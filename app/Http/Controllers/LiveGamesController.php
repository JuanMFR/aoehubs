<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Map;
use App\Models\MapCategory;
use Illuminate\Http\Request;

/**
 * Listado publico de matches en curso (status=in_progress) — estilo
 * aoe2recs.com/live. Visible sin login para que se pueda compartir el
 * link a streamers, casters, comunidad.
 *
 * Filtros (todos opcionales, GET query):
 *   - q          → search por persona_name del host o opponent (LIKE %X%)
 *   - map        → filtra por Map::name
 *   - category   → filtra por MapCategory::slug (matches en mapas de esa cat)
 *
 * Auto-refresh client-side cada 15s — el companion alimenta los matches
 * y status_changes via API normal, no hay nada server-push aca.
 */
class LiveGamesController extends Controller
{
    public function index(Request $request)
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

        return view('live', compact(
            'matches', 'q', 'mapFilter', 'catSlug', 'activeCategory',
            'playedMapNames', 'allCategories',
        ));
    }
}
