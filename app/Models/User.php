<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public const ROLE_PLAYER = 'player';
    public const ROLE_ADMIN  = 'admin';

    public const ROLES = [self::ROLE_PLAYER, self::ROLE_ADMIN];

    public const BOT_STEAM_ID = 'BOTDEV_PERMANENT_QUEUE';

    protected $fillable = [
        'steam_id',
        'persona_name',
        'avatar_url',
        'role',
        'rating',
        'rating_deviation',
        'rating_volatility',
        'pings_json',
        'pings_updated_at',
        'cooldown_until',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'rating'            => 'float',
            'rating_deviation'  => 'float',
            'rating_volatility' => 'float',
            'pings_json'        => 'array',
            'pings_updated_at'  => 'datetime',
            'cooldown_until'    => 'datetime',
        ];
    }

    public function isInCooldown(): bool
    {
        return $this->cooldown_until !== null && $this->cooldown_until->isFuture();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isBot(): bool
    {
        return $this->steam_id === self::BOT_STEAM_ID;
    }
}
