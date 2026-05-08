<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapDraft extends Model
{
    protected $fillable = [
        'match_id',
        'starting_user_id',
        'bans_json',
        'final_map',
    ];

    protected function casts(): array
    {
        return [
            'bans_json' => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function startingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'starting_user_id');
    }
}
