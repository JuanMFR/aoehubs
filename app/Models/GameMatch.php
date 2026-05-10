<?php

namespace App\Models;

use App\Services\Glicko2;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

// Lo nombramos GameMatch (no Match) porque "match" es palabra reservada en PHP 8+
// y trae problemas con expresiones tipo match(...). La tabla se llama 'matches'.
class GameMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'season_id',
        'host_user_id',
        'opponent_user_id',
        'config_json',
        'lobby_id',
        'status',
        'started_at',
        'host_heartbeat_at',
        'opponent_heartbeat_at',
        'winner_user_id',
        'replay_filename',
        'replay_size',
        'replay_path',
        'replay_uploaded_by',
        'parsed_metadata',
        'validation_errors',
        'parsed_at',
        'host_rating_before',
        'host_rating_change',
        'opponent_rating_before',
        'opponent_rating_change',
    ];

    protected function casts(): array
    {
        return [
            'config_json'            => 'array',
            'replay_size'            => 'integer',
            'started_at'             => 'datetime',
            'host_heartbeat_at'      => 'datetime',
            'opponent_heartbeat_at'  => 'datetime',
            'parsed_metadata'        => 'array',
            'validation_errors'      => 'array',
            'parsed_at'              => 'datetime',
            'host_rating_before'     => 'float',
            'host_rating_change'     => 'float',
            'opponent_rating_before' => 'float',
            'opponent_rating_change' => 'float',
        ];
    }

    // Estados del ciclo de vida de un match:
    //   drafting           → map/civ drafts en curso
    //   pending            → drafts done; el host tiene que armar el lobby
    //   in_progress        → el companion vio el .aoe2record creado
    //   pending_validation → replay subido pero el parser no pudo leerlo
    //                        (típicamente porque mgz está atrasado vs el último
    //                        patch de DE). Sin rating aplicado. Reintenta el
    //                        cron `matches:reprocess-pending`.
    //   completed          → parseado, validado, Glicko-2 aplicado. Final.
    //   invalid            → parseado pero falló validación (mods, settings
    //                        cambiados, civs distintas al draft, etc). Sin
    //                        rating. Final — no se reintenta.
    //   abandoned          → lobby cerrado sin jugar, timeout/heartbeat, o
    //                        companion reportó salida al menú. Sin rating.
    public const STATUS_DRAFTING           = 'drafting';
    public const STATUS_PENDING            = 'pending';
    public const STATUS_IN_PROGRESS        = 'in_progress';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_COMPLETED          = 'completed';
    public const STATUS_INVALID            = 'invalid';
    public const STATUS_ABANDONED          = 'abandoned';

    /** Lista canónica para iterar (filtros admin, validation, etc.). */
    public const STATUSES = [
        self::STATUS_DRAFTING,
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_PENDING_VALIDATION,
        self::STATUS_INVALID,
        self::STATUS_ABANDONED,
    ];

    /**
     * Auto-asigna la season activa al crear un match si no se paso una
     * explicita. Asi no hay que tocar todos los call-sites de Matchmaking.
     * Si no hay season activa (ej. entre el cierre de S1 y la apertura de
     * S2), el match queda con season_id=null hasta que el admin asocie
     * manualmente o cree la nueva season.
     */
    protected static function booted(): void
    {
        static::creating(function (self $match) {
            if ($match->season_id === null) {
                $current = Season::current();
                if ($current !== null) {
                    $match->season_id = $current->id;
                }
            }
        });
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function mapDraft()
    {
        return $this->hasOne(MapDraft::class, 'match_id');
    }

    public function civDraft()
    {
        return $this->hasOne(CivDraft::class, 'match_id');
    }

    /**
     * Aplica el resultado a los ratings: corre Glicko-2 entre host y opponent
     * con `$winnerUserId` como ganador, persiste los ratings nuevos en ambos
     * users, y guarda el snapshot pre-match + el delta en este match.
     *
     * NO setea `status` ni `winner_user_id` — eso lo hace el caller en su
     * propia $match->update(). Este metodo se concentra exclusivamente en la
     * parte de Glicko-2 + persistencia de cambios de rating.
     *
     * Idempotente: si `host_rating_change` ya esta seteado (signal de que
     * Glicko ya corrio para esta match), retorna sin hacer nada. Esto cubre
     * el caso de race entre cron forfeit + replay upload + admin reprocess
     * que podian aplicar Glicko dos veces y corromper ratings.
     *
     * IMPORTANTE: el caller TAMBIEN debe usar lockForUpdate sobre la row
     * del match dentro de una transaction antes de llamar — sino la
     * verificacion de host_rating_change tiene una ventana TOCTOU.
     */
    public function applyRatingChange(int $winnerUserId): void
    {
        // Guard de idempotencia. Si ya aplicamos cambio de rating, no
        // re-aplicamos (corromperia los ratings de los dos users).
        if ($this->host_rating_change !== null) {
            return;
        }

        $host     = $this->host;
        $opponent = $this->opponent;
        $hostWon  = $winnerUserId === $host->id;

        // Atomico: si Glicko global pega pero el de categoria falla, queremos
        // que TODO revierta para que la proxima invocacion reintente limpio.
        // Las nested transactions usan SAVEPOINTs si el caller ya tiene una
        // transaction abierta — no rompe.
        DB::transaction(function () use ($host, $opponent, $hostWon) {
            // 1) Glicko-2 global (existente).
            $hostBefore = $host->rating;
            $oppBefore  = $opponent->rating;

            $update = Glicko2::update(
                $host->rating,     $host->rating_deviation,     $host->rating_volatility,
                $opponent->rating, $opponent->rating_deviation, $opponent->rating_volatility,
                $hostWon,
            );

            $host->update([
                'rating'            => $update['host']['rating'],
                'rating_deviation'  => $update['host']['rd'],
                'rating_volatility' => $update['host']['volatility'],
            ]);
            $opponent->update([
                'rating'            => $update['opponent']['rating'],
                'rating_deviation'  => $update['opponent']['rd'],
                'rating_volatility' => $update['opponent']['volatility'],
            ]);

            $this->update([
                'host_rating_before'     => $hostBefore,
                'host_rating_change'     => $update['host']['rating'] - $hostBefore,
                'opponent_rating_before' => $oppBefore,
                'opponent_rating_change' => $update['opponent']['rating'] - $oppBefore,
            ]);

            // 2) Glicko-2 per-category (nuevo). Si el mapa pertenece a una o
            //    mas categorias, se actualiza el rating de cada una con su
            //    propio RD/volatility — leaderboards independientes, mismo
            //    `$hostWon` (la verdad del match es la misma en todas).
            $this->applyCategoryRatingChanges($hostWon);
        });
    }

    /**
     * Resuelve el mapa via mapDraft.final_map → Map → categories y aplica
     * Glicko-2 al rating en cada categoria para host y opponent. Crea las
     * filas user_category_ratings on-demand con defaults estandar
     * (1500/350/0.06) si no existian.
     *
     * No-op si:
     *   - no hay mapDraft (matches pre-draft o sin mapa resuelto)
     *   - mapDraft.final_map no resuelve a una fila Map (mapa eliminado del
     *     pool)
     *   - el Map no pertenece a ninguna categoria
     */
    private function applyCategoryRatingChanges(bool $hostWon): void
    {
        $mapName = $this->mapDraft?->final_map;
        if (! $mapName) return;

        $map = Map::where('name', $mapName)->with('categories')->first();
        if (! $map || $map->categories->isEmpty()) return;

        foreach ($map->categories as $cat) {
            $hostCat = UserCategoryRating::getOrCreate($this->host_user_id, $cat->id);
            $oppCat  = UserCategoryRating::getOrCreate($this->opponent_user_id, $cat->id);

            $update = Glicko2::update(
                $hostCat->rating, $hostCat->rating_deviation, $hostCat->rating_volatility,
                $oppCat->rating,  $oppCat->rating_deviation,  $oppCat->rating_volatility,
                $hostWon,
            );

            $hostCat->update([
                'rating'            => $update['host']['rating'],
                'rating_deviation'  => $update['host']['rd'],
                'rating_volatility' => $update['host']['volatility'],
                'matches_played'    => $hostCat->matches_played + 1,
            ]);
            $oppCat->update([
                'rating'            => $update['opponent']['rating'],
                'rating_deviation'  => $update['opponent']['rd'],
                'rating_volatility' => $update['opponent']['volatility'],
                'matches_played'    => $oppCat->matches_played + 1,
            ]);
        }
    }
}
