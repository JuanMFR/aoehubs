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
    private const PENDING_GRACE_MIN        = 10; // grace para que el lobby se arme
    private const BOT_STEAM_ID             = 'BOTDEV_PERMANENT_QUEUE';

    public function handle(): int
    {
        $staleCutoff   = now()->subMinutes(self::STALE_AFTER_MIN);
        $pendingCutoff = now()->subMinutes(self::PENDING_GRACE_MIN);

        // Procesamos uno por uno porque la decisión depende del par
        // (host_stale, opp_stale, isBot) y del status.
        $active = GameMatch::with(['host', 'opponent'])
            ->whereIn('status', [
                GameMatch::STATUS_DRAFTING,
                GameMatch::STATUS_PENDING,
                GameMatch::STATUS_IN_PROGRESS,
            ])
            ->get();

        $abandoned = 0;
        $forfeits  = 0;

        foreach ($active as $match) {
            $hostStale = $this->isStale($match->host_heartbeat_at, $staleCutoff);
            $oppStale  = $this->isStale($match->opponent_heartbeat_at, $staleCutoff);

            $hostIsBot = $match->host->steam_id            === self::BOT_STEAM_ID;
            $oppIsBot  = $match->opponent?->steam_id       === self::BOT_STEAM_ID;

            // ── Drafting o Pending: si el humano dejo de heartbeatear y la
            //    match tiene mas de PENDING_GRACE_MIN, abandonamos. Sin
            //    rating change porque la partida nunca arranco.
            if (in_array($match->status, [GameMatch::STATUS_DRAFTING, GameMatch::STATUS_PENDING], true)) {
                if ($match->created_at >= $pendingCutoff) continue; // demasiado nueva, dale tiempo

                $humanStale = false;
                if (! $hostIsBot && $hostStale) $humanStale = true;
                if (! $oppIsBot  && $oppStale)  $humanStale = true;

                if ($humanStale) {
                    $match->update(['status' => GameMatch::STATUS_ABANDONED]);
                    $abandoned++;
                }
                continue;
            }

            // ── In progress vs bot: el bot no heartbeatea, miramos solo al humano.
            //    Si el humano se cayo mid-game, abandon (sin forfeit/rating change
            //    contra bot).
            if ($hostIsBot || $oppIsBot) {
                $humanStale = $hostIsBot ? $oppStale : $hostStale;
                if ($humanStale) {
                    $match->update(['status' => GameMatch::STATUS_ABANDONED]);
                    $abandoned++;
                }
                continue;
            }

            // ── In progress PvP: aplicamos forfeit si solo uno se cayó. Si los
            //    dos se cayeron, abandonamos sin rating change.
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
        }

        $this->info("Abandoned: {$abandoned} | Forfeits: {$forfeits}");
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
