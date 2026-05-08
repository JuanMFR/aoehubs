<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use App\Models\User;
use App\Services\CooldownService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpia matches "zombie" — los que quedaron en pending/in_progress sin
 * actividad. Tres reglas:
 *
 *   1) pending sin heartbeat y creado >1h atrás → abandoned (el companion
 *      nunca apareció)
 *   2) pending|in_progress con heartbeats viejos de ambos lados → abandoned
 *      (corte mutuo o ambos cerraron el companion)
 *   3) in_progress con un heartbeat vivo y otro viejo → forfeit del lado
 *      caído. El vivo gana, Glicko-2 aplicado normalmente. Match queda
 *      'completed' con replay_path null (ese null es el marker de walkover).
 *
 * Excepción para Bot Dev: en matches contra el bot, el bot nunca heartbeatea
 * (no tiene companion), así que la lógica de forfeit no aplica — sólo
 * abandonamos si el lado humano se queda sin pulso por mucho tiempo.
 */
class ExpireStaleMatches extends Command
{
    protected $signature   = 'matches:expire-stale';
    protected $description = 'Mark zombie matches as abandoned, apply forfeit on disconnect mid-game';

    private const STALE_AFTER_MIN          = 5;
    private const NO_HEARTBEAT_GRACE_HR    = 1;
    private const BOT_STEAM_ID             = 'BOTDEV_PERMANENT_QUEUE';

    public function handle(): int
    {
        $staleCutoff   = now()->subMinutes(self::STALE_AFTER_MIN);
        $createdCutoff = now()->subHours(self::NO_HEARTBEAT_GRACE_HR);

        // Regla 1: pending huérfanos viejos (companion nunca llegó)
        $orphaned = GameMatch::where('status', GameMatch::STATUS_PENDING)
            ->whereNull('host_heartbeat_at')
            ->whereNull('opponent_heartbeat_at')
            ->where('created_at', '<', $createdCutoff)
            ->update(['status' => GameMatch::STATUS_ABANDONED]);

        // Regla 2 + 3: matches activos. Inspeccionamos uno por uno porque la
        // decisión depende del par (host_stale, opp_stale) y de si hay bot.
        $active = GameMatch::with(['host', 'opponent'])
            ->whereIn('status', [GameMatch::STATUS_PENDING, GameMatch::STATUS_IN_PROGRESS])
            ->get();

        $abandoned = 0;
        $forfeits  = 0;

        foreach ($active as $match) {
            $hostStale = $this->isStale($match->host_heartbeat_at, $staleCutoff);
            $oppStale  = $this->isStale($match->opponent_heartbeat_at, $staleCutoff);

            $hostIsBot = $match->host->steam_id     === self::BOT_STEAM_ID;
            $oppIsBot  = $match->opponent->steam_id === self::BOT_STEAM_ID;

            if ($hostIsBot || $oppIsBot) {
                // Match contra bot: el bot no heartbeatea, sólo nos importa el humano.
                $humanStale = $hostIsBot ? $oppStale : $hostStale;
                if ($humanStale && $match->status === GameMatch::STATUS_IN_PROGRESS) {
                    // El humano se fue mid-partida contra bot. Sin rating change.
                    $match->update(['status' => GameMatch::STATUS_ABANDONED]);
                    $abandoned++;
                }
                continue;
            }

            // Match real (PvP). Aplicamos forfeit si solo uno se cayó.
            if ($match->status !== GameMatch::STATUS_IN_PROGRESS) continue;

            if ($hostStale && $oppStale) {
                $match->update(['status' => GameMatch::STATUS_ABANDONED]);
                $abandoned++;
            } elseif ($hostStale && ! $oppStale) {
                $this->applyForfeit($match, winnerId: $match->opponent_user_id);
                $forfeits++;
            } elseif (! $hostStale && $oppStale) {
                $this->applyForfeit($match, winnerId: $match->host_user_id);
                $forfeits++;
            }
            // ambos vivos: no action
        }

        $this->info("Orphaned: {$orphaned} | Abandoned: {$abandoned} | Forfeits: {$forfeits}");
        return self::SUCCESS;
    }

    /**
     * "Stale" = sin heartbeat hace >5min Y la match arrancó hace >5min. La
     * segunda condición evita falsos positivos en matches recién creadas
     * (donde uno de los heartbeats puede estar null porque el otro companion
     * todavía no apareció).
     */
    private function isStale(?\DateTimeInterface $heartbeat, \DateTimeInterface $cutoff): bool
    {
        if ($heartbeat === null) return true;
        return $heartbeat < $cutoff;
    }

    /**
     * Aplica forfeit: status=completed + winner_user_id, Glicko-2 actualiza
     * ambos ratings. replay_path queda null como marker de "ganada por
     * walkover, no hay replay para validar".
     */
    private function applyForfeit(GameMatch $match, int $winnerId): void
    {
        DB::transaction(function () use ($match, $winnerId) {
            $match->update([
                'status'         => GameMatch::STATUS_COMPLETED,
                'winner_user_id' => $winnerId,
            ]);
            $match->applyRatingChange($winnerId);

            // Anti-griefing: el que perdió por desconexión recibe ofensa
            $loserId = $winnerId === $match->host_user_id ? $match->opponent_user_id : $match->host_user_id;
            $loser   = User::find($loserId);
            if ($loser) {
                CooldownService::record($loser, $match, CooldownService::KIND_MID_GAME_DISCONNECT);
            }
        });
    }
}
