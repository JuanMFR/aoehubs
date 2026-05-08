<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Top 50 users por rating, mostrando rating, RD, y record W/L.
     */
    public function index(Request $request)
    {
        // Subquery con W/L counts por user (deriva de matches completed).
        $wins = DB::table('matches')
            ->select('winner_user_id as user_id', DB::raw('COUNT(*) as wins'))
            ->where('status', GameMatch::STATUS_COMPLETED)
            ->whereNotNull('winner_user_id')
            ->groupBy('winner_user_id');

        $matchesPerUser = DB::table('matches')
            ->select(DB::raw('user_id, SUM(played) as played'))
            ->fromSub(function ($q) {
                $q->from('matches')
                  ->select('host_user_id as user_id', DB::raw('1 as played'))
                  ->where('status', GameMatch::STATUS_COMPLETED)
                  ->whereNotNull('winner_user_id')
                  ->unionAll(DB::table('matches')
                      ->select('opponent_user_id as user_id', DB::raw('1 as played'))
                      ->where('status', GameMatch::STATUS_COMPLETED)
                      ->whereNotNull('winner_user_id'));
            }, 'm')
            ->groupBy('user_id');

        $users = User::query()
            ->where('steam_id', '!=', User::BOT_STEAM_ID)
            ->leftJoinSub($wins, 'w', 'w.user_id', '=', 'users.id')
            ->leftJoinSub($matchesPerUser, 'p', 'p.user_id', '=', 'users.id')
            ->select(
                'users.*',
                DB::raw('COALESCE(w.wins, 0) as wins'),
                DB::raw('COALESCE(p.played, 0) as played'),
            )
            ->orderByDesc('users.rating')
            ->limit(50)
            ->get();

        return view('leaderboard', compact('users'));
    }
}
