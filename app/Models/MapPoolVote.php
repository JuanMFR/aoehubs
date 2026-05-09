<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Una eleccion de pool de mapas. Ciclo de vida:
 *
 *   open       → starts_at <= now < ends_at, users votan
 *   closed     → ends_at pasado, ganadores aplicados al pool
 *   cancelled  → admin lo cancelo manual (evento pro-pack, error, etc)
 *
 * Pool final tras aplicar = `Map::is_fixed_in_pool=true` + winners_json.
 */
class MapPoolVote extends Model
{
    public const STATUS_OPEN      = 'open';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'pool_size_voted',
        'status',
        'applied_at',
        'winners_json',
    ];

    // Default explicito para que el objeto recien creado en memoria refleje
    // el default de la columna sin necesidad de un fresh(). Eloquent NO
    // refresca defaults del DB despues de un create() — sin esto,
    // applyToPool() veria status=null y returnaria temprano.
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected function casts(): array
    {
        return [
            'starts_at'       => 'datetime',
            'ends_at'         => 'datetime',
            'applied_at'      => 'datetime',
            'pool_size_voted' => 'integer',
            'winners_json'    => 'array',
        ];
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, 'map_pool_vote_candidates', 'vote_id', 'map_id');
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(MapPoolVoteBallot::class, 'vote_id');
    }

    /** Esta abierta y dentro de la ventana de votacion. */
    public function isVotable(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->starts_at?->isPast()
            && $this->ends_at?->isFuture();
    }

    /**
     * Computa el ranking de candidatos por cantidad de menciones en ballots.
     * Tiebreaker para DISPLAY (deterministico, predecible al recargar):
     *   1. votos DESC
     *   2. pool_winner_count ASC (favor rotacion: el que menos veces gano)
     *   3. nombre ASC (alfabetico, ultimo recurso)
     *
     * Para PICKING winners (no display) ver `pickWinners()` que reemplaza
     * el ultimo nivel por random.
     *
     * @return \Illuminate\Support\Collection collection of {map, votes, pool_winner_count}
     */
    public function tally(): \Illuminate\Support\Collection
    {
        $candidates = $this->candidates()->orderBy('name')->get()->keyBy('id');
        $counts     = array_fill_keys($candidates->keys()->all(), 0);

        foreach ($this->ballots()->get() as $ballot) {
            foreach ($ballot->votes_json ?? [] as $mapId) {
                if (isset($counts[$mapId])) {
                    $counts[$mapId]++;
                }
            }
        }

        $rows = collect($counts)->map(fn ($votes, $mapId) => [
            'map'               => $candidates[$mapId],
            'votes'             => $votes,
            'pool_winner_count' => $candidates[$mapId]->pool_winner_count,
        ]);

        return $rows->sortBy([
            ['votes', 'desc'],
            ['pool_winner_count', 'asc'],
            fn ($a, $b) => strcmp($a['map']->name, $b['map']->name),
        ])->values();
    }

    /**
     * Como tally() pero rompe empates exactos (mismos votos Y mismo
     * pool_winner_count) al RANDOM en lugar de alfabetico. Usado por
     * applyToPool() para evitar sesgo cuando hay empate perfecto en el
     * boundary del top-N.
     *
     * Implementacion: agrupamos rows por la tupla (votos, pwc), shuffleamos
     * cada grupo, y reflattenamos preservando el orden de grupos (que viene
     * sorteado correctamente desde tally()). Asi solo se randomiza dentro
     * de cada bucket, no el orden general.
     *
     * @return int[] map_ids ganadores
     */
    public function pickWinners(): array
    {
        $shuffled = $this->tally()
            ->groupBy(fn ($r) => $r['votes'] . '|' . $r['pool_winner_count'])
            ->map(fn ($group) => $group->shuffle())
            ->flatten(1);

        return $shuffled->take($this->pool_size_voted)
                        ->pluck('map.id')
                        ->all();
    }

    /**
     * Aplica los ganadores al pool: deja activos los maps fijos + los top-N
     * votados, desactiva el resto. Snapshot a `winners_json` y marca
     * applied_at/status=closed.
     *
     * Idempotente: si ya esta closed, no hace nada.
     *
     * Devuelve los map_ids ganadores (vacio si la votacion no tuvo ningun ballot).
     */
    public function applyToPool(): array
    {
        if ($this->status !== self::STATUS_OPEN) {
            return $this->winners_json ?? [];
        }

        return DB::transaction(function () {
            $totalVotes = $this->ballots()->count();

            // Sin ballots: no aplicamos cambios al pool. Cerramos la votacion
            // en estado "closed" pero sin winners para que sea explicito que
            // no aplico nada — el admin sabe que hay que crear otra.
            if ($totalVotes === 0) {
                $this->update([
                    'status'       => self::STATUS_CLOSED,
                    'applied_at'   => null,
                    'winners_json' => [],
                ]);
                return [];
            }

            // Top N con tiebreak random (votos DESC, pool_winner_count ASC,
            // shuffle dentro de cada bucket exacto). Ver pickWinners().
            $winnerIds = $this->pickWinners();

            // Deactivacion masiva, despues activacion selectiva.
            // - Maps fijos: siempre activos.
            // - Maps ganadores: activos + bump de pool_winner_count para
            //   penalizarlos en el tiebreaker de la SIGUIENTE votacion.
            // - Resto: inactivos.
            Map::query()->update(['is_active' => false]);
            Map::where('is_fixed_in_pool', true)->update(['is_active' => true]);
            if (! empty($winnerIds)) {
                Map::whereIn('id', $winnerIds)->update(['is_active' => true]);
                Map::whereIn('id', $winnerIds)->increment('pool_winner_count');
            }

            $this->update([
                'status'       => self::STATUS_CLOSED,
                'applied_at'   => now(),
                'winners_json' => $winnerIds,
            ]);

            return $winnerIds;
        });
    }

    /**
     * IDs de maps que ganaron la votacion CLOSED mas reciente. Lo usamos al
     * crear la siguiente votacion: por default los excluimos de los
     * candidatos (regla "no repetir consecutivos") salvo override admin.
     */
    public static function lastWinnerIds(): array
    {
        $last = static::where('status', self::STATUS_CLOSED)
            ->whereNotNull('applied_at')
            ->orderByDesc('applied_at')
            ->first();

        return $last?->winners_json ?? [];
    }
}
