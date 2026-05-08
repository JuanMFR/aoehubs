<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Services\CooldownService;
use App\Services\Matchmaking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatchController extends Controller
{
    public function __construct(private Matchmaking $matchmaking) {}

    /**
     * Lista las matches del usuario actual + form para crear una nueva.
     * Soporta filtros: ?status=, ?result=win|loss|walkover, ?opponent= (busca
     * en persona_name o steam_id del rival). Pagina de 20 en 20.
     */
    public function index(Request $request)
    {
        $userId        = $request->user()->id;
        $statusFilter  = $request->input('status');
        $resultFilter  = $request->input('result');
        $opponentQ     = trim((string) $request->input('opponent'));

        $query = GameMatch::with(['host', 'opponent', 'mapDraft', 'civDraft'])
            ->where(function ($q) use ($userId) {
                $q->where('host_user_id', $userId)
                  ->orWhere('opponent_user_id', $userId);
            });

        if ($statusFilter && in_array($statusFilter, [
            GameMatch::STATUS_DRAFTING,
            GameMatch::STATUS_PENDING,
            GameMatch::STATUS_IN_PROGRESS,
            GameMatch::STATUS_COMPLETED,
            GameMatch::STATUS_PENDING_VALIDATION,
            GameMatch::STATUS_INVALID,
            GameMatch::STATUS_ABANDONED,
        ], true)) {
            $query->where('status', $statusFilter);
        } else {
            // Por default ocultamos abandoned — no aportan info al historial.
            // Para verlos hay que filtrar explicitamente por status=abandoned.
            $query->where('status', '!=', GameMatch::STATUS_ABANDONED);
        }

        // Filtro por resultado: 'win' / 'loss' / 'walkover'. Solo aplica a
        // completadas; si elegis 'win' aplicamos winner_user_id = userId.
        if ($resultFilter === 'win') {
            $query->where('status', GameMatch::STATUS_COMPLETED)->where('winner_user_id', $userId);
        } elseif ($resultFilter === 'loss') {
            $query->where('status', GameMatch::STATUS_COMPLETED)
                  ->whereNotNull('winner_user_id')
                  ->where('winner_user_id', '!=', $userId);
        } elseif ($resultFilter === 'walkover') {
            $query->where('status', GameMatch::STATUS_COMPLETED)
                  ->whereNull('replay_path');
        }

        // Filtro por opponent: matchea persona_name o steam_id del *otro* user.
        // Cualquiera de los dos (host o opponent) puede ser el "rival" segun
        // quien sea el viewer, asi que filtramos por la otra punta.
        if ($opponentQ !== '') {
            $query->whereHas('host', function ($q) use ($userId, $opponentQ) {
                $q->where('users.id', '!=', $userId)
                  ->where(function ($qq) use ($opponentQ) {
                      $qq->where('persona_name', 'like', "%{$opponentQ}%")
                         ->orWhere('steam_id', 'like', "%{$opponentQ}%");
                  });
            })->orWhereHas('opponent', function ($q) use ($userId, $opponentQ) {
                $q->where('users.id', '!=', $userId)
                  ->where(function ($qq) use ($opponentQ) {
                      $qq->where('persona_name', 'like', "%{$opponentQ}%")
                         ->orWhere('steam_id', 'like', "%{$opponentQ}%");
                  });
            });
        }

        $matches = $query->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $statuses = [
            GameMatch::STATUS_DRAFTING,
            GameMatch::STATUS_PENDING,
            GameMatch::STATUS_IN_PROGRESS,
            GameMatch::STATUS_COMPLETED,
            GameMatch::STATUS_PENDING_VALIDATION,
            GameMatch::STATUS_INVALID,
            GameMatch::STATUS_ABANDONED,
        ];

        return view('matches.index', compact('matches', 'statuses', 'statusFilter', 'resultFilter', 'opponentQ'));
    }

    /**
     * Vista detalle de una match. La info que muestra depende del status:
     *   - drafting       → link al draft activo
     *   - pending        → card grande de mapa + civs + checklist pre-partida
     *   - in_progress    → status actual + heartbeats
     *   - completed      → resultado + ΔRating + datos del replay
     *   - invalid        → errors de validación
     *   - abandoned      → motivo
     *
     * Sirve también como destino post-draft (en lugar de redirigir a /matches).
     */
    public function show(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $match  = GameMatch::with(['host', 'opponent', 'winner', 'mapDraft', 'civDraft'])
            ->where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('host_user_id', $userId)
                  ->orWhere('opponent_user_id', $userId);
            })
            ->first();

        if ($match === null) abort(404);

        return view('matches.show', compact('match'));
    }

    /**
     * Crea instantáneamente una match con el user como host y el Bot Dev como
     * opponent. Sirve para testear el flow de host sin esperar otro jugador.
     */
    public function testHost(Request $request)
    {
        $match = $this->matchmaking->createTestHostMatch($request->user());
        return redirect()->route('matches.index')->with('flash', "Test host match #{$match->id} creada.");
    }

    /**
     * Crea match con Bot Dev como host (con lobby_id+password provistos por el
     * user) y el user como joiner. Para testear el flow de joiner solo.
     */
    public function testJoiner(Request $request)
    {
        $data = $request->validate([
            'lobby_id' => ['required', 'string', 'regex:/^\d{8,12}$/'],
            'password' => ['required', 'string', 'max:60'],
        ]);

        $match = $this->matchmaking->createTestJoinerMatch(
            $request->user(),
            $data['lobby_id'],
            $data['password'],
        );

        return redirect()->route('matches.index')->with('flash', "Test joiner match #{$match->id} creada.");
    }

    /**
     * Crea una match pendiente para el usuario actual como host.
     */
    public function store(Request $request)
    {
        $validServers = [
            'westeurope', 'eastus', 'brazilsouth', 'southeastasia',
            'koreacentral', 'australiasoutheast', 'centralindia',
            'ukwest', 'southcentralus', 'westus3', 'chilecentral',
        ];

        $data = $request->validate([
            'lobby_name' => ['required', 'string', 'max:60'],
            'password'   => ['nullable', 'string', 'max:60'],
            'server'     => ['required', Rule::in($validServers)],
        ]);

        // Auto-abandonar matches pendientes anteriores del mismo usuario:
        // un usuario sólo puede tener 1 match pending a la vez (la nueva).
        GameMatch::where('host_user_id', $request->user()->id)
            ->where('status', GameMatch::STATUS_PENDING)
            ->update(['status' => GameMatch::STATUS_ABANDONED]);

        GameMatch::create([
            'host_user_id' => $request->user()->id,
            'config_json'  => [
                'lobbyName' => $data['lobby_name'],
                'password'  => $data['password'] ?? '',
                'server'    => $data['server'],
            ],
            'status' => GameMatch::STATUS_PENDING,
        ]);

        return redirect()->route('matches.index')->with('flash', 'Match creada.');
    }

    /**
     * Cancela manualmente una match en draft o pending. Aplica anti-griefing.
     *
     * Reglas:
     *   - Solo participantes (host u opponent) pueden cancelar
     *   - Status valido: drafting, pending. No se permite cancelar in_progress
     *     desde aca (eso lo maneja el heartbeat timeout con KIND_MID_GAME_DISCONNECT)
     *   - Cancelar mid-draft → KIND_DRAFT_ABANDON
     *   - Cancelar pending  → KIND_LOBBY_ABORT (el draft termino, abandonan antes de jugar)
     *   - El otro jugador NO recibe penalty. Su flow detecta status=abandoned via
     *     polling y se redirige al detalle del match.
     */
    public function cancel(Request $request, int $id)
    {
        $userId = $request->user()->id;

        $match = GameMatch::where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('host_user_id', $userId)->orWhere('opponent_user_id', $userId);
            })
            ->whereIn('status', [GameMatch::STATUS_DRAFTING, GameMatch::STATUS_PENDING])
            ->firstOrFail();

        $offenseKind = $match->status === GameMatch::STATUS_DRAFTING
            ? CooldownService::KIND_DRAFT_ABANDON
            : CooldownService::KIND_LOBBY_ABORT;

        $match->update(['status' => GameMatch::STATUS_ABANDONED]);
        $cdSecs = CooldownService::record($request->user(), $match, $offenseKind);

        if ($cdSecs > 0) {
            // Tono firme — el contador grande de la card lo va a comunicar mejor.
            return redirect()->route('dashboard')->with('error', 'Has abandonado demasiadas partidas.');
        }

        return redirect()->route('dashboard')->with('flash', 'Partida cancelada.');
    }
}
