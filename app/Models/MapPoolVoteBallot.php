<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voto de un user en una eleccion de pool de mapas. `votes_json` es array
 * de map_ids — entre 1 y `vote.pool_size_voted` items, todos miembros de
 * los candidatos. La constraint unique(vote_id, user_id) garantiza 1 sola
 * fila por user/voto; el user puede sobrescribir mientras vote.status=open.
 */
class MapPoolVoteBallot extends Model
{
    protected $fillable = [
        'vote_id',
        'user_id',
        'votes_json',
    ];

    protected function casts(): array
    {
        return [
            'votes_json' => 'array',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(MapPoolVote::class, 'vote_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
