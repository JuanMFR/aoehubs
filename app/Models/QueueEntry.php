<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    protected $fillable = ['user_id', 'is_bot', 'joined_at'];

    protected function casts(): array
    {
        return [
            'is_bot'    => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
