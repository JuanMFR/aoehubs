<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Calculadora de metricas para awards. Cada `metric_key` definido en
 * config/awards.php se mapea a un metodo aca.
 *
 * Mantenemos esto separado de AwardService para que sea testeable sin
 * tocar el modelo de UserAward.
 */
class AwardEvaluator
{
    /**
     * Devuelve el valor numerico actual de la metrica para el user en la
     * season indicada (o global si season=null).
     *
     * Lanza si la metric_key no esta registrada — error de config, no se
     * tolera silenciosamente.
     */
    public function compute(string $metricKey, User $user, ?Season $season): int|float
    {
        return match ($metricKey) {
            'matches_played_in_season'   => $this->matchesPlayedInSeason($user, $season),
            'wins_in_season'             => $this->winsInSeason($user, $season),
            'peak_streak_in_season'      => $this->peakStreakInSeason($user, $season),
            'peak_rating_in_season'      => $this->peakRatingInSeason($user, $season),
            'wins_vs_higher_rated_in_season' => $this->winsVsHigherRatedInSeason($user, $season),
            'top_civ_wins_in_season'     => $this->topCivWinsInSeason($user, $season),
            'matches_played_total'       => $this->matchesPlayedTotal($user),
            'wins_total'                 => $this->winsTotal($user),
            default => throw new \InvalidArgumentException("Unknown metric key: {$metricKey}"),
        };
    }

    /** Cantidad de matches completed donde el user fue host u opponent. */
    private function matchesPlayedInSeason(User $user, ?Season $season): int
    {
        return $this->scopeUserMatches($user, $season, GameMatch::STATUS_COMPLETED)->count();
    }

    private function winsInSeason(User $user, ?Season $season): int
    {
        return $this->scopeUserMatches($user, $season, GameMatch::STATUS_COMPLETED)
            ->where('winner_user_id', $user->id)
            ->count();
    }

    /** Mejor racha de wins consecutivos durante la season (no la actual). */
    private function peakStreakInSeason(User $user, ?Season $season): int
    {
        $matches = $this->scopeUserMatches($user, $season, GameMatch::STATUS_COMPLETED)
            ->orderBy('id') // chronological
            ->get(['id', 'winner_user_id']);

        $current = 0;
        $peak = 0;
        foreach ($matches as $m) {
            if ($m->winner_user_id === $user->id) {
                $current++;
                if ($current > $peak) $peak = $current;
            } else {
                $current = 0;
            }
        }
        return $peak;
    }

    /**
     * Pico de rating durante la season. Lo calculamos a partir de los
     * snapshots `host_rating_before/change` o `opponent_rating_before/change`,
     * incluyendo el rating actual del user como floor (en caso de no haber
     * jugado partidas todavia esta season).
     */
    private function peakRatingInSeason(User $user, ?Season $season): float
    {
        $matches = $this->scopeUserMatches($user, $season, GameMatch::STATUS_COMPLETED)
            ->whereNotNull('host_rating_before')
            ->get([
                'host_user_id', 'opponent_user_id',
                'host_rating_before', 'host_rating_change',
                'opponent_rating_before', 'opponent_rating_change',
            ]);

        $peak = (float) $user->rating; // current rating como floor
        foreach ($matches as $m) {
            if ($m->host_user_id === $user->id) {
                $rating = (float) $m->host_rating_before + (float) ($m->host_rating_change ?? 0);
            } else {
                $rating = (float) $m->opponent_rating_before + (float) ($m->opponent_rating_change ?? 0);
            }
            if ($rating > $peak) $peak = $rating;
        }
        return $peak;
    }

    /**
     * Wins donde el rating del rival ANTES del match era >200 puntos por
     * encima del nuestro.
     */
    private function winsVsHigherRatedInSeason(User $user, ?Season $season): int
    {
        $q = GameMatch::query()
            ->where('status', GameMatch::STATUS_COMPLETED)
            ->where('winner_user_id', $user->id);

        if ($season) $q->where('season_id', $season->id);

        // Doble caso: yo era host y el opponent tenia +200 sobre mi, o viceversa.
        $q->where(function ($w) use ($user) {
            $w->where(function ($a) use ($user) {
                $a->where('host_user_id', $user->id)
                  ->whereColumn('host_rating_before', '<', DB::raw('opponent_rating_before - 200'));
            })->orWhere(function ($b) use ($user) {
                $b->where('opponent_user_id', $user->id)
                  ->whereColumn('opponent_rating_before', '<', DB::raw('host_rating_before - 200'));
            });
        });

        return $q->count();
    }

    /**
     * Cantidad de wins con la civ mas usada del user en la season. Es el
     * "max wins con una sola civ" — para el award de civ specialist.
     */
    private function topCivWinsInSeason(User $user, ?Season $season): int
    {
        $matches = $this->scopeUserMatches($user, $season, GameMatch::STATUS_COMPLETED)
            ->where('winner_user_id', $user->id)
            ->with('civDraft')
            ->get();

        $civWins = [];
        foreach ($matches as $m) {
            if (!$m->civDraft) continue;
            $civ = $m->host_user_id === $user->id
                ? $m->civDraft->host_final_civ
                : $m->civDraft->opponent_final_civ;
            if ($civ === null) continue;
            $civWins[$civ] = ($civWins[$civ] ?? 0) + 1;
        }

        return $civWins ? max($civWins) : 0;
    }

    private function matchesPlayedTotal(User $user): int
    {
        return $this->scopeUserMatches($user, null, GameMatch::STATUS_COMPLETED)->count();
    }

    private function winsTotal(User $user): int
    {
        return $this->scopeUserMatches($user, null, GameMatch::STATUS_COMPLETED)
            ->where('winner_user_id', $user->id)
            ->count();
    }

    /**
     * Helper que arma el query base de "matches del user X en season Y
     * con status Z". DRY entre los metodos de arriba.
     */
    private function scopeUserMatches(User $user, ?Season $season, string $status)
    {
        $q = GameMatch::where('status', $status)
            ->where(function ($w) use ($user) {
                $w->where('host_user_id', $user->id)
                  ->orWhere('opponent_user_id', $user->id);
            });

        if ($season) $q->where('season_id', $season->id);

        return $q;
    }

    /**
     * Predicado especial para el award "comeback": el user gano un match
     * donde su rating pre-match estaba >100 puntos por debajo del rival.
     */
    public function isComebackWin(User $user, GameMatch $match): bool
    {
        if ($match->winner_user_id !== $user->id) return false;
        if ($match->host_rating_before === null || $match->opponent_rating_before === null) return false;

        $myRating  = $match->host_user_id === $user->id ? $match->host_rating_before : $match->opponent_rating_before;
        $oppRating = $match->host_user_id === $user->id ? $match->opponent_rating_before : $match->host_rating_before;

        return $oppRating - $myRating > 100;
    }
}
