<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indices compuestos en `matches` para queries hot del backend:
 *
 *   - (host_user_id, status) y (opponent_user_id, status):
 *     match listings filtrados por user + status. Sin estos hace
 *     full-table scan + filesort. Pega en LeaderboardController,
 *     UserProfileController, MatchController::index, DashboardController
 *     (active match), Api\CompanionApiController::pendingMatch.
 *
 *   - winner_user_id:
 *     count de wins por user en LeaderboardController + W/L computations.
 *     Era table scan tambien.
 *
 * Sin breaking changes — solo agrega indexes. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->index(['host_user_id', 'status'],     'matches_host_status_idx');
            $table->index(['opponent_user_id', 'status'], 'matches_opp_status_idx');
            $table->index('winner_user_id',                'matches_winner_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_host_status_idx');
            $table->dropIndex('matches_opp_status_idx');
            $table->dropIndex('matches_winner_idx');
        });
    }
};
