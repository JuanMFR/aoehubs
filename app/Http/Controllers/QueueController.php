<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\QueueEntry;
use App\Services\CooldownService;
use App\Services\Matchmaking;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(private Matchmaking $matchmaking) {}

    public function join(Request $request)
    {
        $user = $request->user();

        if ($user->isInCooldown()) {
            $remaining = CooldownService::formatSeconds(CooldownService::remainingSeconds($user));
            return redirect()->route('dashboard')->with('error',
                "Estás en cooldown anti-griefing por {$remaining}. " .
                "Causa: abandonaste partidas o aborteaste lobbies recientemente.");
        }

        $match = $this->matchmaking->joinQueue($user);

        if ($match !== null) {
            return redirect()->route('drafts.maps.show', $match->id)
                ->with('flash', "Match encontrada: #{$match->id}. Empezá el draft de mapas.");
        }

        return redirect()->route('dashboard')->with('flash', 'En cola, esperando rival...');
    }

    public function leave(Request $request)
    {
        $this->matchmaking->leaveQueue($request->user());
        return redirect()->route('dashboard')->with('flash', 'Saliste de la cola.');
    }

    /**
     * Endpoint para que el dashboard sepa el estado: ¿en cola?, ¿match encontrada?
     * Detecta matches recién creadas (drafting) y también las posteriores
     * (pending, in_progress) para redirigir al user al lugar correcto.
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $inQueue = QueueEntry::where('user_id', $user->id)->where('is_bot', false)->exists();

        $activeMatch = GameMatch::where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                  ->orWhere('opponent_user_id', $user->id);
            })
            ->whereIn('status', [
                GameMatch::STATUS_DRAFTING,
                GameMatch::STATUS_PENDING,
                GameMatch::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('id')
            ->first();

        // URL de redirect según el estado: si está en draft → a la página del
        // draft; si ya pasó a pending/in_progress → al detalle del match
        // (donde está toda la info del lobby + civ + mapa para que el user
        // sepa qué configurar en AoE2).
        $redirectUrl = null;
        if ($activeMatch !== null) {
            $redirectUrl = match ($activeMatch->status) {
                GameMatch::STATUS_DRAFTING => route('drafts.maps.show', $activeMatch->id),
                default                    => route('matches.show', $activeMatch->id),
            };
        }

        return response()->json([
            'in_queue'     => $inQueue,
            'match_id'     => $activeMatch?->id,
            'match_status' => $activeMatch?->status,
            'redirect_url' => $redirectUrl,
        ]);
    }
}
