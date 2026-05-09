<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $fillable = [
        'name',
        'name_es',
        'name_en',
        'icon_path',
        'rms_map_id',
        'rms_filename',
        'rms_hash',
        'is_custom',
        'is_fixed_in_pool',
        'pool_winner_count',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'is_custom'         => 'boolean',
            'is_fixed_in_pool'  => 'boolean',
            'rms_map_id'        => 'integer',
            'sort_order'        => 'integer',
            'pool_winner_count' => 'integer',
        ];
    }

    /** Scope: solo mapas activos. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Scope: ordenados como el admin los configuro. */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Pool actual de mapas para el draft. Retorna array de strings con los
     * nombres canonicos. Reemplaza Matchmaking::MAP_POOL (const).
     *
     * Cacheado por request — el admin no cambia el pool muy seguido y
     * mas de un draft puede consultarlo en la misma request.
     */
    public static function activeNames(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        return $cache = static::active()->ordered()->pluck('name')->toArray();
    }
}
