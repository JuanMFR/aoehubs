<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\MapPoolVote;
use App\Models\QueueEntry;
use App\Models\Season;
use App\Models\User;
use App\Services\CooldownService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard del usuario logueado. Concentra:
 *   - Estado del matchmaking (en cola, en cooldown, o CTA buscar partida)
 *   - Banner "estás en partida" si hay match activa (drafting/pending/in_progress)
 *   - Player card preview con tu rating + W/L + top awards
 *   - Stats de la season activa: top map/civ, total matches, top 1, mas activo
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $season = Season::current();

        // Estado del user
        $queueEntry = QueueEntry::where('user_id', $user->id)->where('is_bot', false)->first();
        $inCooldown = $user->isInCooldown();
        $cooldownSeconds = $inCooldown ? CooldownService::remainingSeconds($user) : 0;
        $cooldownLeft = $inCooldown
            ? CooldownService::formatSeconds($cooldownSeconds)
            : null;

        // Si el bot dev esta en queue (solo en dev local typically), avisamos
        // al user que va a quedar emparejado al instante. En prod esta apagado
        // y mostramos el mensaje generico.
        $botInQueue = QueueEntry::where('is_bot', true)->exists();

        // El companion ping-ea cada 30s; consideramos vivo si last_used_at
        // esta dentro de los ultimos 90s (3x el ping interval). Helper en User.
        $companionAlive = $user->companionAlive();

        // Match activa (drafting, pending o in_progress) — para mostrar el
        // CTA "estás en partida" cuando el user vuelve al dashboard mid-flow.
        $activeMatch = $user->activeMatch();

        $activeMatchUrl = null;
        $activeMatchRival = null;
        if ($activeMatch) {
            $activeMatchUrl = match ($activeMatch->status) {
                GameMatch::STATUS_DRAFTING => route('drafts.maps.show', $activeMatch->id),
                default                    => route('matches.show', $activeMatch->id),
            };

            $rival = $activeMatch->host_user_id === $user->id
                ? $activeMatch->opponent
                : $activeMatch->host;
            if ($rival) {
                $activeMatchRival = [
                    'name'       => $rival->displayName(),
                    'rating'     => round($rival->rating),
                    'avatar_url' => $rival->avatar_url,
                    'is_bot'     => $rival->isBot(),
                ];
            }
        }

        // Stats de season activa (vacios si no hay season). Cacheamos 60s
        // porque son los mismos para TODOS los users — 6 aggregates por
        // hit del dashboard se vuelven 1 hit cache + miss.
        $seasonStats = $season
            ? Cache::remember("season_stats:{$season->id}", 60, fn () => $this->seasonStats($season))
            : null;

        // Votacion de pool de mapas abierta (si la hay). Sirve para mostrar
        // un CTA "opina" en el dashboard. El flag $userVoted indica si el
        // user ya emitio ballot (cambia el copy del CTA).
        $openVote  = MapPoolVote::where('status', MapPoolVote::STATUS_OPEN)->latest('id')->first();
        $userVoted = $openVote
            ? $openVote->ballots()->where('user_id', $user->id)->exists()
            : false;

        return view('dashboard', compact(
            'user', 'season', 'queueEntry', 'inCooldown', 'cooldownLeft', 'cooldownSeconds',
            'activeMatch', 'activeMatchUrl', 'activeMatchRival', 'seasonStats',
            'botInQueue', 'companionAlive', 'openVote', 'userVoted',
        ));
    }

    /**
     * Stats agregadas de una season — totalmente derivables de matches +
     * map_drafts + civ_drafts. No persistidas.
     */
    private function seasonStats(Season $season): array
    {
        $stats = [];

        $stats['total_matches'] = GameMatch::where('season_id', $season->id)
            ->where('status', GameMatch::STATUS_COMPLETED)
            ->count();

        // Top map jugado: el final_map mas comun entre matches completed.
        $stats['top_map'] = DB::table('map_drafts')
            ->join('matches', 'map_drafts.match_id', '=', 'matches.id')
            ->where('matches.season_id', $season->id)
            ->where('matches.status', GameMatch::STATUS_COMPLETED)
            ->whereNotNull('map_drafts.final_map')
            ->select('map_drafts.final_map as map', DB::raw('COUNT(*) as count'))
            ->groupBy('map_drafts.final_map')
            ->orderByDesc('count')
            ->first();

        // Top civ ganadora: la civ con mas victorias en la season.
        $stats['top_civ'] = DB::table('civ_drafts')
            ->join('matches', 'civ_drafts.match_id', '=', 'matches.id')
            ->where('matches.season_id', $season->id)
            ->where('matches.status', GameMatch::STATUS_COMPLETED)
            ->whereNotNull('matches.winner_user_id')
            ->selectRaw("
                CASE WHEN matches.winner_user_id = matches.host_user_id
                     THEN civ_drafts.host_final_civ
                     ELSE civ_drafts.opponent_final_civ
                END as civ,
                COUNT(*) as count
            ")
            ->whereNotNull(DB::raw("CASE WHEN matches.winner_user_id = matches.host_user_id
                                          THEN civ_drafts.host_final_civ
                                          ELSE civ_drafts.opponent_final_civ
                                     END"))
            ->groupBy('civ')
            ->orderByDesc('count')
            ->first();

        // Top map baneado: bans estan en map_drafts.bans_json (array de
        // {user_id, map, ts}). Lo agregamos en PHP porque JSON queries
        // varian por DB engine.
        $mapBans = DB::table('map_drafts')
            ->join('matches', 'map_drafts.match_id', '=', 'matches.id')
            ->where('matches.season_id', $season->id)
            ->where('matches.status', GameMatch::STATUS_COMPLETED)
            ->whereNotNull('map_drafts.bans_json')
            ->pluck('map_drafts.bans_json');

        $mapBanCounts = [];
        foreach ($mapBans as $banJson) {
            $bans = is_string($banJson) ? json_decode($banJson, true) : $banJson;
            foreach ($bans ?? [] as $ban) {
                $name = $ban['map'] ?? null;
                if ($name) $mapBanCounts[$name] = ($mapBanCounts[$name] ?? 0) + 1;
            }
        }
        if ($mapBanCounts) {
            arsort($mapBanCounts);
            $stats['top_banned_map'] = (object) [
                'map'   => array_key_first($mapBanCounts),
                'count' => reset($mapBanCounts),
            ];
        }

        // Top civ baneada: union de host_bans_json + opponent_bans_json
        // (ambas son arrays de civ names).
        $civBansRows = DB::table('civ_drafts')
            ->join('matches', 'civ_drafts.match_id', '=', 'matches.id')
            ->where('matches.season_id', $season->id)
            ->where('matches.status', GameMatch::STATUS_COMPLETED)
            ->select('civ_drafts.host_bans_json', 'civ_drafts.opponent_bans_json')
            ->get();

        $civBanCounts = [];
        foreach ($civBansRows as $r) {
            foreach (['host_bans_json', 'opponent_bans_json'] as $col) {
                $bans = is_string($r->$col) ? json_decode($r->$col, true) : $r->$col;
                foreach ($bans ?? [] as $civ) {
                    if ($civ) $civBanCounts[$civ] = ($civBanCounts[$civ] ?? 0) + 1;
                }
            }
        }
        if ($civBanCounts) {
            arsort($civBanCounts);
            $stats['top_banned_civ'] = (object) [
                'civ'   => array_key_first($civBanCounts),
                'count' => reset($civBanCounts),
            ];
        }

        // Top 1 del leaderboard (rating actual mas alto, excluyendo bot).
        $stats['top_player'] = User::where('steam_id', '!=', User::BOT_STEAM_ID)
            ->orderByDesc('rating')
            ->first();

        // Most active: mas partidas jugadas (host o opponent) en esta season.
        $stats['most_active'] = DB::table(DB::raw("(
            SELECT host_user_id as user_id FROM matches
                WHERE season_id = {$season->id} AND status = 'completed'
            UNION ALL
            SELECT opponent_user_id as user_id FROM matches
                WHERE season_id = {$season->id} AND status = 'completed'
        ) m"))
            ->select('user_id', DB::raw('COUNT(*) as plays'))
            ->groupBy('user_id')
            ->orderByDesc('plays')
            ->first();

        if ($stats['most_active']) {
            $stats['most_active_user'] = User::find($stats['most_active']->user_id);
        }

        return $stats;
    }
}
