<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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

    /**
     * Nombre a mostrar — prioriza persona_name (vino del Steam Web API), y si
     * no esta disponible cae a "Player {ultimos 6 chars del SteamID64}".
     * Evita los "Bienvenido, jugador" feos cuando STEAM_API_KEY no esta
     * configurado o el refresh aun no corrio.
     */
    public function displayName(): string
    {
        if (! empty($this->persona_name)) return $this->persona_name;
        if ($this->isBot()) return 'Bot Dev';
        return 'Player ' . substr($this->steam_id ?? '', -6);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(UserAward::class);
    }

    public function seasonStats(): HasMany
    {
        return $this->hasMany(SeasonStat::class);
    }

    /**
     * Helper centralizado: cuenta wins/losses/totales del user contra matches
     * completed. Si pasas $season, se restringe a esa season.
     *
     * Hace UN SOLO query con SUM(CASE...). Antes habia 4 controllers
     * haciendo 2-4 queries cada uno con SQL ligeramente distinto.
     *
     * Returns: ['wins' => int, 'losses' => int, 'played' => int, 'win_rate' => int]
     */
    public function winLossStats(?Season $season = null): array
    {
        $q = DB::table('matches')
            ->where('status', GameMatch::STATUS_COMPLETED)
            ->whereNotNull('winner_user_id')
            ->where(function ($w) {
                $w->where('host_user_id', $this->id)
                  ->orWhere('opponent_user_id', $this->id);
            });

        if ($season) $q->where('season_id', $season->id);

        $row = $q->selectRaw(
            'COUNT(*) as played, SUM(CASE WHEN winner_user_id = ? THEN 1 ELSE 0 END) as wins',
            [$this->id]
        )->first();

        $played = (int) ($row->played ?? 0);
        $wins   = (int) ($row->wins   ?? 0);
        $losses = $played - $wins;

        return [
            'played'   => $played,
            'wins'     => $wins,
            'losses'   => $losses,
            'win_rate' => $played > 0 ? (int) round($wins / $played * 100) : 0,
        ];
    }

    /**
     * Match activa (drafting/pending/in_progress) del user, si la hay.
     * Usado en dashboard CTA + queue status — antes duplicado en 4 lugares.
     */
    public function activeMatch(): ?GameMatch
    {
        return GameMatch::with(['host', 'opponent'])
            ->where(function ($q) {
                $q->where('host_user_id', $this->id)
                  ->orWhere('opponent_user_id', $this->id);
            })
            ->whereIn('status', [
                GameMatch::STATUS_DRAFTING,
                GameMatch::STATUS_PENDING,
                GameMatch::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * El companion del user esta corriendo y pingeo en el ultimo minuto y medio.
     * Usado para gatear queue.join y mostrar el estado en dashboard.
     */
    public function companionAlive(): bool
    {
        $token = $this->tokens()->where('name', 'companion')->latest()->first();
        return $token
            && $token->last_used_at
            && $token->last_used_at->diffInSeconds(now()) < 90;
    }
}
