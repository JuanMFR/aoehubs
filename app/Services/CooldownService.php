<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sistema anti-griefing. Cuando un user comete una "ofensa" (abandona lobby
 * o se desconecta mid-partida causando forfeit), registramos el evento en
 * `match_offenses`. Si en las ultimas 24h tiene N ofensas, le aplicamos un
 * cooldown progresivo en queue.
 *
 * Tipos de ofensas:
 *   - LOBBY_ABORT         → user abortó el lobby antes de empezar
 *   - MID_GAME_DISCONNECT → user dejó de heartbeatear durante in_progress
 *                            (forfeit aplicado en su contra)
 *
 * Escalado (en ventana mobil de 24h, contando ofensas viejas + la nueva):
 *   1ra ofensa → 0 (warning silencioso)
 *   2da       → 5 min
 *   3ra       → 30 min
 *   4ta       → 2 h
 *   5ta+      → 24 h
 *
 * Para queries: `User::isInCooldown()` y `Matchmaking::joinQueue` chequea
 * antes de meter al user en cola.
 */
class CooldownService
{
    public const KIND_LOBBY_ABORT         = 'lobby_abort';
    public const KIND_MID_GAME_DISCONNECT = 'mid_game_disconnect';

    private const WINDOW_HOURS = 24;

    private const COOLDOWN_BY_OFFENSE_COUNT = [
        1 => 0,           // 1ra: solo warning, no cooldown
        2 => 5  * 60,     // 5 min
        3 => 30 * 60,     // 30 min
        4 => 2  * 3600,   // 2 horas
        5 => 24 * 3600,   // 24 horas
    ];

    private const MAX_COOLDOWN_SECONDS = 24 * 3600; // techo (a la 5ta+)

    /**
     * Registra una ofensa y aplica cooldown si corresponde. Devuelve los
     * segundos del nuevo cooldown (0 = sin cooldown, solo warning).
     */
    public static function record(User $user, GameMatch $match, string $kind): int
    {
        if ($user->isBot()) return 0; // bots no se cooldownean

        return DB::transaction(function () use ($user, $match, $kind) {
            DB::table('match_offenses')->insert([
                'user_id'    => $user->id,
                'match_id'   => $match->id,
                'kind'       => $kind,
                'created_at' => now(),
            ]);

            // Contar ofensas en ventana movil (incluyendo la recién insertada)
            $count = DB::table('match_offenses')
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS))
                ->count();

            $cdSecs = self::COOLDOWN_BY_OFFENSE_COUNT[$count] ?? self::MAX_COOLDOWN_SECONDS;

            if ($cdSecs > 0) {
                $user->update(['cooldown_until' => now()->addSeconds($cdSecs)]);
            }

            return $cdSecs;
        });
    }

    /**
     * Devuelve los segundos restantes de cooldown para un user, o 0 si no está.
     */
    public static function remainingSeconds(User $user): int
    {
        if (! $user->isInCooldown()) return 0;
        return max(0, now()->diffInSeconds($user->cooldown_until, false));
    }

    public static function formatSeconds(int $secs): string
    {
        if ($secs <= 0)        return '';
        if ($secs < 60)        return "{$secs}s";
        if ($secs < 3600)      return floor($secs / 60) . "min";
        if ($secs < 86400)     return floor($secs / 3600) . "h " . floor(($secs % 3600) / 60) . "min";
        return floor($secs / 86400) . "d";
    }
}
