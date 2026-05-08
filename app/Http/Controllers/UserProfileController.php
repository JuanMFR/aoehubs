<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Season;
use App\Models\SeasonStat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pagina publica de perfil — accesible sin login. Muestra rating, W/L,
 * civs/mapas mas jugados, ultimas matches.
 *
 * URL: /users/{steamId}  (NO /users/{id} — usamos steamId porque es lo que
 * la gente conoce y lo que esta en el leaderboard).
 */
class UserProfileController extends Controller
{
    public function show(string $steamId)
    {
        $user = User::where('steam_id', $steamId)->firstOrFail();

        // W/L record
        $wins   = GameMatch::where('winner_user_id', $user->id)->where('status', 'completed')->count();
        $losses = GameMatch::where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)->orWhere('opponent_user_id', $user->id);
            })
            ->where('status', 'completed')
            ->where('winner_user_id', '!=', $user->id)
            ->whereNotNull('winner_user_id')
            ->count();
        $totalCompleted = $wins + $losses;
        $winRate        = $totalCompleted > 0 ? round(($wins / $totalCompleted) * 100) : 0;

        // Top civs jugadas. Cada match completed con civ_draft tiene el final
        // civ del user (depende de si fue host u opp). Hacemos UNION de los
        // dos sub-queries para obtener un listado plano de civs por match.
        $topCivs = DB::table('civ_drafts')
            ->join('matches', 'civ_drafts.match_id', '=', 'matches.id')
            ->where('matches.status', 'completed')
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('matches.host_user_id', $user->id)
                       ->whereNotNull('civ_drafts.host_final_civ');
                })->orWhere(function ($q2) use ($user) {
                    $q2->where('matches.opponent_user_id', $user->id)
                       ->whereNotNull('civ_drafts.opponent_final_civ');
                });
            })
            ->selectRaw("
                CASE WHEN matches.host_user_id = ?
                     THEN civ_drafts.host_final_civ
                     ELSE civ_drafts.opponent_final_civ
                END as civ,
                CASE WHEN matches.winner_user_id = ? THEN 1 ELSE 0 END as won
            ", [$user->id, $user->id])
            ->get()
            ->groupBy('civ')
            ->map(fn ($rows) => [
                'civ'      => $rows->first()->civ,
                'played'   => $rows->count(),
                'wins'     => $rows->sum('won'),
                'win_rate' => $rows->count() > 0 ? round($rows->sum('won') / $rows->count() * 100) : 0,
            ])
            ->sortByDesc('played')
            ->take(5)
            ->values();

        // Top mapas — similar, pero el mapa es uno solo por match (no depende del rol)
        $topMaps = DB::table('map_drafts')
            ->join('matches', 'map_drafts.match_id', '=', 'matches.id')
            ->where('matches.status', 'completed')
            ->whereNotNull('map_drafts.final_map')
            ->where(function ($q) use ($user) {
                $q->where('matches.host_user_id', $user->id)
                  ->orWhere('matches.opponent_user_id', $user->id);
            })
            ->selectRaw("
                map_drafts.final_map as map,
                CASE WHEN matches.winner_user_id = ? THEN 1 ELSE 0 END as won
            ", [$user->id])
            ->get()
            ->groupBy('map')
            ->map(fn ($rows) => [
                'map'      => $rows->first()->map,
                'played'   => $rows->count(),
                'wins'     => $rows->sum('won'),
                'win_rate' => $rows->count() > 0 ? round($rows->sum('won') / $rows->count() * 100) : 0,
            ])
            ->sortByDesc('played')
            ->take(5)
            ->values();

        // Ultimas matches completadas
        $recentMatches = GameMatch::with(['host', 'opponent', 'mapDraft', 'civDraft'])
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)->orWhere('opponent_user_id', $user->id);
            })
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Stats de la season actual (para mostrar prominentemente en el header).
        $currentSeason = Season::current();
        $seasonWins = 0; $seasonLosses = 0; $seasonWinRate = 0; $seasonTotal = 0;
        if ($currentSeason) {
            $seasonWins = GameMatch::where('winner_user_id', $user->id)
                ->where('status', GameMatch::STATUS_COMPLETED)
                ->where('season_id', $currentSeason->id)
                ->count();
            $seasonLosses = GameMatch::where(function ($q) use ($user) {
                    $q->where('host_user_id', $user->id)->orWhere('opponent_user_id', $user->id);
                })
                ->where('status', GameMatch::STATUS_COMPLETED)
                ->where('season_id', $currentSeason->id)
                ->where('winner_user_id', '!=', $user->id)
                ->whereNotNull('winner_user_id')
                ->count();
            $seasonTotal   = $seasonWins + $seasonLosses;
            $seasonWinRate = $seasonTotal > 0 ? round($seasonWins / $seasonTotal * 100) : 0;
        }

        // Snapshots de seasons cerradas (final_rank, plays/wins, etc.).
        $pastSeasonStats = SeasonStat::where('user_id', $user->id)
            ->with('season')
            ->whereHas('season', fn ($q) => $q->where('status', Season::STATUS_CLOSED))
            ->orderByDesc('season_id')
            ->get();

        // Estado del companion token — solo se muestra al propio user (la
        // seccion de la vista esta gateada por $isMe). last_used_at viene
        // de personal_access_tokens.last_used_at, que Sanctum actualiza
        // cuando el companion hace una request con el token.
        $companionToken = $user->tokens()->where('name', 'companion')->latest()->first();

        return view('users.show', compact(
            'user', 'wins', 'losses', 'totalCompleted', 'winRate',
            'currentSeason', 'seasonWins', 'seasonLosses', 'seasonTotal', 'seasonWinRate',
            'pastSeasonStats',
            'topCivs', 'topMaps', 'recentMatches', 'companionToken'
        ));
    }
}
