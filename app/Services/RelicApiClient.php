<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para la API community de Worlds Edge / Relic Link — la fuente
 * de datos en vivo de AoE2 DE (lobby browser, leaderboards, profiles).
 *
 * Endpoints usados:
 *   - findAdvertisements: lobbies activos (gente esperando + en juego)
 *     Publico, sin auth. Cacheable 10s.
 *   - getPersonalStat: stats de profile_ids (alias, rating, country)
 *     Publico, sin auth. Cacheable por-profile 5min (alias/rating
 *     no cambian seguido y se reusan entre refreshes).
 *
 * Caveats:
 *   - findObservableAdvertisements (games en curso spectables) requiere
 *     sessionID via login Steam — no es viable desde web. Usamos el
 *     filter `isobservable=1` sobre findAdvertisements como aproximacion.
 *   - El payload trae blobs codificados (`options`, `slotinfo`) con
 *     detalles de civs/settings. Parsearlos requiere libs especializadas;
 *     v1 ignora esos campos y muestra solo lo que viene plain.
 *   - Sin auth no hay rate limit duro documentado pero la comunidad
 *     recomienda <= 1 req/s. Cacheamos agresivo.
 *   - Si la API cae o tarda > 5s, caemos a un array vacio + log warning.
 *     El controller decide como mostrar el error al user.
 */
class RelicApiClient
{
    private const BASE_URL          = 'https://aoe-api.worldsedgelink.com';
    private const HTTP_TIMEOUT_SEC  = 5;
    private const ADS_CACHE_SEC     = 10;
    private const STAT_CACHE_SEC    = 300;  // 5 min

    /**
     * Lista de lobbies activos. Cada item incluye:
     *   id, description (lobby name), mapname, host_profile_id,
     *   matchmembers[], maxplayers, isobservable, observernum,
     *   relayserver_region, state, passwordprotected.
     *
     * @return array Lista de matches; vacio si la API fallo.
     */
    public function findAdvertisements(): array
    {
        return Cache::remember('relic:ads', self::ADS_CACHE_SEC, function () {
            try {
                $resp = Http::timeout(self::HTTP_TIMEOUT_SEC)
                    ->withHeaders(['User-Agent' => 'AoEHubs/1.0 (+platform)'])
                    ->get(self::BASE_URL . '/community/advertisement/findAdvertisements', [
                        'title' => 'age2',
                    ]);

                if (! $resp->successful()) {
                    Log::warning("Relic API findAdvertisements HTTP {$resp->status()}");
                    return [];
                }

                $body = $resp->json();
                if (($body['result']['code'] ?? -1) !== 0) {
                    Log::warning('Relic API findAdvertisements result not 0: ' . json_encode($body['result'] ?? []));
                    return [];
                }

                return $body['matches'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('Relic API findAdvertisements failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Resuelve profile_ids → {alias, rating, country}. Cachea por-id
     * (5 min) para minimizar hits a la API entre page reloads.
     *
     * @param  int[] $profileIds
     * @return array<int, array{alias: string, rating: int|null, country: string|null}>
     */
    public function getPersonalStats(array $profileIds): array
    {
        $profileIds = array_values(array_unique(array_filter(
            $profileIds,
            fn ($id) => is_int($id) && $id > 0
        )));

        if (empty($profileIds)) return [];

        $resolved = [];
        $missing  = [];
        foreach ($profileIds as $id) {
            $hit = Cache::get("relic:stat:{$id}");
            if ($hit !== null) {
                $resolved[$id] = $hit;
            } else {
                $missing[] = $id;
            }
        }

        if (! empty($missing)) {
            $fresh = $this->fetchStatsBatch($missing);
            foreach ($fresh as $id => $stat) {
                Cache::put("relic:stat:{$id}", $stat, self::STAT_CACHE_SEC);
                $resolved[$id] = $stat;
            }
        }

        return $resolved;
    }

    /**
     * Hit a /community/leaderboard/getPersonalStat con un batch de
     * profile_ids. Devuelve [profile_id => stat]. Los IDs sin respuesta
     * (cuenta privada, baneada, no existe) NO aparecen en la respuesta.
     */
    private function fetchStatsBatch(array $profileIds): array
    {
        try {
            $resp = Http::timeout(self::HTTP_TIMEOUT_SEC)
                ->withHeaders(['User-Agent' => 'AoEHubs/1.0 (+platform)'])
                ->get(self::BASE_URL . '/community/leaderboard/getPersonalStat', [
                    'title'       => 'age2',
                    'profile_ids' => '[' . implode(',', $profileIds) . ']',
                ]);

            if (! $resp->successful()) {
                Log::warning("Relic getPersonalStat HTTP {$resp->status()}");
                return [];
            }

            $body = $resp->json();
            if (($body['result']['code'] ?? -1) !== 0) {
                return [];
            }

            // El payload viene en `statGroups` (con datos de leaderboard) y
            // `leaderboardStats` (los ratings). El alias canonical esta en
            // statGroups[].members[0]. Lo merge en una sola estructura por
            // profile_id.
            $stats = [];
            foreach ($body['statGroups'] ?? [] as $group) {
                $member = $group['members'][0] ?? null;
                if (! $member || ! isset($member['profile_id'])) continue;
                $pid = (int) $member['profile_id'];
                $stats[$pid] = [
                    'alias'   => $member['alias']   ?? null,
                    'country' => $member['country'] ?? null,
                    'rating'  => null,  // populated below
                ];
            }

            // El rating queda en leaderboardStats. Tomamos el de leaderboard
            // 1v1 RM (leaderboard_id=3) como default. Si no esta, fallback al
            // mas alto que tenga.
            foreach ($body['leaderboardStats'] ?? [] as $lbStat) {
                $statgroupId = $lbStat['statgroup_id'] ?? null;
                if ($statgroupId === null) continue;
                // Map statgroup_id back to profile_id via statGroups
                foreach ($body['statGroups'] ?? [] as $group) {
                    if (($group['id'] ?? null) === $statgroupId) {
                        $member = $group['members'][0] ?? null;
                        if ($member && isset($member['profile_id'])) {
                            $pid = (int) $member['profile_id'];
                            // Preferir leaderboard_id=3 (1v1 RM); sino el primer rating decente
                            $isPreferred = ($lbStat['leaderboard_id'] ?? null) === 3;
                            $existing = $stats[$pid]['rating'] ?? null;
                            if ($isPreferred || $existing === null) {
                                $stats[$pid]['rating'] = (int) ($lbStat['rating'] ?? 0) ?: null;
                            }
                        }
                    }
                }
            }

            return $stats;
        } catch (\Throwable $e) {
            Log::warning('Relic getPersonalStat batch failed: ' . $e->getMessage());
            return [];
        }
    }
}
