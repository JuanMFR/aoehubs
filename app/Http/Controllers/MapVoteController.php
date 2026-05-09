<?php

namespace App\Http\Controllers;

use App\Models\MapPoolVote;
use App\Models\MapPoolVoteBallot;
use Illuminate\Http\Request;

/**
 * Frontend de votacion de pool de mapas para los users.
 *
 * Visibilidad: cualquier user logueado (decisión del producto: maximizar
 * participacion, no es decision que afecte rating asi que multi-account no
 * vale la pena perseguir).
 *
 * Una sola votacion abierta a la vez (enforced en AdminController::storeMapVote);
 * si por alguna razon hay varias, agarramos la mas reciente.
 */
class MapVoteController extends Controller
{
    /**
     * Vista de votacion. Si no hay votacion abierta, mostramos un empty
     * state con el snapshot del pool actual + la proxima votacion (si la
     * hay programada al futuro).
     */
    public function show(Request $request)
    {
        $vote = MapPoolVote::with('candidates')
            ->where('status', MapPoolVote::STATUS_OPEN)
            ->latest('id')
            ->first();

        $ballot = null;
        if ($vote !== null) {
            $ballot = $vote->ballots()->where('user_id', $request->user()->id)->first();
        }

        return view('maps.vote', compact('vote', 'ballot'));
    }

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
            return redirect()->route('maps.vote')
                ->with('error', 'No hay ninguna votación abierta ahora mismo.');
        }

        // Doble check: aunque status=open, podria haber pasado ends_at en
        // el rato entre el render del form y el submit (el cron va a
        // cerrarla en el proximo minuto). Bloqueamos ahi.
        if (! $vote->isVotable()) {
            return redirect()->route('maps.vote')
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

        return redirect()->route('maps.vote')
            ->with('flash', 'Tu voto fue registrado. Podés cambiarlo cuantas veces quieras hasta el cierre.');
    }
}
