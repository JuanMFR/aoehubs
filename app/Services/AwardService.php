<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Season;
use App\Models\SeasonStat;
use App\Models\User;
use App\Models\UserAward;
use Illuminate\Support\Facades\Log;

/**
 * Punto de entrada para otorgar awards.
 *
 * Tres flows principales:
 *
 *   1. evaluateInstantForUser($user, $match)
 *      Llamado por MatchObserver cuando un match pasa a completed.
 *      Evalua todos los awards con evaluator='instant' definidos en
 *      config/awards.php para el user, y otorga los que correspondan.
 *
 *   2. evaluateEndOfSeason($season)
 *      Llamado por SeasonService::closeAndStartNext() despues de
 *      snapshotear las stats. Otorga awards atados a final_rank
 *      (champion, elite).
 *
 *   3. grantManual($user, $code, $tier)
 *      Para awards con evaluator='manual' — typically founders,
 *      special recognitions. Disparado por comando admin.
 */
class AwardService
{
    public function __construct(private AwardEvaluator $evaluator) {}

    /**
     * Crea el UserAward si todavia no existe para esa combinacion
     * (user, code, tier, season). Idempotente — devuelve null si ya existe.
     */
    public function grant(User $user, string $code, int $tier, ?int $seasonId, array $metadata = []): ?UserAward
    {
        $exists = UserAward::where('user_id', $user->id)
            ->where('award_code', $code)
            ->where('tier', $tier)
            ->where('season_id', $seasonId)
            ->exists();

        if ($exists) return null;

        return UserAward::create([
            'user_id'       => $user->id,
            'season_id'     => $seasonId,
            'award_code'    => $code,
            'tier'          => $tier,
            'awarded_at'    => now(),
            'metadata_json' => $metadata ?: null,
        ]);
    }

    /**
     * Para awards manuales (founders, etc). Wrapper sobre grant() con la
     * verificacion de que el award este declarado como manual.
     */
    public function grantManual(User $user, string $code, ?int $tier = null): ?UserAward
    {
        $def = config("awards.{$code}");
        if (!$def) {
            throw new \InvalidArgumentException("Award no registrado: {$code}");
        }
        if (($def['evaluator'] ?? null) !== 'manual') {
            throw new \InvalidArgumentException("Award '{$code}' no es manual.");
        }

        // Si tier no se especifica, usar el unico declarado en config.
        if ($tier === null) {
            $tiers = array_keys($def['tiers'] ?? []);
            if (count($tiers) !== 1) {
                throw new \InvalidArgumentException("Award '{$code}' tiene multiples tiers, especificar uno.");
            }
            $tier = $tiers[0];
        }

        $seasonId = ($def['scope'] ?? 'season') === 'global' ? null : Season::current()?->id;
        return $this->grant($user, $code, $tier, $seasonId, ['source' => 'manual']);
    }

    /**
     * Evalua todos los instant awards para un user. Si `$match` esta dado,
     * tambien evalua awards match-locales como "comeback".
     *
     * Se llama desde MatchObserver con la season actual.
     */
    public function evaluateInstantForUser(User $user, ?GameMatch $match = null): array
    {
        if ($user->isBot()) return [];

        $season = Season::current();
        $newAwards = [];

        foreach (config('awards', []) as $code => $def) {
            if (($def['evaluator'] ?? null) !== 'instant') continue;
            $newAwards = array_merge($newAwards, $this->evaluateAward($code, $def, $user, $season, $match));
        }

        if (!empty($newAwards)) {
            Log::info("AwardService granted " . count($newAwards) . " award(s) to user #{$user->id}", [
                'codes' => array_map(fn ($a) => "{$a->award_code}:t{$a->tier}", $newAwards),
            ]);
        }

        return $newAwards;
    }

    /**
     * Evalua awards instant SEASON-SCOPED para un user en una season
     * especifica (puede ser una closed). Util en backfill — recorre
     * todas las seasons historicamente para que los users tengan los
     * Centurion/streak/etc. correspondientes a cada una.
     *
     * Skipea globales — esos se evaluan por separado (una sola vez).
     */
    public function evaluateInstantForUserInSeason(User $user, Season $season): array
    {
        if ($user->isBot()) return [];

        $newAwards = [];
        foreach (config('awards', []) as $code => $def) {
            if (($def['evaluator'] ?? null) !== 'instant') continue;
            if (($def['scope'] ?? 'season') === 'global') continue;
            $newAwards = array_merge($newAwards, $this->evaluateAward($code, $def, $user, $season, null));
        }
        return $newAwards;
    }

    /**
     * Evalua awards instant GLOBALES para un user (no atados a season).
     * Para backfill — los globales se evaluan una sola vez por user.
     */
    public function evaluateGlobalInstantForUser(User $user): array
    {
        if ($user->isBot()) return [];

        $newAwards = [];
        foreach (config('awards', []) as $code => $def) {
            if (($def['evaluator'] ?? null) !== 'instant') continue;
            if (($def['scope'] ?? 'season') !== 'global') continue;
            $newAwards = array_merge($newAwards, $this->evaluateAward($code, $def, $user, null, null));
        }
        return $newAwards;
    }

    /**
     * Logica central de evaluacion para un award especifico. Maneja:
     *   - awards tiered numericos (centurion, streak, etc.)
     *   - awards match-locales (comeback)
     */
    private function evaluateAward(string $code, array $def, User $user, ?Season $season, ?GameMatch $match): array
    {
        $isGlobal = ($def['scope'] ?? 'season') === 'global';
        $scopeSeasonId = $isGlobal ? null : $season?->id;

        // Award match-local (comeback): solo otorga si pasa match=! null y
        // ese match cumple el predicado.
        if (($def['metric_key'] ?? null) === 'comeback_match') {
            if ($match === null) return [];
            if (! $this->evaluator->isComebackWin($user, $match)) return [];

            $awards = [];
            foreach ($def['tiers'] ?? [] as $tier => $_) {
                $a = $this->grant($user, $code, $tier, $scopeSeasonId, ['match_id' => $match->id]);
                if ($a) $awards[] = $a;
            }
            return $awards;
        }

        // Awards tiered con threshold numerico (mayoria de casos).
        $value = $this->evaluator->compute($def['metric_key'], $user, $season);
        $awards = [];
        foreach ($def['tiers'] ?? [] as $tier => $tierConfig) {
            $threshold = $tierConfig['threshold'] ?? null;
            if ($threshold !== null && $value >= $threshold) {
                $a = $this->grant($user, $code, $tier, $scopeSeasonId, ['value' => $value]);
                if ($a) $awards[] = $a;
            }
        }
        return $awards;
    }

    /**
     * Evalua awards de fin de season (champion, elite). Asume que
     * SeasonService ya populo `season_stats.final_rank`. Otorga TODOS los
     * tiers correspondientes — si alguien quedo Top 1, recibe tanto
     * champion como elite (todos sus tiers escalonados).
     */
    public function evaluateEndOfSeason(Season $season): array
    {
        $newAwards = [];

        $stats = SeasonStat::where('season_id', $season->id)
            ->whereNotNull('final_rank')
            ->orderBy('final_rank')
            ->with('user')
            ->get();

        foreach ($stats as $stat) {
            if ($stat->user === null || $stat->user->isBot()) continue;

            foreach (config('awards', []) as $code => $def) {
                if (($def['evaluator'] ?? null) !== 'end_of_season') continue;

                foreach ($def['tiers'] ?? [] as $tier => $tierConfig) {
                    $rankMax = $tierConfig['rank_max'] ?? null;
                    if ($rankMax !== null && $stat->final_rank <= $rankMax) {
                        $a = $this->grant($stat->user, $code, $tier, $season->id, [
                            'rank' => $stat->final_rank,
                            'final_rating' => $stat->final_rating,
                        ]);
                        if ($a) $newAwards[] = $a;
                    }
                }
            }
        }

        Log::info("AwardService granted end-of-season: " . count($newAwards) . " award(s) for season #{$season->id}");
        return $newAwards;
    }

    /**
     * Re-evalua instant awards historicamente — recorre TODAS las seasons
     * (closed + active) y todos los users. Idempotente.
     *
     * Util:
     *   - Despues de inicializar la primera season (otorga retroactivamente
     *     awards a los testers que jugaron antes del observer existir)
     *   - Despues de agregar un award nuevo a config/awards.php
     *   - Despues de cambiar un threshold
     *
     * NO otorga awards de fin de season — para eso correr por separado
     * AwardService::evaluateEndOfSeason() o (en flow normal) lo dispara
     * SeasonService al cerrar la season.
     */
    public function backfillAll(): array
    {
        $stats = ['users_processed' => 0, 'awards_granted' => 0];

        $users = User::where('steam_id', '!=', User::BOT_STEAM_ID)->get();
        $seasons = Season::all();

        foreach ($users as $user) {
            // Globales: una sola vez por user.
            $stats['awards_granted'] += count($this->evaluateGlobalInstantForUser($user));

            // Por-season: una vez por (user, season). Esto otorga Centurion/
            // streak/climber/etc. retroactivamente para seasons cerradas.
            foreach ($seasons as $season) {
                $stats['awards_granted'] += count($this->evaluateInstantForUserInSeason($user, $season));
            }

            $stats['users_processed']++;
        }

        return $stats;
    }
}
