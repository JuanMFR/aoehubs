<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAward extends Model
{
    public const TIER_BRONZE    = 1;
    public const TIER_SILVER    = 2;
    public const TIER_GOLD      = 3;
    public const TIER_PLATINUM  = 4;
    public const TIER_PRISMATIC = 5;

    public const TIER_NAMES = [
        self::TIER_BRONZE    => 'Bronze',
        self::TIER_SILVER    => 'Silver',
        self::TIER_GOLD      => 'Gold',
        self::TIER_PLATINUM  => 'Platinum',
        self::TIER_PRISMATIC => 'Prismatic',
    ];

    // Awards son inmutables: no tiene sentido updated_at, y `awarded_at`
    // ya cumple el rol de "cuando se otorgo".
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'season_id',
        'award_code',
        'tier',
        'awarded_at',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'tier'          => 'integer',
            'awarded_at'    => 'datetime',
            'metadata_json' => 'array',
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

    public function tierName(): string
    {
        return self::TIER_NAMES[$this->tier] ?? 'Unknown';
    }

    /**
     * Definicion del award desde config/awards.php. Devuelve null si el
     * award_code no esta registrado (caso defensivo, no deberia pasar).
     */
    public function definition(): ?array
    {
        return config("awards.{$this->award_code}");
    }
}
