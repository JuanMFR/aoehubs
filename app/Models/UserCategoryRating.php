<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rating Glicko-2 de un user en una categoria especifica de mapa. Analogo
 * a las columnas users.rating/rating_deviation/rating_volatility, pero
 * por categoria.
 *
 * Se crea lazy: la primera vez que el user juega un match en un mapa de
 * la categoria. Defaults son los Glicko-2 estandar (1500/350/0.06).
 *
 * Lo escribe `GameMatch::applyRatingChange` cuando termina un match —
 * para cada categoria del mapa jugado, corre Glicko-2 sobre los ratings
 * categoria de host y opponent y persiste.
 */
class UserCategoryRating extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'rating',
        'rating_deviation',
        'rating_volatility',
        'matches_played',
    ];

    protected function casts(): array
    {
        return [
            'rating'            => 'float',
            'rating_deviation'  => 'float',
            'rating_volatility' => 'float',
            'matches_played'    => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MapCategory::class, 'category_id');
    }
}
