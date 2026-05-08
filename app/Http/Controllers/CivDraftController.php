<?php

namespace App\Http\Controllers;

use App\Models\CivDraft;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\Matchmaking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CivDraftController extends Controller
{
    private const BOT_STEAM_ID         = 'BOTDEV_PERMANENT_QUEUE';
    private const TURN_TIMEOUT_SECONDS = 60; // un poco más que el map draft

    public function show(Request $request, int $matchId)
    {
        $match = $this->findUserMatch($request, $matchId);
        if ($match === null) abort(404);
        if ($match->civDraft === null) {
            // Si llegan acá sin haber terminado el map draft, redirigir
            return redirect()->route('drafts.maps.show', $matchId);
        }
        $this->processBotActions($match);
        return view('drafts.civs', ['match' => $match]);
    }

    public function state(Request $request, int $matchId): JsonResponse
    {
        $match = $this->findUserMatch($request, $matchId);
        if ($match === null) return response()->json(['error' => 'not found'], 404);
        if ($match->civDraft === null) return response()->json(['error' => 'no civ draft yet'], 400);

        $this->processBotActions($match);
        return response()->json($this->buildState($match->fresh('civDraft'), $request->user()));
    }

    /**
     * POST picks: el user submite sus 4 elecciones iniciales.
     */
    public function submitPicks(Request $request, int $matchId): JsonResponse
    {
        $data = $request->validate([
            'picks'   => ['required', 'array', 'size:4'],
            'picks.*' => ['required', 'string', 'in:' . implode(',', Matchmaking::CIV_POOL)],
        ]);

        if (count(array_unique($data['picks'])) !== 4) {
            return response()->json(['error' => 'picks deben ser 4 civs distintas'], 400);
        }

        $error = DB::transaction(function () use ($request, $matchId, $data) {
            $match = $this->findUserMatch($request, $matchId, lockForUpdate: true);
            if ($match === null) return response()->json(['error' => 'not found'], 404);

            $draft = $match->civDraft;
            if ($draft === null || $draft->phase !== CivDraft::PHASE_PICKING) {
                return response()->json(['error' => 'no estás en la fase de picks'], 409);
            }

            $isHost = $match->host_user_id === $request->user()->id;
            $field  = $isHost ? 'host_picks_json' : 'opponent_picks_json';

            if ($draft->{$field} !== null) {
                return response()->json(['error' => 'ya enviaste tus picks'], 409);
            }

            $draft->{$field} = $data['picks'];
            $this->maybeAdvancePhase($draft);
            $draft->save();
            return null;
        });

        if ($error instanceof JsonResponse) return $error;

        $match = $this->findUserMatch($request, $matchId);
        $this->processBotActions($match);
        return response()->json($this->buildState($match->fresh('civDraft'), $request->user()));
    }

    /**
     * POST bans: el user banea 2 de los 4 picks del rival.
     */
    public function submitBans(Request $request, int $matchId): JsonResponse
    {
        $data = $request->validate([
            'bans'   => ['required', 'array', 'size:2'],
            'bans.*' => ['required', 'string'],
        ]);

        if (count(array_unique($data['bans'])) !== 2) {
            return response()->json(['error' => 'bans deben ser 2 civs distintas'], 400);
        }

        $error = DB::transaction(function () use ($request, $matchId, $data) {
            $match = $this->findUserMatch($request, $matchId, lockForUpdate: true);
            if ($match === null) return response()->json(['error' => 'not found'], 404);

            $draft = $match->civDraft;
            if ($draft === null || $draft->phase !== CivDraft::PHASE_BANNING) {
                return response()->json(['error' => 'no estás en la fase de bans'], 409);
            }

            $isHost = $match->host_user_id === $request->user()->id;
            $myBansField  = $isHost ? 'host_bans_json' : 'opponent_bans_json';
            $oppPicks     = $isHost ? $draft->opponent_picks_json : $draft->host_picks_json;

            if ($draft->{$myBansField} !== null) {
                return response()->json(['error' => 'ya enviaste tus bans'], 409);
            }

            // Validar que los bans estén entre los picks del rival
            foreach ($data['bans'] as $b) {
                if (!in_array($b, $oppPicks, true)) {
                    return response()->json(['error' => "no podés banear $b: no está entre los picks del rival"], 400);
                }
            }

            $draft->{$myBansField} = $data['bans'];
            $this->maybeAdvancePhase($draft);
            $draft->save();
            return null;
        });

        if ($error instanceof JsonResponse) return $error;

        $match = $this->findUserMatch($request, $matchId);
        $this->processBotActions($match);
        return response()->json($this->buildState($match->fresh('civDraft'), $request->user()));
    }

    /**
     * POST final: el user elige su civ final (1 de las 2 que sobrevivieron).
     */
    public function submitFinal(Request $request, int $matchId): JsonResponse
    {
        $data = $request->validate([
            'civ' => ['required', 'string'],
        ]);

        $error = DB::transaction(function () use ($request, $matchId, $data) {
            $match = $this->findUserMatch($request, $matchId, lockForUpdate: true);
            if ($match === null) return response()->json(['error' => 'not found'], 404);

            $draft = $match->civDraft;
            if ($draft === null || $draft->phase !== CivDraft::PHASE_FINALIZING) {
                return response()->json(['error' => 'no estás en la fase de finalización'], 409);
            }

            $isHost = $match->host_user_id === $request->user()->id;
            $field  = $isHost ? 'host_final_civ' : 'opponent_final_civ';

            if ($draft->{$field} !== null) {
                return response()->json(['error' => 'ya elegiste tu civ final'], 409);
            }

            $remaining = $this->remainingPicksFor($draft, $isHost);
            if (!in_array($data['civ'], $remaining, true)) {
                return response()->json(['error' => 'esa civ no está entre tus picks sobrevivientes'], 400);
            }

            $draft->{$field} = $data['civ'];
            $this->maybeAdvancePhase($draft);
            $draft->save();

            // Si el draft quedó completed, escribir civs en match.config_json y
            // pasar la match a pending → ya el companion la toma.
            if ($draft->phase === CivDraft::PHASE_COMPLETED) {
                $config = $match->config_json;
                $config['hostCiv']     = $draft->host_final_civ;
                $config['opponentCiv'] = $draft->opponent_final_civ;
                $match->config_json = $config;
                $match->status      = GameMatch::STATUS_PENDING;
                $match->save();
            }
            return null;
        });

        if ($error instanceof JsonResponse) return $error;

        $match = $this->findUserMatch($request, $matchId);
        $this->processBotActions($match);
        return response()->json($this->buildState($match->fresh('civDraft'), $request->user()));
    }

    /**
     * Hace lo que tenga que hacer el bot según la fase actual + auto-acciones
     * por timeout en humanos. Idempotente.
     */
    private function processBotActions(GameMatch $match): void
    {
        $bot = User::where('steam_id', self::BOT_STEAM_ID)->first();

        DB::transaction(function () use ($match, $bot) {
            $draft = CivDraft::where('match_id', $match->id)->lockForUpdate()->first();
            if ($draft === null || $draft->phase === CivDraft::PHASE_COMPLETED) return;

            $changed = true;
            $iterationGuard = 0;
            while ($changed && $iterationGuard++ < 20) {
                $changed = false;
                $changed = $this->actAsBotIfPossible($match, $draft, $bot) || $changed;
                $changed = $this->actByTimeout($match, $draft) || $changed;

                // Re-cargar phase desde DB porque maybeAdvancePhase pudo haber actualizado
                $draft->refresh();

                // Si llegamos a completed, cerrar la match
                if ($draft->phase === CivDraft::PHASE_COMPLETED && $match->status === GameMatch::STATUS_DRAFTING) {
                    $config = $match->config_json;
                    $config['hostCiv']     = $draft->host_final_civ;
                    $config['opponentCiv'] = $draft->opponent_final_civ;
                    $match->config_json = $config;
                    $match->status      = GameMatch::STATUS_PENDING;
                    $match->save();
                    break;
                }
            }
        });
    }

    /**
     * Si el rival es bot y todavía no actuó en la fase actual, lo hace ahora.
     * Devuelve true si hizo algo, false si no.
     */
    private function actAsBotIfPossible(GameMatch $match, CivDraft $draft, ?User $bot): bool
    {
        if ($bot === null) return false;
        $botIsHost = $match->host_user_id === $bot->id;
        $botIsOpp  = $match->opponent_user_id === $bot->id;
        if (!$botIsHost && !$botIsOpp) return false;

        $action = false;
        switch ($draft->phase) {
            case CivDraft::PHASE_PICKING:
                $field = $botIsHost ? 'host_picks_json' : 'opponent_picks_json';
                if ($draft->{$field} === null) {
                    // Bot pickea 4 random del pool
                    $pool = Matchmaking::CIV_POOL;
                    shuffle($pool);
                    $draft->{$field} = array_slice($pool, 0, 4);
                    $action = true;
                }
                break;

            case CivDraft::PHASE_BANNING:
                $field    = $botIsHost ? 'host_bans_json' : 'opponent_bans_json';
                $oppPicks = $botIsHost ? $draft->opponent_picks_json : $draft->host_picks_json;
                if ($draft->{$field} === null && $oppPicks !== null) {
                    $shuffled = $oppPicks;
                    shuffle($shuffled);
                    $draft->{$field} = array_slice($shuffled, 0, 2);
                    $action = true;
                }
                break;

            case CivDraft::PHASE_FINALIZING:
                $field = $botIsHost ? 'host_final_civ' : 'opponent_final_civ';
                if ($draft->{$field} === null) {
                    $remaining = $this->remainingPicksFor($draft, $botIsHost);
                    if (!empty($remaining)) {
                        $draft->{$field} = $remaining[array_rand($remaining)];
                        $action = true;
                    }
                }
                break;
        }

        if ($action) {
            $this->maybeAdvancePhase($draft);
            $draft->save();
        }
        return $action;
    }

    /**
     * Si pasó el timeout del turno actual y algún humano no actuó, completamos por él.
     */
    private function actByTimeout(GameMatch $match, CivDraft $draft): bool
    {
        if (!now()->greaterThan($draft->updated_at->copy()->addSeconds(self::TURN_TIMEOUT_SECONDS))) {
            return false;
        }
        if ($draft->phase === CivDraft::PHASE_COMPLETED) return false;

        $action = false;
        // Para cada user, si no actuó en la fase actual, le hacemos pick random
        foreach (['host', 'opponent'] as $role) {
            $isHost = $role === 'host';
            $userId = $isHost ? $match->host_user_id : $match->opponent_user_id;
            if ($userId === null) continue;

            switch ($draft->phase) {
                case CivDraft::PHASE_PICKING:
                    $field = $isHost ? 'host_picks_json' : 'opponent_picks_json';
                    if ($draft->{$field} === null) {
                        $pool = Matchmaking::CIV_POOL;
                        shuffle($pool);
                        $draft->{$field} = array_slice($pool, 0, 4);
                        $action = true;
                    }
                    break;
                case CivDraft::PHASE_BANNING:
                    $field    = $isHost ? 'host_bans_json' : 'opponent_bans_json';
                    $oppPicks = $isHost ? $draft->opponent_picks_json : $draft->host_picks_json;
                    if ($draft->{$field} === null && $oppPicks !== null) {
                        $shuffled = $oppPicks;
                        shuffle($shuffled);
                        $draft->{$field} = array_slice($shuffled, 0, 2);
                        $action = true;
                    }
                    break;
                case CivDraft::PHASE_FINALIZING:
                    $field = $isHost ? 'host_final_civ' : 'opponent_final_civ';
                    if ($draft->{$field} === null) {
                        $remaining = $this->remainingPicksFor($draft, $isHost);
                        if (!empty($remaining)) {
                            $draft->{$field} = $remaining[array_rand($remaining)];
                            $action = true;
                        }
                    }
                    break;
            }
        }

        if ($action) {
            $this->maybeAdvancePhase($draft);
            $draft->save();
        }
        return $action;
    }

    /**
     * Si ambos completaron la fase actual, avanza a la siguiente.
     */
    private function maybeAdvancePhase(CivDraft $draft): void
    {
        switch ($draft->phase) {
            case CivDraft::PHASE_PICKING:
                if ($draft->host_picks_json !== null && $draft->opponent_picks_json !== null) {
                    $draft->phase = CivDraft::PHASE_BANNING;
                }
                break;
            case CivDraft::PHASE_BANNING:
                if ($draft->host_bans_json !== null && $draft->opponent_bans_json !== null) {
                    $draft->phase = CivDraft::PHASE_FINALIZING;
                }
                break;
            case CivDraft::PHASE_FINALIZING:
                if ($draft->host_final_civ !== null && $draft->opponent_final_civ !== null) {
                    $draft->phase = CivDraft::PHASE_COMPLETED;
                }
                break;
        }
    }

    /**
     * Picks que le quedan a un user después de que el rival baneó.
     * Para finalizing.
     */
    private function remainingPicksFor(CivDraft $draft, bool $isHost): array
    {
        $myPicks = $isHost ? ($draft->host_picks_json ?? []) : ($draft->opponent_picks_json ?? []);
        // Los bans los pone el RIVAL sobre MIS picks
        $oppBans = $isHost ? ($draft->opponent_bans_json ?? []) : ($draft->host_bans_json ?? []);
        return array_values(array_diff($myPicks, $oppBans));
    }

    private function findUserMatch(Request $request, int $matchId, bool $lockForUpdate = false): ?GameMatch
    {
        $userId = $request->user()->id;
        $query = GameMatch::with('civDraft')
            ->where('id', $matchId)
            ->where(function ($q) use ($userId) {
                $q->where('host_user_id', $userId)->orWhere('opponent_user_id', $userId);
            });
        if ($lockForUpdate) $query->lockForUpdate();
        return $query->first();
    }

    private function buildState(GameMatch $match, User $user): array
    {
        $draft  = $match->civDraft;
        $isHost = $match->host_user_id === $user->id;

        $myPicks   = $isHost ? $draft->host_picks_json : $draft->opponent_picks_json;
        $oppPicks  = $isHost ? $draft->opponent_picks_json : $draft->host_picks_json;
        $myBans    = $isHost ? $draft->host_bans_json : $draft->opponent_bans_json;
        $oppBans   = $isHost ? $draft->opponent_bans_json : $draft->host_bans_json;
        $myFinal   = $isHost ? $draft->host_final_civ : $draft->opponent_final_civ;
        $oppFinal  = $isHost ? $draft->opponent_final_civ : $draft->host_final_civ;

        // Durante 'picking' ocultamos los picks del rival hasta que ambos confirmen
        $showOppPicks = $draft->phase !== CivDraft::PHASE_PICKING;

        return [
            'phase'             => $draft->phase,
            'pool'              => Matchmaking::CIV_POOL,
            'my_picks'          => $myPicks,
            'opp_picks'         => $showOppPicks ? $oppPicks : null,
            'my_bans'           => $myBans,
            'opp_bans'          => $oppBans,
            'my_remaining'      => $draft->phase === CivDraft::PHASE_FINALIZING
                ? $this->remainingPicksFor($draft, $isHost)
                : null,
            'my_final'          => $myFinal,
            'opp_final'         => $oppFinal,
            'my_picked'         => $myPicks !== null,
            'opp_picked'        => $oppPicks !== null,
            'my_banned'         => $myBans !== null,
            'opp_banned'        => $oppBans !== null,
            'my_finalized'      => $myFinal !== null,
            'opp_finalized'     => $oppFinal !== null,
            'turn_deadline'     => $draft->phase !== CivDraft::PHASE_COMPLETED
                ? $draft->updated_at->copy()->addSeconds(self::TURN_TIMEOUT_SECONDS)->toIso8601String()
                : null,
            'turn_timeout_seconds' => self::TURN_TIMEOUT_SECONDS,
            'match_status'      => $match->status,
        ];
    }
}
