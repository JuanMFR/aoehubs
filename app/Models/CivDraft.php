<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CivDraft extends Model
{
    public const PHASE_PICKING    = 'picking';
    public const PHASE_BANNING    = 'banning';
    public const PHASE_FINALIZING = 'finalizing';
    public const PHASE_COMPLETED  = 'completed';

    protected $fillable = [
        'match_id',
        'host_picks_json',
        'opponent_picks_json',
        'host_bans_json',
        'opponent_bans_json',
        'host_final_civ',
        'opponent_final_civ',
        'phase',
    ];

    protected function casts(): array
    {
        return [
            'host_picks_json'     => 'array',
            'opponent_picks_json' => 'array',
            'host_bans_json'      => 'array',
            'opponent_bans_json'  => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
