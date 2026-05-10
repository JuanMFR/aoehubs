<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Map;
use App\Models\MapCategory;
use App\Services\RelicApiClient;
use Illuminate\Http\Request;

/**
 * Listado publico de matches en curso — estilo aoe2recs.com/live.
 * Visible sin login para que se pueda compartir el link a streamers,
 * casters, comunidad.
 *
 * Dos sources:
 *   - source=platform (default): matches de la plataforma (status=in_progress).
 *     Filtros: q (player), map, category.
 *   - source=global: lobbies/games globales de AoE2 DE via Relic API.
 *     Filtra por defecto a isobservable=1 (los que se pueden spectear),
 *     enriquece host + matchmembers con alias + rating via getPersonalStat.
 *     Filtros: q (mapname o description).
 *
 * Auto-refresh client-side cada 15s. Cache server-side en RelicApiClient
 * (10s para findAdvertisements, 5min por profile_id en getPersonalStat).
 */
class LiveGamesController extends Controller
{
    public function __construct(private RelicApiClient $relic) {}

    public function index(Request $request)
    {
        $source = $request->query('source') === 'global' ? 'global' : 'platform';

        if ($source === 'global') {
            return $this->indexGlobal($request);
        }

        return $this->indexPlatform($request);
    }

    /** Source=platform: matches de nuestra DB. */
    private function indexPlatform(Request $request)
    {
        $q          = trim((string) $request->query('q', ''));
        $mapFilter  = trim((string) $request->query('map', ''));
        $catSlug    = trim((string) $request->query('category', ''));

        $query = GameMatch::with([
                'host', 'opponent',
                'mapDraft', 'civDraft',
            ])
            ->where('status', GameMatch::STATUS_IN_PROGRESS)
            ->orderByDesc('started_at');

        if ($q !== '') {
            // Search por nombre — host o opponent. Usamos LIKE simple
            // (case-insensitive en MySQL utf8mb4_unicode_ci default).
            $query->where(function ($w) use ($q) {
                $w->whereHas('host',     fn ($qq) => $qq->where('persona_name', 'like', "%{$q}%"))
                  ->orWhereHas('opponent', fn ($qq) => $qq->where('persona_name', 'like', "%{$q}%"));
            });
        }

        if ($mapFilter !== '') {
            // Filter por nombre canonical del mapa via mapDraft.final_map.
            $query->whereHas('mapDraft', fn ($qq) => $qq->where('final_map', $mapFilter));
        }

        $activeCategory = $catSlug !== ''
            ? MapCategory::where('slug', $catSlug)->first()
            : null;

        if ($activeCategory !== null) {
            // Filter por categoria: el mapa del draft tiene que pertenecer
            // a esa cat. mapDrafts.final_map es string, lo joinamos contra
            // maps.name + map_category pivot via whereExists.
            $query->whereExists(function ($sub) use ($activeCategory) {
                $sub->from('map_drafts')
                    ->join('maps', 'maps.name', '=', 'map_drafts.final_map')
                    ->join('map_category', 'map_category.map_id', '=', 'maps.id')
                    ->whereColumn('map_drafts.match_id', 'matches.id')
                    ->where('map_category.category_id', $activeCategory->id);
            });
        }

        $matches = $query->limit(100)->get();

        // Para los dropdowns: solo opciones con al menos 1 match in_progress
        // (sino el dropdown se llena con mapas que nadie esta jugando).
        $playedMapNames = GameMatch::with('mapDraft')
            ->where('status', GameMatch::STATUS_IN_PROGRESS)
            ->get()
            ->pluck('mapDraft.final_map')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $allCategories = MapCategory::active()->ordered()->get();
        $source = 'platform';

        return view('live', compact(
            'source', 'matches', 'q', 'mapFilter', 'catSlug', 'activeCategory',
            'playedMapNames', 'allCategories',
        ));
    }

    /**
     * Presets validos para el filtro de ELO minimo (rating del host).
     * 0 = sin filtro. Default 2000 (top-tier solamente).
     */
    private const ELO_PRESETS = [0, 1500, 1800, 2000, 2200];

    /**
     * Source=global: lobbies de la API de Relic, enriquecidos con stats
     * de los profile_ids del host + matchmembers.
     *
     * Filtros aplicables a global (mas limitados que platform — la API
     * no expone categorias ni nuestro pool de mapas):
     *   - q           → LIKE en mapname OR description (lobby name)
     *   - observable  → '1' (default) muestra solo isobservable=1.
     *                   '0' o ausente: cualquiera. El user toggle.
     *   - elo_min     → 0/1500/1800/2000/2200. Default 2000. Filtra
     *                   lobbies donde el host tenga rating < threshold.
     *                   Cualquier valor fuera de presets cae a default.
     *
     * Pipeline:
     *   1. Fetch ads (cache 10s).
     *   2. Filter cheap (q + observable, sin stats).
     *   3. Resolver stats SOLO para los que sobreviven (no desperdiciar
     *      hits a la API en lobbies que se van a filtrar).
     *   4. Filter por elo_min usando stats.
     *   5. Slice a 60 para limitar UI.
     */
    private function indexGlobal(Request $request)
    {
        $q       = trim((string) $request->query('q', ''));
        $onlyObs = $request->query('observable', '1') !== '0';

        $eloMin = (int) $request->query('elo_min', 2000);
        if (! in_array($eloMin, self::ELO_PRESETS, true)) {
            $eloMin = 2000;
        }

        $ads = $this->relic->findAdvertisements();

        // (2a) isobservable filter
        if ($onlyObs) {
            $ads = array_values(array_filter($ads, fn ($a) => ($a['isobservable'] ?? 0) === 1));
        }

        // (2b) search filter
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $ads = array_values(array_filter($ads, function ($a) use ($needle) {
                $hay = mb_strtolower(($a['mapname'] ?? '') . ' ' . ($a['description'] ?? ''));
                return mb_strpos($hay, $needle) !== false;
            }));
        }

        // (3) recolectar profile_ids de los ads sobrevivientes y resolver stats.
        $profileIds = [];
        foreach ($ads as $ad) {
            if (! empty($ad['host_profile_id'])) $profileIds[] = (int) $ad['host_profile_id'];
            foreach ($ad['matchmembers'] ?? [] as $m) {
                if (! empty($m['profile_id'])) $profileIds[] = (int) $m['profile_id'];
            }
        }
        $stats = $this->relic->getPersonalStats($profileIds);

        // (4) elo_min filter sobre el rating del host. Lobbies sin stats
        // resueltos para el host (profile privado, baneado, sin matches en
        // 1v1) quedan fuera cuando hay threshold > 0 — son indistinguibles
        // de "rating bajo" desde nuestro lado.
        if ($eloMin > 0) {
            $ads = array_values(array_filter($ads, function ($ad) use ($stats, $eloMin) {
                $hostId = $ad['host_profile_id'] ?? null;
                if (! $hostId) return false;
                $rating = $stats[$hostId]['rating'] ?? null;
                return $rating !== null && $rating >= $eloMin;
            }));
        }

        // (5) slice 60 ads
        $ads = array_slice($ads, 0, 60);

        $source = 'global';
        $apiOk  = $this->relic->findAdvertisements() !== [] || count($ads) > 0;
        $eloPresets = self::ELO_PRESETS;

        return view('live', compact(
            'source', 'ads', 'stats', 'q', 'onlyObs', 'eloMin', 'eloPresets', 'apiOk',
        ));
    }
}
