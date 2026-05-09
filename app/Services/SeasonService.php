<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Season;
use App\Models\SeasonStat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
// AwardService es opcional — solo lo usa closeAndStartNext() y existe
// luego de Fase C. Se inyecta via constructor con default null para que
// los tests / comandos pre-fase-C no fallen.
use App\Services\AwardService;

class SeasonService
{
    public function __construct(private ?AwardService $awards = null) {}


    /**
     * Defaults del soft reset al cerrar una season. Se pueden overridear
     * por season pasando un config_json al close().
     *
     *   - base:             rating al que todos regresan parcialmente
     *   - factor:           cuanto se preserva del rating (0 = total reset,
     *                       1 = no reset). 0.4 = "el de 3000 vuelve cerca
     *                       de 2100, el de 800 sube a 1220"
     *   - rd_reset:         RD a setear post-reset. 350 es el default Glicko
     *                       y hace que las primeras partidas pesen mucho —
     *                       lo que da la sensacion de "el de elo alto gana
     *                       mas puntos al inicio"
     *   - volatility_reset: vuelve al default Glicko-2 (0.06)
     */
    public const DEFAULT_RESET_CONFIG = [
        'base'             => 1500.0,
        'factor'           => 0.4,
        'rd_reset'         => 350.0,
        'volatility_reset' => 0.06,
    ];

    /**
     * Crea la primera season del sistema, en estado 'active'. Disenado
     * para ejecutarse una unica vez via `seasons:init`.
     *
     * Si ya existe una season activa, lanza excepcion — se puede arrancar
     * la segunda con `closeAndStartNext()`.
     */
    public function init(string $name, string $slug, ?Carbon $endsAt = null): Season
    {
        if (Season::current() !== null) {
            throw new \RuntimeException('Ya hay una season activa. Usar closeAndStartNext.');
        }

        return Season::create([
            'name'      => $name,
            'slug'      => $slug,
            'status'    => Season::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at'   => $endsAt,
        ]);
    }

    /**
     * Asocia matches con season_id=NULL a la season indicada. Util al
     * inicializar la primera season en una DB que ya tiene matches
     * jugadas (caso pre-season en la beta actual).
     *
     * Devuelve la cantidad de matches actualizadas.
     */
    public function backfillOrphanMatches(Season $season): int
    {
        return GameMatch::whereNull('season_id')->update(['season_id' => $season->id]);
    }

    /**
     * Cierra la season activa, snapshotea las stats finales de cada user,
     * aplica el soft reset a los ratings vivos y abre una nueva season.
     *
     * Pasos en transaccion:
     *   1. Marca la season como closed (status + closed_at + reset_config)
     *   2. Para cada user que jugo en la season, crea SeasonStat con
     *      final_rating, final_rd, peak_rating, matches_played, matches_won
     *   3. Ranquea los stats por final_rating DESC y popula final_rank
     *   4. Aplica soft reset a `users.rating/rd/volatility`
     *   5. Crea la nueva season activa
     *
     * NO otorga awards de fin de season — eso lo hace AwardService::evaluateEndOfSeason()
     * en una fase posterior (lo dejamos asi para que SeasonService no dependa
     * del catalogo de awards).
     */
    public function closeAndStartNext(
        Season $current,
        string $nextName,
        string $nextSlug,
        ?Carbon $nextEndsAt = null,
        array $resetConfig = []
    ): Season {
        if (!$current->isActive()) {
            throw new \RuntimeException("Season #{$current->id} no esta activa.");
        }

        $config = array_merge(self::DEFAULT_RESET_CONFIG, $resetConfig);

        return DB::transaction(function () use ($current, $nextName, $nextSlug, $nextEndsAt, $config) {
            // 1. Snapshot de stats finales antes de tocar nada.
            $this->snapshotSeasonStats($current);

            // 2. Otorgar awards de fin de season (champion, elite). Se hace
            //    despues de los stats porque depende de season_stats.final_rank.
            //    Si AwardService no esta disponible (pre-fase-C), skipear.
            if ($this->awards !== null) {
                $this->awards->evaluateEndOfSeason($current);
            }

            // 3. Marcar season como cerrada.
            $current->update([
                'status'            => Season::STATUS_CLOSED,
                'closed_at'         => now(),
                'reset_config_json' => $config,
            ]);

            // 4. Aplicar soft reset a todos los users que tienen rating
            //    distinto al base (los que nunca jugaron quedan como estan).
            $this->applySoftReset($config);

            // 5. Abrir la nueva season activa.
            return Season::create([
                'name'      => $nextName,
                'slug'      => $nextSlug,
                'status'    => Season::STATUS_ACTIVE,
                'starts_at' => now(),
                'ends_at'   => $nextEndsAt,
            ]);
        });
    }

    /**
     * Soft reset puro — formula `new = base + (old - base) * factor`.
     * Expuesta como metodo separado para poder testearla y para que el
     * admin UI pueda mostrar previews de "tu rating quedara en X".
     */
    public function softResetRating(float $oldRating, array $config): float
    {
        return $config['base'] + ($oldRating - $config['base']) * $config['factor'];
    }

    /**
     * Crea un SeasonStat por cada user que tiene >=1 match completed en
     * esta season. Calcula peak_rating mirando los snapshots dentro de
     * matches.host_rating_before / opponent_rating_before.
     */
    private function snapshotSeasonStats(Season $season): void
    {
        // Subqueries para wins y plays por user en esta season.
        // Cada match tiene host y opponent — ambos cuentan como "played".
        $matches = GameMatch::where('season_id', $season->id)
            ->where('status', GameMatch::STATUS_COMPLETED)
            ->get(['id', 'host_user_id', 'opponent_user_id', 'winner_user_id',
                   'host_rating_before', 'opponent_rating_before']);

        // Acumulamos por user_id manualmente para no hacer N queries.
        $stats = [];
        foreach ($matches as $m) {
            foreach ([$m->host_user_id, $m->opponent_user_id] as $uid) {
                if (!isset($stats[$uid])) {
                    $stats[$uid] = ['played' => 0, 'won' => 0, 'peak' => 0.0];
                }
                $stats[$uid]['played']++;
                if ($m->winner_user_id === $uid) $stats[$uid]['won']++;

                // Peak: maximo de los rating_before vistos en la season.
                $rating = $uid === $m->host_user_id ? $m->host_rating_before : $m->opponent_rating_before;
                if ($rating !== null && $rating > $stats[$uid]['peak']) {
                    $stats[$uid]['peak'] = $rating;
                }
            }
        }

        // Cargamos todos los users involucrados en una sola query.
        $userIds = array_keys($stats);
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Crear SeasonStat por cada user. Ordenamos por rating actual DESC
        // para asignar final_rank.
        $sorted = collect($stats)
            ->map(fn ($s, $uid) => array_merge($s, ['user_id' => $uid, 'rating' => $users[$uid]?->rating ?? 0]))
            ->sortByDesc('rating')
            ->values();

        $rank = 1;
        foreach ($sorted as $s) {
            $u = $users[$s['user_id']] ?? null;
            if ($u === null) continue;

            // Bots no entran al ranking pero si tienen stats si jugaron.
            $assignedRank = $u->isBot() ? null : $rank;

            SeasonStat::create([
                'user_id'        => $u->id,
                'season_id'      => $season->id,
                'final_rating'   => $u->rating,
                'final_rd'       => $u->rating_deviation,
                'peak_rating'    => max($s['peak'], $u->rating),
                'final_rank'     => $assignedRank,
                'matches_played' => $s['played'],
                'matches_won'    => $s['won'],
            ]);

            if (!$u->isBot()) $rank++;
        }
    }

    /**
     * Aplica el soft reset a TODOS los users (excepto el bot dev) en un
     * solo UPDATE atomico. Antes hacia un UPDATE por user en chunks de 200
     * — con miles de users eso bloquea el admin UI por minutos.
     *
     * Formula: rating = base + (rating - base) * factor.
     */
    private function applySoftReset(array $config): void
    {
        DB::statement(
            "UPDATE users
             SET rating            = ? + (rating - ?) * ?,
                 rating_deviation  = ?,
                 rating_volatility = ?
             WHERE steam_id != ?",
            [
                $config['base'],
                $config['base'],
                $config['factor'],
                $config['rd_reset'],
                $config['volatility_reset'],
                User::BOT_STEAM_ID,
            ]
        );
    }
}
