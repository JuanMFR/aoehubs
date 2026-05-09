<?php

namespace App\Http\Controllers;

use App\Models\MapPoolVote;
use App\Models\MapPoolVoteBallot;
use Illuminate\Http\Request;

/**
 * Endpoint de submit de voto de pool de mapas.
 *
 * Visibilidad: cualquier user logueado (decision del producto: maximizar
 * participacion, no es decision que afecte rating asi que multi-account no
 * vale la pena perseguir).
 *
 * El form se renderiza inline en el dashboard via partial
 * `_map_vote_modal.blade.php`; este controller solo procesa el submit y
 * redirige back al dashboard.
 *
 * Una sola votacion abierta a la vez (enforced en AdminController::storeMapVote);
 * si por alguna razon hay varias, agarramos la mas reciente.
 */
class MapVoteController extends Controller
{
    /**
     * Submit/update del voto. Idempotente: el user puede submitear N veces
     * mientras la votacion este abierta — sobrescribe su ballot anterior
     * via updateOrCreate (la unique key es vote_id+user_id).
     */
    public function submit(Request $request)
    {
        $vote = MapPoolVote::with('candidates')
            ->where('status', MapPoolVote::STATUS_OPEN)
            ->latest('id')
            ->first();

        if ($vote === null) {
            return redirect()->route('dashboard')
                ->with('error', 'No hay ninguna votación abierta ahora mismo.');
        }

        // Doble check: aunque status=open, podria haber pasado ends_at en
        // el rato entre el render del form y el submit (el cron va a
        // cerrarla en el proximo minuto). Bloqueamos ahi.
        if (! $vote->isVotable()) {
            return redirect()->route('dashboard')
                ->with('error', 'La votación ya cerró — tu voto no se registró.');
        }

        $data = $request->validate([
            'votes'   => ['required', 'array', 'min:1', 'max:' . $vote->pool_size_voted],
            'votes.*' => ['integer', 'distinct', 'exists:maps,id'],
        ]);

        // Validacion: todos los map_ids del ballot tienen que ser candidatos
        // del vote. Sin esto, alguien podria meter un map_id arbitrario via
        // form tampering.
        $candidateIds = $vote->candidates->pluck('id')->all();
        $invalid = array_diff($data['votes'], $candidateIds);
        if (! empty($invalid)) {
            return back()->withErrors([
                'votes' => 'Algunos mapas elegidos no son candidatos válidos en esta votación.',
            ])->withInput();
        }

        MapPoolVoteBallot::updateOrCreate(
            ['vote_id' => $vote->id, 'user_id' => $request->user()->id],
            ['votes_json' => array_values($data['votes'])],
        );

        return redirect()->route('dashboard')
            ->with('flash', 'Tu voto fue registrado. Podés cambiarlo cuantas veces quieras hasta el cierre.');
    }
}
