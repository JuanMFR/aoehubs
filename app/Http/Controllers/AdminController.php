<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CompanionApiController;
use App\Models\GameMatch;
use App\Models\QueueEntry;
use App\Models\User;
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

        $statuses = [
            GameMatch::STATUS_DRAFTING,
            GameMatch::STATUS_PENDING,
            GameMatch::STATUS_IN_PROGRESS,
            GameMatch::STATUS_COMPLETED,
            GameMatch::STATUS_PENDING_VALIDATION,
            GameMatch::STATUS_INVALID,
            GameMatch::STATUS_ABANDONED,
        ];

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

        DB::transaction(function () use ($match, $resolution) {
            $match->update($resolution['updates']);
            if ($resolution['ratingApplied']) {
                $match->fresh(['host', 'opponent'])->applyRatingChange($resolution['winnerUserId']);
            }
        });

        $newStatus = $resolution['updates']['status'];
        return back()->with('flash', "Match #{$match->id} reprocesada → {$newStatus}.");
    }

}
