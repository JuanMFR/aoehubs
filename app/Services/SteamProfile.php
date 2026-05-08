<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trae datos publicos de un user de Steam usando la Steam Web API.
 * Endpoint: ISteamUser/GetPlayerSummaries (key requerida).
 *
 * Comportamiento si no hay API key configurada (STEAM_API_KEY en .env):
 *   no rompe nada — simplemente skipea el enrichment y los users quedan
 *   con persona_name=null/avatar_url=null. La web los muestra como '—' o
 *   por SteamID truncado.
 *
 * Frecuencia: solo refrescamos si pasaron mas de PROFILE_TTL_HOURS desde
 * el ultimo update. Steam pone rate limits razonables pero buena vecindad
 * es no spamear sus endpoints.
 */
class SteamProfile
{
    private const ENDPOINT       = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/';
    private const PROFILE_TTL_HR = 24;

    /**
     * Refresca persona_name + avatar_url del user si hace falta.
     * No-op si no hay STEAM_API_KEY o si los datos son recientes.
     */
    public static function refresh(User $user, bool $force = false): void
    {
        $key = config('services.steam.api_key');
        if (empty($key)) return;

        if (! $force && $user->persona_name !== null && $user->updated_at?->gt(now()->subHours(self::PROFILE_TTL_HR))) {
            return;
        }

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, [
                'key'      => $key,
                'steamids' => $user->steam_id,
            ]);

            if (! $response->successful()) {
                Log::warning("Steam API GetPlayerSummaries → {$response->status()}", ['steam_id' => $user->steam_id]);
                return;
            }

            $players = $response->json('response.players') ?? [];
            $player  = $players[0] ?? null;
            if ($player === null) return;

            $user->update([
                'persona_name' => $player['personaname'] ?? $user->persona_name,
                'avatar_url'   => $player['avatarfull']  ?? $user->avatar_url,
            ]);
        } catch (\Throwable $e) {
            Log::error("Steam API error: {$e->getMessage()}", ['steam_id' => $user->steam_id]);
        }
    }
}
