<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\MapCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Top 50 users por rating, mostrando rating, RD, y record W/L.
     *
     * Acepta ?category=<slug> para filtrar por una ladder de categoria.
     * En modo categoria:
     *   - El rating mostrado es el de user_category_ratings (no global).
     *   - Solo aparecen users que tienen al menos un match en esa categoria
     *     (tienen fila ucr).
     *   - W/L y partidas siguen siendo globales — el conteo per-categoria
     *     requiere joins extra que no compensan para v1. matches_played
     *     del ucr se muestra como "Matches en cat".
     */
    public function index(Request $request)
    {
        $categories      = MapCategory::active()->ordered()->get();
        $categorySlug    = $request->query('category');
        $activeCategory  = $categorySlug
            ? $categories->firstWhere('slug', $categorySlug)
            : null;

        // Subquery con W/L counts por user (deriva de matches completed).
        // Es global aun en vista filtrada — ver comment del docblock.
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

        $query = User::query()
            ->where('users.steam_id', '!=', User::BOT_STEAM_ID)
            ->leftJoinSub($wins, 'w', 'w.user_id', '=', 'users.id')
            ->leftJoinSub($matchesPerUser, 'p', 'p.user_id', '=', 'users.id')
            ->with(['awards' => function ($q) {
                $q->whereIn('tier', [4, 5])->orderByDesc('tier');
            }]);

        if ($activeCategory !== null) {
            // INNER join: solo users con rating en la categoria. Order por
            // rating de la categoria, no global.
            $query->join('user_category_ratings as ucr', function ($j) use ($activeCategory) {
                $j->on('ucr.user_id', '=', 'users.id')
                  ->where('ucr.category_id', '=', $activeCategory->id);
            })
            ->select(
                'users.*',
                'ucr.rating as cat_rating',
                'ucr.rating_deviation as cat_rd',
                'ucr.matches_played as cat_matches',
                DB::raw('COALESCE(w.wins, 0) as wins'),
                DB::raw('COALESCE(p.played, 0) as played'),
            )
            ->orderByDesc('ucr.rating');
        } else {
            $query->select(
                'users.*',
                DB::raw('COALESCE(w.wins, 0) as wins'),
                DB::raw('COALESCE(p.played, 0) as played'),
            )
            ->orderByDesc('users.rating');
        }

        $users = $query->limit(50)->get();

        return view('leaderboard', compact('users', 'categories', 'activeCategory'));
    }
}
