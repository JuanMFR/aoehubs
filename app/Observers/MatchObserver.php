<?php

namespace App\Observers;

use App\Models\GameMatch;
use App\Services\AwardService;

/**
 * Observer del modelo GameMatch.
 *
 * Cuando un match transiciona a status=completed (Glicko ya aplicado por
 * el caller), evalua los awards instant para ambos jugadores.
 *
 * Solo dispara una vez por match: usamos `isDirty('status')` mas el
 * status previo en `getOriginal()` para no re-evaluar awards si el match
 * se updatea por otra razon (ej. admin reprocess).
 */
class MatchObserver
{
    public function __construct(private AwardService $awards) {}

    public function updated(GameMatch $match): void
    {
        // Disparamos en dos casos:
        //  a) status acaba de cambiar a 'completed' (caso normal: replay
        //     uploaded → status=completed pero applyRatingChange viene
        //     despues, host_rating_change todavia es null aqui)
        //  b) host_rating_change acaba de setearse en un match ya completed
        //     (caso forfeit: status se setea primero, applyRatingChange
        //     despues, awards necesitan host_rating_before/change para
        //     evaluar comeback / climber)
        //
        // Awards son idempotentes (AwardService::grant tiene check de
        // duplicado), asi que disparar 2 veces no duplica grants — solo
        // re-evalua. La fix es necesaria porque sin (b), comeback nunca
        // se otorga en forfeits (sus inputs vienen del rating before/change).
        $statusJustCompleted = $match->wasChanged('status')
            && $match->status === GameMatch::STATUS_COMPLETED;
        $ratingJustApplied = $match->wasChanged('host_rating_change')
            && $match->host_rating_change !== null
            && $match->status === GameMatch::STATUS_COMPLETED;

        if (! $statusJustCompleted && ! $ratingJustApplied) return;

        // Cargamos los users frescos para tener el rating post-Glicko.
        $match->loadMissing(['host', 'opponent']);

        if ($match->host !== null) {
            $this->awards->evaluateInstantForUser($match->host->fresh(), $match);
        }
        if ($match->opponent !== null) {
            $this->awards->evaluateInstantForUser($match->opponent->fresh(), $match);
        }
    }
}
