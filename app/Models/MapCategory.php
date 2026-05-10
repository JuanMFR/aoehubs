<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Categoria de mapas (ej. "Cerrados", "Agua", "Open"). Cada mapa puede
 * pertenecer a 0..N categorias. Cada categoria implica una leaderboard
 * propia, derivada de user_category_ratings.
 *
 * El admin gestiona el catalogo desde /admin/maps/categories.
 */
class MapCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Mapas que pertenecen a esta categoria. */
    public function maps(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, 'map_category', 'category_id', 'map_id');
    }

    /** Solo categorias activas. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
