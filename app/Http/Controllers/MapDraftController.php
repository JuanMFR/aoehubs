<?php

namespace App\Http\Controllers;

use App\Models\CivDraft;
use App\Models\GameMatch;
use App\Models\MapDraft;
use App\Models\User;
use App\Services\Matchmaking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapDraftController extends Controller
{
    private const BOT_STEAM_ID         = 'BOTDEV_PERMANENT_QUEUE';
    private const TURN_TIMEOUT_SECONDS = 30;

    public function show(Request $request, int $matchId)
    {
        $match = $this->findUserMatch($request, $matchId);
        if ($match === null) abort(404);
        $this->processBans($match);
        return view('drafts.maps', ['match' => $match]);
    }

    public function state(Request $request, int $matchId): JsonResponse
    {
        $match = $this->findUserMatch($request, $matchId);
        if ($match === null) return response()->json(['error' => 'not found'], 404);

        // Cada poll: si es turno del bot lo banea, y si el humano se pasó del
        // deadline (30s) baneamos random por él.
        $this->processBans($match);

        return response()->json($this->buildState($match->fresh('mapDraft'), $request->user()));
    }

    public function ban(Request $request, int $matchId): JsonResponse
    {
        $data = $request->validate([
            'map' => ['required', 'string', 'max:60'],
        ]);

        // Transaction para registrar el ban del humano. Si algo falla, el
        // closure devuelve un JsonResponse y lo retornamos. Si todo OK,
        // devuelve null y seguimos con el procesamiento posterior.
        $error = DB::transaction(function () use ($request, $matchId, $data) {
            $match = $this->findUserMatch($request, $matchId, lockForUpdate: true);
            if ($match === null) return response()->json(['error' => 'not found'], 404);

            $draft = $match->mapDraft;
            if ($draft === null) return response()->json(['error' => 'no draft'], 400);
            if ($draft->final_map !== null) return response()->json(['error' => 'draft already finished'], 409);

            $turnUserId = $this->currentTurnUserId($match, $draft);
            if ($turnUserId !== $request->user()->id) {
                return response()->json(['error' => 'not your turn'], 409);
            }

            $remaining = $this->remainingMaps($draft);
            if (!in_array($data['map'], $remaining, true)) {
                return response()->json(['error' => 'map already banned or not in pool'], 400);
            }

            $this->addBan($draft, $request->user()->id, $data['map']);
            return null;
        });

        if ($error instanceof JsonResponse) return $error;

        // Bot bans + auto-bans por timeout (independientes del rol)
        $match = $this->findUserMatch($request, $matchId);
        $this->processBans($match);

        return response()->json($this->buildState($match->fresh('mapDraft'), $request->user()));
    }

    /**
     * Loop que cubre dos casos en una sola transacción:
     *   - Es turno del Bot Dev → banea el primer mapa por orden alfabético
     *   - Pasó el deadline (30s) en turno de un humano → banea random por él
     * Salimos cuando le toca a un humano dentro del deadline o termina el draft.
     */
    private function processBans(GameMatch $match): void
    {
        $bot = User::where('steam_id', self::BOT_STEAM_ID)->first();

        DB::transaction(function () use ($match, $bot) {
            $draft = MapDraft::where('match_id', $match->id)->lockForUpdate()->first();
            if ($draft === null || $draft->final_map !== null) return;

            while ($draft->final_map === null) {
                $turnUserId = $this->currentTurnUserId($match, $draft);
                $isBotTurn  = ($bot !== null && $turnUserId === $bot->id);
                $isTimedOut = now()->greaterThan($this->turnDeadline($draft));

                if (!$isBotTurn && !$isTimedOut) break;

                $remaining = $this->remainingMaps($draft);
                if (count($remaining) <= 1) break;

                if ($isBotTurn) {
                    sort($remaining); // determinístico: alfabético
                    $pick = $remaining[0];
                } else {
                    // timeout humano: pick random
                    $pick = $remaining[array_rand($remaining)];
                }

                $this->addBan($draft, $turnUserId, $pick);
            }
        });
    }

    private function findUserMatch(Request $request, int $matchId, bool $lockForUpdate = false): ?GameMatch
    {
        $userId = $request->user()->id;
        $query = GameMatch::with('mapDraft')
            ->where('id', $matchId)
            ->where(function ($q) use ($userId) {
                $q->where('host_user_id', $userId)->orWhere('opponent_user_id', $userId);
            });
        if ($lockForUpdate) $query->lockForUpdate();
        return $query->first();
    }

    private function buildState(GameMatch $match, User $user): array
    {
        $draft     = $match->mapDraft;
        $bans      = $draft->bans_json ?? [];
        $turnId    = $this->currentTurnUserId($match, $draft);

        return [
            'match_id'             => $match->id,
            'pool'                 => Matchmaking::MAP_POOL,
            'bans'                 => $bans,
            'final_map'            => $draft->final_map,
            'is_completed'         => $draft->final_map !== null,
            'your_turn'            => $turnId === $user->id && $draft->final_map === null,
            'host_user_id'         => $match->host_user_id,
            'opponent_user_id'     => $match->opponent_user_id,
            'starting_user_id'     => $draft->starting_user_id,
            'current_turn_user_id' => $turnId,
            'turn_deadline'        => $draft->final_map === null
                ? $this->turnDeadline($draft)->toIso8601String()
                : null,
            'turn_timeout_seconds' => self::TURN_TIMEOUT_SECONDS,
            'match_status'         => $match->status,
        ];
    }

    private function remainingMaps(MapDraft $draft): array
    {
        $bannedNames = array_column($draft->bans_json ?? [], 'map');
        return array_values(array_diff(Matchmaking::MAP_POOL, $bannedNames));
    }

    private function currentTurnUserId(GameMatch $match, MapDraft $draft): int
    {
        $banCount = count($draft->bans_json ?? []);
        $other = $draft->starting_user_id === $match->host_user_id
            ? $match->opponent_user_id
            : $match->host_user_id;
        return $banCount % 2 === 0 ? $draft->starting_user_id : $other;
    }

    /**
     * Cuándo arrancó el turno actual: timestamp del último ban, o created_at
     * del draft si todavía no hay bans.
     */
    private function turnStartedAt(MapDraft $draft): \Carbon\Carbon
    {
        $bans = $draft->bans_json ?? [];
        if (empty($bans)) return $draft->created_at;
        return \Carbon\Carbon::parse(end($bans)['ts']);
    }

    private function turnDeadline(MapDraft $draft): \Carbon\Carbon
    {
        return $this->turnStartedAt($draft)->copy()->addSeconds(self::TURN_TIMEOUT_SECONDS);
    }

    private function addBan(MapDraft $draft, int $userId, string $map): void
    {
        $bans = $draft->bans_json ?? [];
        $bans[] = ['user_id' => $userId, 'map' => $map, 'ts' => now()->toIso8601String()];
        $draft->bans_json = $bans;

        $remaining = $this->remainingMaps($draft);
        if (count($remaining) === 1) {
            $finalMap = $remaining[0];
            $draft->final_map = $finalMap;
            $draft->save();

            // Map draft completo: actualizamos el mapa en config_json y arrancamos
            // el civ draft. La match queda en `drafting` hasta que termine el civ.
            $match = $draft->match;
            $config = $match->config_json;
            $config['map'] = $finalMap;
            $match->config_json = $config;
            $match->save();

            CivDraft::firstOrCreate(
                ['match_id' => $match->id],
                ['phase' => CivDraft::PHASE_PICKING],
            );
        } else {
            $draft->save();
        }
    }
}
