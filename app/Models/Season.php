<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CLOSED   = 'closed';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'starts_at',
        'ends_at',
        'closed_at',
        'reset_config_json',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'         => 'datetime',
            'ends_at'           => 'datetime',
            'closed_at'         => 'datetime',
            'reset_config_json' => 'array',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'season_id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(SeasonStat::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(UserAward::class);
    }

    /**
     * La season activa, si existe. Solo puede haber una a la vez (constraint
     * de aplicacion, no de DB).
     */
    public static function current(): ?self
    {
        return static::where('status', self::STATUS_ACTIVE)->first();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Segundos restantes hasta `ends_at`. Negativo si ya paso. NULL si no
     * hay fecha planificada.
     */
    public function secondsUntilEnd(): ?int
    {
        if ($this->ends_at === null) return null;
        return (int) now()->diffInSeconds($this->ends_at, false);
    }
}
