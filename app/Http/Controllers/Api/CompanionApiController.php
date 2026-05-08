<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\CooldownService;
use App\Services\MatchValidator;
use App\Services\ReplayParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompanionApiController extends Controller
{
    /**
     * GET /api/companion/match
     * Devuelve la próxima partida activa del usuario, o 204 si no hay.
     * "Activa" = pending (lobby por armar) o in_progress (partida arrancó).
     * El companion necesita seguir viendo la match cuando pasa a in_progress
     * para poder mantenerla como contexto al subir el replay.
     */
    public function pendingMatch(Request $request): JsonResponse
    {
        $user = $request->user();

        // Auto-expirar pendings de más de 1h sin actividad
        GameMatch::where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                  ->orWhere('opponent_user_id', $user->id);
            })
            ->where('status', GameMatch::STATUS_PENDING)
            ->where('created_at', '<', now()->subHour())
            ->update(['status' => GameMatch::STATUS_ABANDONED]);

        $match = GameMatch::query()
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                  ->orWhere('opponent_user_id', $user->id);
            })
            ->whereIn('status', [GameMatch::STATUS_PENDING, GameMatch::STATUS_IN_PROGRESS])
            ->orderBy('id')
            ->first();

        if ($match === null) {
            return response()->json(null, 204);
        }

        $isHost = $match->host_user_id === $user->id;

        $payload = array_merge(
            [
                'matchId'     => $match->id,
                'role'        => $isHost ? 'host' : 'joiner',
                'hostSteamId' => $isHost ? $user->steam_id : ($match->host->steam_id ?? null),
                'lobbyId'     => $match->lobby_id,
                'status'      => $match->status,
            ],
            $match->config_json,
        );

        return response()->json($payload);
    }

    /**
     * POST /api/companion/pings
     * Body: { pings: { westeurope: 80, eastus: 145, ... } }
     * El companion mide latencia ICMP a cada server de AoE2 y reporta los
     * resultados (en ms). Se cachean en el user — cuando el matchmaker
     * empareja dos players, usa estos valores para elegir el server con menor
     * max(host_ping, opp_ping). Las regiones donde el ping falló no aparecen.
     */
    public function reportPings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pings'   => ['required', 'array'],
            'pings.*' => ['integer', 'min:0', 'max:5000'],
        ]);

        // Filtramos a las regiones que conocemos para evitar que el companion
        // pueda inflar el JSON con basura.
        $known = [
            'westeurope', 'eastus', 'brazilsouth', 'southeastasia',
            'koreacentral', 'australiasoutheast', 'centralindia', 'ukwest',
            'southcentralus', 'westus3', 'chilecentral',
        ];
        $clean = array_intersect_key($data['pings'], array_flip($known));

        $request->user()->update([
            'pings_json'       => $clean,
            'pings_updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'received' => count($clean)]);
    }

    /**
     * POST /api/companion/lobby-ready
     * Body: { matchId, lobbyId }
     * El companion del host reporta el "ID de juego" que extrajo por OCR del
     * lobby room, así el joiner puede abrir aoe2de://0/{lobbyId}.
     */
    public function reportLobbyReady(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matchId' => ['required', 'integer'],
            'lobbyId' => ['required', 'string', 'max:20'],
        ]);

        $user  = $request->user();
        $match = GameMatch::where('id', $data['matchId'])
            ->where('host_user_id', $user->id)
            ->first();

        if ($match === null) {
            return response()->json(['error' => 'match not found'], 404);
        }

        $match->update(['lobby_id' => $data['lobbyId']]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/companion/match-started
     * Body: { matchId }
     * El companion vio que AoE2 escribió un .aoe2record nuevo => la partida
     * realmente arrancó. Transición pending → in_progress.
     * Idempotente: si ya está in_progress, devuelve ok (puede ser el otro
     * companion llegando segundo).
     */
    public function matchStarted(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matchId' => ['required', 'integer'],
        ]);

        $match = $this->findOwnedMatch($request->user(), $data['matchId']);
        if ($match === null) {
            return response()->json(['error' => 'match not found'], 404);
        }

        if ($match->status === GameMatch::STATUS_IN_PROGRESS) {
            return response()->json(['ok' => true, 'alreadyStarted' => true]);
        }

        if ($match->status !== GameMatch::STATUS_PENDING) {
            return response()->json(['error' => "cannot start match from status {$match->status}"], 409);
        }

        // Sembramos el heartbeat del caller acá: el otro companion va a
        // setear el suyo en su próximo /heartbeat. Hasta entonces queda null,
        // que el cron interpreta como "todavía no apareció".
        $isHost = $match->host_user_id === $request->user()->id;
        $match->update([
            'status'                                       => GameMatch::STATUS_IN_PROGRESS,
            'started_at'                                   => now(),
            $isHost ? 'host_heartbeat_at' : 'opponent_heartbeat_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/companion/match-aborted
     * Body: { matchId, reason? }
     * El companion vio que el usuario salió del lobby al menú principal sin
     * que se hubiera escrito un .aoe2record => la partida nunca empezó.
     * Idempotente.
     */
    public function matchAborted(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matchId' => ['required', 'integer'],
            'reason'  => ['nullable', 'string', 'max:120'],
        ]);

        $match = $this->findOwnedMatch($request->user(), $data['matchId']);
        if ($match === null) {
            return response()->json(['error' => 'match not found'], 404);
        }

        // Sólo se puede abortar antes de que arranque la partida real.
        // Si ya está in_progress hay un .aoe2record en juego — no aceptamos abort.
        if (! in_array($match->status, [GameMatch::STATUS_PENDING, GameMatch::STATUS_DRAFTING], true)) {
            if ($match->status === GameMatch::STATUS_ABANDONED) {
                return response()->json(['ok' => true, 'alreadyAborted' => true]);
            }
            return response()->json(['error' => "cannot abort match from status {$match->status}"], 409);
        }

        $match->update(['status' => GameMatch::STATUS_ABANDONED]);
        // Anti-griefing: registrar la ofensa del que reportó el abort
        CooldownService::record($request->user(), $match, CooldownService::KIND_LOBBY_ABORT);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/companion/heartbeat
     * Body: { matchId }
     * Pulso de vida del companion. Si dejan de llegar, el cron de timeout
     * marca el match como abandoned.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matchId' => ['required', 'integer'],
        ]);

        $match = $this->findOwnedMatch($request->user(), $data['matchId']);
        if ($match === null) {
            return response()->json(['error' => 'match not found'], 404);
        }

        if (! in_array($match->status, [GameMatch::STATUS_PENDING, GameMatch::STATUS_IN_PROGRESS], true)) {
            return response()->json(['error' => "match no longer active ({$match->status})"], 409);
        }

        $isHost = $match->host_user_id === $request->user()->id;
        $match->update([
            $isHost ? 'host_heartbeat_at' : 'opponent_heartbeat_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => $match->status]);
    }

    /**
     * POST /api/companion/replay
     * Multipart: file=replay.aoe2record, matchId=N
     *
     * Flujo:
     *   1. Guarda el archivo en storage/app/replays/
     *   2. Lo pasa por mgz-fast (Python subprocess) → metadata + winner desde body
     *   3. Si parseó bien → MatchValidator → completed (+rating) o invalid
     *   4. Si parseó mal  → pending_validation (sin rating, reintenta cuando
     *                       lo arregle el upstream o le metamos un patch)
     *
     * El winner se determina autoritativamente desde el postgame del replay
     * (ver MatchValidator::winnerUserId). NO confiamos en outcome reportado
     * por client.
     */
    public function uploadReplay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matchId' => ['required', 'integer'],
            'file'    => ['required', 'file', 'max:102400'], // 100 MB
        ]);

        $user  = $request->user();
        $match = $this->findOwnedMatch($user, $data['matchId']);
        if ($match === null) {
            return response()->json(['error' => 'match not found'], 404);
        }

        // Reglas de aceptación del upload:
        //   - completed/abandoned: ya resuelto definitivamente, 409
        //   - invalid/pending_validation: el primer replay no resolvió el match
        //     (validación falló o parser no pudo). Si es OTRO user el que sube
        //     ahora (típicamente: A alt+f4 → su replay parcial subió primero;
        //     B con replay completo override), aceptamos el segundo upload.
        //     Si es el MISMO user, 409 (anti-spam).
        $resolvedFinal = in_array($match->status, [GameMatch::STATUS_COMPLETED, GameMatch::STATUS_ABANDONED], true);
        $resolvedSoft  = in_array($match->status, [GameMatch::STATUS_INVALID, GameMatch::STATUS_PENDING_VALIDATION], true);

        if ($resolvedFinal) {
            return response()->json(['error' => "match already resolved ({$match->status})"], 409);
        }
        if ($resolvedSoft && $match->replay_uploaded_by === $user->id) {
            return response()->json(['error' => "you already uploaded a replay for this match"], 409);
        }

        $file       = $request->file('file');
        $storedName = sprintf('match_%d_%d.aoe2record', $match->id, now()->timestamp);
        $path       = $file->storeAs('replays', $storedName, 'local');

        $resolution = self::resolveReplay($match, Storage::disk('local')->path($path));

        DB::transaction(function () use ($match, $user, $file, $path, $storedName, $resolution) {
            $match->update(array_merge([
                'replay_filename'     => $file->getClientOriginalName() ?: $storedName,
                'replay_size'         => $file->getSize(),
                'replay_path'         => $path,
                'replay_uploaded_by'  => $user->id,
            ], $resolution['updates']));

            if ($resolution['ratingApplied']) {
                $match->applyRatingChange($resolution['winnerUserId']);
            }
        });

        return response()->json([
            'ok'         => true,
            'replayPath' => $path,
            'status'     => $resolution['updates']['status'],
            'errors'     => $resolution['updates']['validation_errors'] ?? [],
        ]);
    }

    /**
     * Decide el estado final del match a partir del archivo subido. Llamado
     * tanto desde uploadReplay() como desde el comando de reproceso.
     *
     * El winner se saca exclusivamente del replay parseado — sin fallbacks
     * a info reportada por client. Si el parser no puede determinar winner
     * (ej: replay truncado por alt+f4), el match queda 'invalid' sin rating.
     *
     * @return array{updates: array, ratingApplied: bool, winnerUserId: ?int}
     */
    public static function resolveReplay(GameMatch $match, string $absolutePath): array
    {
        try {
            $parseResult = ReplayParser::parse($absolutePath);
        } catch (\Throwable $e) {
            // Error de infraestructura (Python no corre, script falta, timeout).
            // Tratado igual que parse_error — el match queda pending_validation
            // hasta que se resuelva la infra y se reprocese.
            Log::error("ReplayParser exception: " . $e->getMessage(), ['match_id' => $match->id]);
            return [
                'updates' => [
                    'status'            => GameMatch::STATUS_PENDING_VALIDATION,
                    'validation_errors' => ['infra: ' . $e->getMessage()],
                ],
                'ratingApplied' => false,
                'winnerUserId'  => null,
            ];
        }

        if (! $parseResult->ok) {
            // mgz no pudo leer el archivo (típicamente versión de DE más nueva
            // que la que mgz soporta). El match queda pending_validation.
            return [
                'updates' => [
                    'status'            => GameMatch::STATUS_PENDING_VALIDATION,
                    'validation_errors' => ["parse: {$parseResult->type}: {$parseResult->error}"],
                ],
                'ratingApplied' => false,
                'winnerUserId'  => null,
            ];
        }

        // Parseó OK → corremos validación contra el draft + reglas de ranked
        $errors       = MatchValidator::validate($match, $parseResult->data);
        $winnerUserId = MatchValidator::winnerUserId($match, $parseResult->data);

        if (! empty($errors)) {
            return [
                'updates' => [
                    'status'            => GameMatch::STATUS_INVALID,
                    'parsed_metadata'   => $parseResult->data,
                    'validation_errors' => $errors,
                    'parsed_at'         => now(),
                ],
                'ratingApplied' => false,
                'winnerUserId'  => null,
            ];
        }

        if ($winnerUserId === null) {
            // Parseó bien, validó bien, pero ningún jugador es winner.
            // Probablemente empate o desync no detectado por get_completed.
            return [
                'updates' => [
                    'status'            => GameMatch::STATUS_INVALID,
                    'parsed_metadata'   => $parseResult->data,
                    'validation_errors' => ['no se pudo determinar el ganador desde el replay'],
                    'parsed_at'         => now(),
                ],
                'ratingApplied' => false,
                'winnerUserId'  => null,
            ];
        }

        return [
            'updates' => [
                'status'            => GameMatch::STATUS_COMPLETED,
                'winner_user_id'    => $winnerUserId,
                'parsed_metadata'   => $parseResult->data,
                'validation_errors' => null,
                'parsed_at'         => now(),
            ],
            'ratingApplied' => true,
            'winnerUserId'  => $winnerUserId,
        ];
    }

    /**
     * Encuentra una match donde el user es host o opponent. Vale para todos
     * los endpoints de ciclo de vida — los dos participantes pueden interactuar.
     */
    private function findOwnedMatch($user, int $matchId): ?GameMatch
    {
        return GameMatch::where('id', $matchId)
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                  ->orWhere('opponent_user_id', $user->id);
            })
            ->first();
    }
}
