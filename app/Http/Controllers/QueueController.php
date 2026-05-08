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
                "No vas a poder buscar partida por {$remaining}.");
        }

        // Bloqueamos si el companion no esta corriendo. El companion hace
        // un presence ping cada 30s (Program.cs), asi que si last_used_at
        // tiene mas de 90s probablemente esta cerrado. Sin companion, el
        // matchmaking no tiene sentido — no se pueden detectar las partidas.
        $token = $user->tokens()->where('name', 'companion')->latest()->first();
        $companionAlive = $token
            && $token->last_used_at
            && $token->last_used_at->diffInSeconds(now()) < 90;

        if (! $companionAlive) {
            return redirect()->route('dashboard')->with('error',
                'El companion no parece estar corriendo. Abrilo y volvé a intentar.');
        }

        $match = $this->matchmaking->joinQueue($user);

        // Si emparejo instantaneamente (ej. con el Bot Dev), redirigimos
        // igualmente al dashboard con un session flag — el dashboard JS
        // dispara el modal "Partida encontrada" + sonido y maneja el
        // redirect tras countdown. Asi el flow es uniforme con el caso PvP
        // donde el match aparece via polling.
        if ($match !== null) {
            return redirect()->route('dashboard')->with('match_just_made', true);
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

        $activeMatch = GameMatch::with(['host', 'opponent'])
            ->where(function ($q) use ($user) {
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
        // draft; si ya pasó a pending/in_progress → al detalle del match.
        $redirectUrl = null;
        $rival = null;
        if ($activeMatch !== null) {
            $redirectUrl = match ($activeMatch->status) {
                GameMatch::STATUS_DRAFTING => route('drafts.maps.show', $activeMatch->id),
                default                    => route('matches.show', $activeMatch->id),
            };

            // Info minima del rival para mostrar en el modal "partida encontrada".
            $rivalUser = $activeMatch->host_user_id === $user->id
                ? $activeMatch->opponent
                : $activeMatch->host;
            if ($rivalUser) {
                $rival = [
                    'name'       => $rivalUser->displayName(),
                    'rating'     => round($rivalUser->rating),
                    'avatar_url' => $rivalUser->avatar_url,
                    'is_bot'     => $rivalUser->isBot(),
                ];
            }
        }

        return response()->json([
            'in_queue'     => $inQueue,
            'match_id'     => $activeMatch?->id,
            'match_status' => $activeMatch?->status,
            'redirect_url' => $redirectUrl,
            'rival'        => $rival,
        ]);
    }
}
