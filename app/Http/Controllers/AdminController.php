<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CompanionApiController;
use App\Models\GameMatch;
use App\Models\Map;
use App\Models\MapCategory;
use App\Models\MapPoolVote;
use App\Models\QueueEntry;
use App\Models\Season;
use App\Models\User;
use App\Services\SeasonService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Panel admin. Protegido por middleware 'admin' en routes/web.php.
 *
 * Acciones disponibles:
 *   - Ver overview con stats globales
 *   - Listar usuarios + cambiar role (promote/demote)
 *   - Listar matches (todos, no solo del logueado) + filtros
 *   - Ver detalle de un match (parsed_metadata, validation_errors)
 *   - Forzar cancel de un match activo (pending/in_progress)
 *   - Reprocesar replay manualmente (re-correr el parser)
 */
class AdminController extends Controller
{
    public function overview()
    {
        $statusCounts = GameMatch::query()
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status')
            ->toArray();

        $queueSize = QueueEntry::where('is_bot', false)->count();

        $recentMatches = GameMatch::with(['host', 'opponent'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $recentUsers = User::orderByDesc('id')->limit(10)->get();

        $userStats = [
            'total'   => User::count(),
            'admins'  => User::where('role', User::ROLE_ADMIN)->count(),
            'players' => User::where('role', User::ROLE_PLAYER)->count(),
        ];

        return view('admin.overview', compact('statusCounts', 'queueSize', 'recentMatches', 'recentUsers', 'userStats'));
    }

    public function users(Request $request)
    {
        $q = $request->input('q');

        $users = User::query()
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('persona_name', 'like', "%{$q}%")
                  ->orWhere('steam_id', 'like', "%{$q}%");
            }))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.users', compact('users', 'q'));
    }

    public function promoteUser(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'in:player,admin'],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== User::ROLE_ADMIN) {
            return back()->with('error', 'No podés sacarte el rol de admin a vos mismo.');
        }

        $user->update(['role' => $data['role']]);

        return back()->with('flash', "Role de '{$user->persona_name}' cambiado a {$data['role']}.");
    }

    public function matches(Request $request)
    {
        $status = $request->input('status');

        $matches = GameMatch::query()
            ->with(['host', 'opponent', 'winner'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $statuses = GameMatch::STATUSES;

        return view('admin.matches', compact('matches', 'status', 'statuses'));
    }

    public function matchDetail(GameMatch $match)
    {
        $match->load(['host', 'opponent', 'winner', 'mapDraft', 'civDraft']);
        return view('admin.match-detail', compact('match'));
    }

    public function forceCancel(GameMatch $match)
    {
        if (! in_array($match->status, [GameMatch::STATUS_PENDING, GameMatch::STATUS_IN_PROGRESS, GameMatch::STATUS_DRAFTING], true)) {
            return back()->with('error', "Solo se pueden cancelar matches activos. Status actual: {$match->status}");
        }

        $match->update(['status' => GameMatch::STATUS_ABANDONED]);

        return back()->with('flash', "Match #{$match->id} marcado como abandoned.");
    }

    public function reprocess(GameMatch $match)
    {
        if ($match->status !== GameMatch::STATUS_PENDING_VALIDATION) {
            return back()->with('error', "Solo se reprocesan matches en pending_validation. Status actual: {$match->status}");
        }
        if (! $match->replay_path) {
            return back()->with('error', 'Match sin replay_path; no hay archivo a reprocesar.');
        }

        $absolutePath = Storage::disk('local')->path($match->replay_path);
        if (! file_exists($absolutePath)) {
            return back()->with('error', "El archivo del replay no existe en disco: {$match->replay_path}");
        }

        $resolution = CompanionApiController::resolveReplay($match, $absolutePath);

        // Lock + re-check: si el match transiciono entre el check inicial y
        // aca (cron forfeit u otro upload), no pisamos su estado.
        DB::transaction(function () use ($match, $resolution) {
            $fresh = GameMatch::where('id', $match->id)->lockForUpdate()->first();
            if ($fresh === null || $fresh->status !== GameMatch::STATUS_PENDING_VALIDATION) {
                return;
            }
            $fresh->update($resolution['updates']);
            if ($resolution['ratingApplied']) {
                $fresh->loadMissing(['host', 'opponent'])->applyRatingChange($resolution['winnerUserId']);
            }
        });

        $newStatus = $resolution['updates']['status'];
        return back()->with('flash', "Match #{$match->id} reprocesada → {$newStatus}.");
    }

    /**
     * Lista de seasons + UI de gestion (editar ends_at + boton cerrar+abrir
     * la siguiente). Las seasons cerradas se ven en read-only abajo del todo.
     */
    public function seasons()
    {
        $current  = Season::current();
        $upcoming = Season::where('status', Season::STATUS_UPCOMING)->orderBy('id')->get();
        $closed   = Season::where('status', Season::STATUS_CLOSED)->orderByDesc('id')->limit(20)->get();

        $userCount = User::where('steam_id', '!=', User::BOT_STEAM_ID)->count();
        $matchCount = $current ? $current->matches()->where('status', GameMatch::STATUS_COMPLETED)->count() : 0;

        return view('admin.seasons', compact('current', 'upcoming', 'closed', 'userCount', 'matchCount'));
    }

    /**
     * Edita la fecha planificada de fin de la season activa. Solo
     * actualiza informacion — el cierre real lo dispara closeSeason().
     */
    public function updateSeasonEndsAt(Request $request, Season $season)
    {
        $data = $request->validate([
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $endsAt = $data['ends_at'] ? Carbon::parse($data['ends_at'])->endOfDay() : null;
        $season->update(['ends_at' => $endsAt]);

        return back()->with('flash', "Fecha de cierre actualizada: " . ($endsAt?->toDateString() ?? 'sin fecha'));
    }

    // ─── Maps CRUD ─────────────────────────────────────────────────────

    public function maps()
    {
        $maps = Map::orderBy('is_active', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Fingerprint incompleto: vanilla sin rms_map_id, o custom sin rms_filename.
        // El validator cae a fallback (comparar por nombre) en estos casos —
        // mostramos un banner para alentar al admin a completarlos.
        $incomplete = $maps->filter(fn ($m) =>
            (! $m->is_custom && $m->rms_map_id === null)
            || ($m->is_custom && empty($m->rms_filename))
        );

        $iconBaseDir = public_path('images/maps');
        $allCategories = MapCategory::active()->ordered()->get();
        $maps->load('categories');  // eager para mostrar chips en la tabla
        return view('admin.maps', compact('maps', 'iconBaseDir', 'incomplete', 'allCategories'));
    }

    public function storeMap(Request $request)
    {
        $data = $this->validateMapFields($request);

        // Garantia minima: vanilla necesita rms_map_id, custom necesita rms_filename.
        // Sin esto la validacion de matches no podria identificar el mapa.
        if (! ($data['is_custom'] ?? false) && empty($data['rms_map_id'])) {
            return back()->withErrors([
                'rms_map_id' => 'Mapa vanilla: rms_map_id es obligatorio (subi un replay para auto-detectarlo).',
            ])->withInput();
        }
        if (($data['is_custom'] ?? false) && empty($data['rms_filename'])) {
            return back()->withErrors([
                'rms_filename' => 'Mapa custom: rms_filename es obligatorio (es el nombre del archivo .rms).',
            ])->withInput();
        }

        $map = Map::create([
            'name'             => $data['name'],
            'name_es'          => $data['name_es']          ?? $data['name'],
            'name_en'          => $data['name_en']          ?? $data['name'],
            'icon_path'        => $data['icon_path']        ?? null,
            'rms_map_id'       => $data['rms_map_id']       ?? null,
            'rms_filename'     => $data['rms_filename']     ?? null,
            'rms_hash'         => $data['rms_hash']         ?? null,
            'is_custom'        => $data['is_custom']        ?? false,
            'is_fixed_in_pool' => $data['is_fixed_in_pool'] ?? false,
            'sort_order'       => $data['sort_order']       ?? 999,
            'is_active'        => $data['is_active']        ?? true,
        ]);

        // Sync de categorias (M2M). Si no se mando ningun checkbox, sync con
        // array vacio = des-asocia todas las categorias previas.
        $map->categories()->sync($data['category_ids'] ?? []);

        return back()->with('flash', "Mapa '{$data['name']}' agregado al pool.");
    }

    public function updateMap(Request $request, Map $map)
    {
        $data = $this->validateMapFields($request, $map);
        $catIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);  // no es columna de la tabla, sync aparte
        $map->update($data);
        $map->categories()->sync($catIds);
        return back()->with('flash', "Mapa '{$map->name}' actualizado.");
    }

    /**
     * Validacion compartida entre store y update. Si $map se pasa, la regla
     * unique:name ignora la fila propia.
     */
    private function validateMapFields(Request $request, ?Map $map = null): array
    {
        $uniqueName = 'unique:maps,name' . ($map ? ',' . $map->id : '');
        return $request->validate([
            'name'             => ['required', 'string', 'max:60', $uniqueName],
            'name_es'          => ['nullable', 'string', 'max:60'],
            'name_en'          => ['nullable', 'string', 'max:60'],
            'icon_path'        => ['nullable', 'string', 'max:255'],
            'rms_map_id'       => ['nullable', 'integer', 'min:0'],
            'rms_filename'     => ['nullable', 'string', 'max:120'],
            'rms_hash'         => ['nullable', 'string', 'size:64', 'regex:/^[0-9a-f]+$/i'],
            'is_custom'        => ['nullable', 'boolean'],
            'is_fixed_in_pool' => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer'],
            'is_active'        => ['nullable', 'boolean'],
            'category_ids'     => ['nullable', 'array'],
            'category_ids.*'   => ['integer', 'exists:map_categories,id'],
        ]);
    }

    public function toggleMap(Map $map)
    {
        $map->update(['is_active' => ! $map->is_active]);
        $newState = $map->is_active ? 'activado' : 'desactivado';
        return back()->with('flash', "Mapa '{$map->name}' {$newState}.");
    }

    public function destroyMap(Map $map)
    {
        $name = $map->name;
        $map->delete();
        return back()->with('flash', "Mapa '{$name}' eliminado del pool.");
    }

    /**
     * Recibe un replay file via upload, lo parsea con scripts/parse_replay.py
     * y devuelve la metadata relevante (canonical map_name + rms_map_id).
     * El frontend usa esto para pre-poblar el form de crear mapa con los
     * datos extraidos.
     *
     * Devuelve JSON: {map_name, rms_map_id, ok, error?}.
     */
    public function extractMapFromReplay(Request $request)
    {
        $request->validate([
            'replay' => ['required', 'file', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('replay');
        $tempPath = $file->store('temp', 'local');
        $absolute = Storage::disk('local')->path($tempPath);

        try {
            $result = \App\Services\ReplayParser::parse($absolute);

            // ParseResult tiene ok/data/error/type. ok=false significa que
            // mgz fallo (replay corrupta o version desactualizada).
            if (! $result->ok) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Parser fallo (' . $result->type . '): ' . $result->error,
                ], 422);
            }

            $data    = $result->data ?? [];
            $mapName = $data['map_name']     ?? null;
            $rmsId   = $data['rms_map_id']   ?? null;
            $rmsFile = $data['rms_filename'] ?? null;

            // Caso 1: ni rms_map_id ni map_name. Replay realmente roto.
            if (! $rmsId && ! $mapName) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'No se pudo identificar el mapa (sin rms_map_id ni map_name). '
                              . 'rms_filename=' . ($rmsFile ?? '?'),
                ], 422);
            }

            // Caso 2: tenemos rms_map_id pero map_name es null. mgz-fast no
            // tiene este mapa en su tabla DE_MAP_NAMES (mapa nuevo, beta, o
            // version vieja de mgz). Devolvemos partial — admin completa el
            // canonical name a mano.
            $partial = $mapName === null;
            $suggestedName = $mapName ?? '';
            $slug = $mapName ? strtolower(str_replace(' ', '_', $mapName)) : '';

            // Heuristica para sugerir is_custom: si mgz no conoce el nombre y
            // el rms_filename no parece vanilla (ej. "LP Arena.rms" en lugar de
            // "ARABIA.rms"), probablemente es un mapa de un pack/Workshop.
            // Marcamos `suggest_is_custom=true` para que el admin lo confirme
            // y la UI muestre los campos correctos. El admin tiene la ultima
            // palabra — esto solo guia el form.
            $suggestIsCustom = $partial && $rmsFile !== null
                && ! preg_match('/^[A-Z_]+\.rms$/', $rmsFile);

            return response()->json([
                'ok'                => true,
                'partial'           => $partial,
                'map_name'          => $suggestedName,
                'rms_map_id'        => $rmsId,
                'rms_filename'      => $rmsFile,
                'icon_path'         => $slug ? "maps/{$slug}.png" : '',
                'suggest_is_custom' => $suggestIsCustom,
                'already_exists'    => $mapName ? Map::where('name', $mapName)->exists() : false,
                'partial_message'   => $partial
                    ? "El parser no conoce el nombre del mapa para rms_map_id={$rmsId}. "
                      . "Esto pasa con mapas nuevos del juego que mgz-fast todavia no actualizo. "
                      . "Completá el canonical name a mano (mira en aocref o testealo) — el rms_map_id "
                      . "ya queda asociado al mapa para futuras validaciones."
                    : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'Error parseando replay: ' . $e->getMessage(),
            ], 500);
        } finally {
            Storage::disk('local')->delete($tempPath);
        }
    }

    /**
     * Cierra la season activa y abre la siguiente con los parametros
     * provistos. Es la accion mas destructiva del admin — afecta el
     * rating de todos los users. La UI hace doble confirmacion.
     */
    public function closeSeason(Request $request, Season $season, SeasonService $service)
    {
        if (!$season->isActive()) {
            return back()->with('error', "Season #{$season->id} no esta activa.");
        }

        $data = $request->validate([
            'next_name'   => ['required', 'string', 'max:60'],
            'next_slug'   => ['required', 'string', 'max:40', 'alpha_dash'],
            'next_ends_at'=> ['nullable', 'date', 'after:now'],
            'factor'      => ['required', 'numeric', 'min:0', 'max:1'],
            'base'        => ['required', 'numeric', 'min:500', 'max:3000'],
            'confirm'     => ['accepted'],
        ]);

        $endsAt = $data['next_ends_at'] ? Carbon::parse($data['next_ends_at'])->endOfDay() : null;

        try {
            $next = $service->closeAndStartNext(
                $season,
                $data['next_name'],
                $data['next_slug'],
                $endsAt,
                ['factor' => (float) $data['factor'], 'base' => (float) $data['base']],
            );
        } catch (\Throwable $e) {
            return back()->with('error', "Error cerrando season: " . $e->getMessage());
        }

        return redirect()->route('admin.seasons')
            ->with('flash', "Season #{$season->id} cerrada. Season #{$next->id} '{$next->name}' activa.");
    }

    // ─── Map Pool Votes ────────────────────────────────────────────────

    /**
     * Lista todas las votaciones (open/closed/cancelled) ordenadas por
     * recencia. La form de "crear nueva" se renderiza en la misma vista.
     */
    public function mapVotes()
    {
        $votes = MapPoolVote::orderByDesc('id')->get();

        // Para el form de "crear nueva": maps disponibles como candidatos =
        // todos los no fijos que NO ganaron la votacion anterior. Admin puede
        // overridear con un toggle si por alguna razon quiere repetir.
        $excludeIds = MapPoolVote::lastWinnerIds();
        $availableMaps = Map::where('is_fixed_in_pool', false)
            ->orderBy('name')
            ->get();

        $fixedMaps = Map::where('is_fixed_in_pool', true)
            ->orderBy('name')
            ->get();

        // Es valido empezar una nueva solo si no hay otra abierta. Mostrar
        // banner si hay una en curso.
        $openVote = $votes->firstWhere('status', MapPoolVote::STATUS_OPEN);

        return view('admin.map-votes', compact(
            'votes', 'availableMaps', 'fixedMaps', 'excludeIds', 'openVote'
        ));
    }

    /**
     * Crea una nueva votacion. Validamos:
     *   - No hay otra votacion abierta (1 a la vez)
     *   - Al menos 2 candidatos y pool_size_voted en rango valido
     *   - Todos los candidate_ids existen y NO son is_fixed_in_pool
     *   - ends_at > starts_at + min 1h (evitar votaciones fugaces por error)
     */
    public function storeMapVote(Request $request)
    {
        if (MapPoolVote::where('status', MapPoolVote::STATUS_OPEN)->exists()) {
            return back()->with('error', 'Ya hay una votacion abierta — cancelala o esperá su cierre antes de crear otra.');
        }

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:80'],
            'starts_at'       => ['required', 'date'],
            'ends_at'         => ['required', 'date', 'after:starts_at'],
            'pool_size_voted' => ['required', 'integer', 'min:1', 'max:30'],
            'candidate_ids'   => ['required', 'array', 'min:2'],
            'candidate_ids.*' => ['integer', 'exists:maps,id'],
        ]);

        // Todos los candidatos deben ser non-fixed (los fijos van directo
        // al pool sin votar). Validacion explicita en lugar de filtrar
        // silenciosamente — admin tiene que ver el error.
        $fixedIds = Map::where('is_fixed_in_pool', true)->pluck('id')->all();
        $invalidFixed = array_intersect($data['candidate_ids'], $fixedIds);
        if (! empty($invalidFixed)) {
            return back()->with('error', 'No se pueden votar mapas fijos: ' . implode(', ', $invalidFixed))->withInput();
        }

        // pool_size_voted no puede superar la cantidad de candidatos (sino
        // siempre ganan todos y no hay competencia).
        if ($data['pool_size_voted'] >= count($data['candidate_ids'])) {
            return back()->withErrors([
                'pool_size_voted' => 'pool_size_voted debe ser MENOR que la cantidad de candidatos (sino todos ganan).',
            ])->withInput();
        }

        $vote = DB::transaction(function () use ($data) {
            $vote = MapPoolVote::create([
                'name'            => $data['name'],
                'starts_at'       => $data['starts_at'],
                'ends_at'         => $data['ends_at'],
                'pool_size_voted' => $data['pool_size_voted'],
                'status'          => MapPoolVote::STATUS_OPEN,
            ]);
            $vote->candidates()->attach($data['candidate_ids']);
            return $vote;
        });

        return redirect()->route('admin.map-votes.show', $vote->id)
            ->with('flash', "Votacion '{$vote->name}' creada con " . count($data['candidate_ids']) . " candidatos.");
    }

    /**
     * Detalle: muestra candidatos, tally en vivo (recomputa cada hit), y los
     * ballots agregados. Si la votacion esta closed, mostramos los winners
     * congelados en winners_json (no recomputamos para que coincida con lo
     * aplicado al pool, evitando desfase si despues alguien borra ballots).
     */
    public function showMapVote(MapPoolVote $vote)
    {
        $vote->load(['candidates', 'ballots']);

        if ($vote->status === MapPoolVote::STATUS_CLOSED && ! empty($vote->winners_json)) {
            // Tally historico (winners ya congelados). Calculamos el detalle
            // de votos por candidato igual para mostrar el ranking completo
            // en la tabla de resultados.
            $tally = $vote->tally();
        } else {
            $tally = $vote->tally();
        }

        $totalBallots = $vote->ballots->count();

        return view('admin.map-vote-show', compact('vote', 'tally', 'totalBallots'));
    }

    /**
     * Cancela una votacion abierta. Marca status=cancelled, NO toca el pool.
     * Util para abortar cuando entra un evento pro-pack o un error obvio.
     */
    public function cancelMapVote(MapPoolVote $vote)
    {
        if ($vote->status !== MapPoolVote::STATUS_OPEN) {
            return back()->with('error', "La votacion '{$vote->name}' no esta abierta — no se puede cancelar.");
        }

        $vote->update(['status' => MapPoolVote::STATUS_CANCELLED]);
        return back()->with('flash', "Votacion '{$vote->name}' cancelada — el pool actual queda intacto.");
    }

    /**
     * Force-apply ahora, sin esperar a ends_at. Util para testing y para
     * cuando admin quiere cerrar antes de tiempo (ej extendio la pool y la
     * votacion ya tuvo participacion suficiente).
     */
    public function applyMapVote(MapPoolVote $vote)
    {
        if ($vote->status !== MapPoolVote::STATUS_OPEN) {
            return back()->with('error', "La votacion '{$vote->name}' no esta abierta — no se puede aplicar.");
        }

        $winners = $vote->applyToPool();

        if (empty($winners)) {
            return back()->with('error', "Votacion cerrada SIN votos — pool sin cambios.");
        }

        return back()->with('flash', count($winners) . " ganadores aplicados al pool. Pool actual = " . Map::where('is_active', true)->count() . " mapas activos.");
    }

    // ─── Map Categories (ladders por tipo de mapa) ────────────────────

    /**
     * Lista categorias + form de creacion. Cada categoria representa una
     * leaderboard derivada (ej. "Cerrados", "Agua") con su propio Glicko-2
     * por user. Crear una categoria NO crea tablas/leaderboards nuevas —
     * solo es una fila en map_categories. La leaderboard "se enciende"
     * automatica al primer match en un mapa de esa categoria.
     */
    public function mapCategories()
    {
        $categories = MapCategory::ordered()
            ->withCount('maps')
            ->get();

        return view('admin.map-categories', compact('categories'));
    }

    public function storeMapCategory(Request $request)
    {
        $data = $this->validateCategoryFields($request);
        MapCategory::create($data);
        return back()->with('flash', "Categoría '{$data['name']}' creada.");
    }

    public function updateMapCategory(Request $request, MapCategory $category)
    {
        $data = $this->validateCategoryFields($request, $category);
        $category->update($data);
        return back()->with('flash', "Categoría '{$category->name}' actualizada.");
    }

    public function toggleMapCategory(MapCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        $newState = $category->is_active ? 'activada' : 'desactivada';
        return back()->with('flash', "Categoría '{$category->name}' {$newState}.");
    }

    public function destroyMapCategory(MapCategory $category)
    {
        // FK cascadeOnDelete se encarga del pivot map_category y de las
        // user_category_ratings. Los matches historicos NO se ven afectados
        // (la categoria no se referencia desde matches; el rating delta
        // se aplico ya y vivio en user_category_ratings antes del cascade).
        $name = $category->name;
        $category->delete();
        return back()->with('flash', "Categoría '{$name}' eliminada (junto con sus ratings y asociaciones).");
    }

    /** Validacion compartida store/update con unique-aware. */
    private function validateCategoryFields(Request $request, ?MapCategory $cat = null): array
    {
        $idClause = $cat ? ',' . $cat->id : '';
        return $request->validate([
            'name'        => ['required', 'string', 'max:60', 'unique:map_categories,name' . $idClause],
            'slug'        => ['required', 'string', 'max:60', 'alpha_dash', 'unique:map_categories,slug' . $idClause],
            'description' => ['nullable', 'string', 'max:500'],
            'icon_path'   => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
    }
}
