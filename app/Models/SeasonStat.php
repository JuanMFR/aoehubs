<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonStat extends Model
{
    protected $fillable = [
        'user_id',
        'season_id',
        'final_rating',
        'final_rd',
        'peak_rating',
        'final_rank',
        'matches_played',
        'matches_won',
    ];

    protected function casts(): array
    {
        return [
            'final_rating'   => 'float',
            'final_rd'       => 'float',
            'peak_rating'    => 'float',
            'final_rank'     => 'integer',
            'matches_played' => 'integer',
            'matches_won'    => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
