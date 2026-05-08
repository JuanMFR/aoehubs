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
        if (! $match->wasChanged('status')) return;
        if ($match->status !== GameMatch::STATUS_COMPLETED) return;

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
