<?php

/**
 * Catalogo de awards (logros / placas) de AoEHubs.
 *
 * Cada entrada define:
 *   - title:        nombre visible
 *   - description:  texto que aparece en la vitrina y tooltips
 *   - icon:         path relativo a /images/{icon} (ej. "awards/centurion.svg").
 *                   Si el archivo no existe en disco, la UI muestra un
 *                   placeholder generico — sirve para subir arte mas tarde
 *                   sin tocar codigo.
 *   - scope:        'season' (atado a una season especifica) o 'global'
 *                   (no atado a season; ej. "primer match ever").
 *   - evaluator:    'instant'        — se evalua al completarse cada match
 *                   'end_of_season'  — se evalua al cerrar la season
 *                   'manual'         — solo via comando admin
 *   - metric_key:   identifica que metrica leer en AwardEvaluator (solo
 *                   para evaluator=instant). Ver AwardEvaluator::compute().
 *   - tiers:        array tier => [threshold, label]. Para awards single-tier
 *                   ponemos un solo tier sin threshold.
 *
 * IMPORTANTE: agregar un nuevo award aqui no lo otorga retroactivamente.
 * Para eso correr `php artisan awards:backfill` despues de actualizar este
 * archivo.
 */

use App\Models\UserAward;

return [

    // ─── Volumen y dedicacion (per-season, tiered, instant) ──────────────

    'centurion' => [
        'title'       => 'Centurión',
        'description' => 'Cantidad de partidas completadas en la season.',
        'icon'        => 'awards/centurion.svg',
        'scope'       => 'season',
        'evaluator'   => 'instant',
        'metric_key'  => 'matches_played_in_season',
        'tiers' => [
            UserAward::TIER_BRONZE    => ['threshold' => 25],
            UserAward::TIER_SILVER    => ['threshold' => 50],
            UserAward::TIER_GOLD      => ['threshold' => 100],
            UserAward::TIER_PLATINUM  => ['threshold' => 250],
            UserAward::TIER_PRISMATIC => ['threshold' => 500],
        ],
    ],

    'streak' => [
        'title'       => 'Imparable',
        'description' => 'Mejor racha de victorias consecutivas en la season.',
        'icon'        => 'awards/streak.svg',
        'scope'       => 'season',
        'evaluator'   => 'instant',
        'metric_key'  => 'peak_streak_in_season',
        'tiers' => [
            UserAward::TIER_BRONZE    => ['threshold' => 3],
            UserAward::TIER_SILVER    => ['threshold' => 5],
            UserAward::TIER_GOLD      => ['threshold' => 8],
            UserAward::TIER_PLATINUM  => ['threshold' => 12],
            UserAward::TIER_PRISMATIC => ['threshold' => 20],
        ],
    ],

    'climber' => [
        'title'       => 'Escalador',
        'description' => 'Pico de rating alcanzado durante la season.',
        'icon'        => 'awards/climber.svg',
        'scope'       => 'season',
        'evaluator'   => 'instant',
        'metric_key'  => 'peak_rating_in_season',
        'tiers' => [
            UserAward::TIER_BRONZE    => ['threshold' => 1700],
            UserAward::TIER_SILVER    => ['threshold' => 1900],
            UserAward::TIER_GOLD      => ['threshold' => 2100],
            UserAward::TIER_PLATINUM  => ['threshold' => 2300],
            UserAward::TIER_PRISMATIC => ['threshold' => 2500],
        ],
    ],

    'titan_slayer' => [
        'title'       => 'Cazatitanes',
        'description' => 'Victorias contra rivales con +200 de rating de diferencia.',
        'icon'        => 'awards/titan_slayer.svg',
        'scope'       => 'season',
        'evaluator'   => 'instant',
        'metric_key'  => 'wins_vs_higher_rated_in_season',
        'tiers' => [
            UserAward::TIER_BRONZE    => ['threshold' => 5],
            UserAward::TIER_SILVER    => ['threshold' => 15],
            UserAward::TIER_GOLD      => ['threshold' => 30],
            UserAward::TIER_PLATINUM  => ['threshold' => 60],
            UserAward::TIER_PRISMATIC => ['threshold' => 100],
        ],
    ],

    'civ_specialist' => [
        'title'       => 'Especialista de Civ',
        'description' => 'Victorias acumuladas con una misma civilizacion.',
        'icon'        => 'awards/civ_specialist.svg',
        'scope'       => 'season',
        'evaluator'   => 'instant',
        'metric_key'  => 'top_civ_wins_in_season',
        'tiers' => [
            UserAward::TIER_BRONZE    => ['threshold' => 10],
            UserAward::TIER_SILVER    => ['threshold' => 20],
            UserAward::TIER_GOLD      => ['threshold' => 40],
            UserAward::TIER_PLATINUM  => ['threshold' => 70],
            UserAward::TIER_PRISMATIC => ['threshold' => 100],
        ],
    ],

    // ─── Cierre de season (per-season, tiered, end_of_season) ──────────

    'elite' => [
        'title'       => 'Élite',
        'description' => 'Posicion en el top final de la season.',
        'icon'        => 'awards/elite.svg',
        'scope'       => 'season',
        'evaluator'   => 'end_of_season',
        'metric_key'  => 'final_rank',
        'tiers' => [
            UserAward::TIER_BRONZE    => ['rank_max' => 100],
            UserAward::TIER_SILVER    => ['rank_max' => 50],
            UserAward::TIER_GOLD      => ['rank_max' => 25],
            UserAward::TIER_PLATINUM  => ['rank_max' => 10],
            UserAward::TIER_PRISMATIC => ['rank_max' => 3],
        ],
    ],

    'champion' => [
        'title'       => 'Campeón',
        'description' => 'Top 1 al cerrar la season — el mas alto reconocimiento.',
        'icon'        => 'awards/champion.svg',
        'scope'       => 'season',
        'evaluator'   => 'end_of_season',
        'metric_key'  => 'final_rank',
        'tiers' => [
            UserAward::TIER_PRISMATIC => ['rank_max' => 1],
        ],
    ],

    // ─── Eventos especiales (single-tier, instant) ──────────────────────

    'comeback' => [
        'title'       => 'Comeback King',
        'description' => 'Ganaste contra un rival con +100 de rating sobre vos.',
        'icon'        => 'awards/comeback.svg',
        'scope'       => 'season',
        'evaluator'   => 'instant',
        'metric_key'  => 'comeback_match', // se evalua match-por-match, no acumulado
        'tiers' => [
            UserAward::TIER_GOLD => [],
        ],
    ],

    // ─── Globales (no atados a una season) ──────────────────────────────

    'first_steps' => [
        'title'       => 'Primeros pasos',
        'description' => 'Tu primera partida en AoEHubs.',
        'icon'        => 'awards/first_steps.svg',
        'scope'       => 'global',
        'evaluator'   => 'instant',
        'metric_key'  => 'matches_played_total',
        'tiers' => [
            UserAward::TIER_BRONZE => ['threshold' => 1],
        ],
    ],

    'first_win' => [
        'title'       => 'Primera victoria',
        'description' => 'Tu primera victoria en AoEHubs.',
        'icon'        => 'awards/first_win.svg',
        'scope'       => 'global',
        'evaluator'   => 'instant',
        'metric_key'  => 'wins_total',
        'tiers' => [
            UserAward::TIER_SILVER => ['threshold' => 1],
        ],
    ],

    // ─── Manuales (otorgados por admin via comando) ─────────────────────

    'founder_a' => [
        'title'       => 'Fundador Pre-A',
        'description' => 'Participaste en Pre-season A — la primera generacion de AoEHubs.',
        'icon'        => 'awards/founder_a.svg',
        'scope'       => 'global',
        'evaluator'   => 'manual',
        'tiers' => [
            UserAward::TIER_PRISMATIC => [],
        ],
    ],

];
