<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de las estadisticas de un user al cierre de una season.
 *
 * Se popula en el momento del `seasons:close`. Antes de ese cierre la fila
 * no existe — el rating "vivo" del user esta en `users.rating`.
 *
 * `final_rank` es 1-based (1 = Top 1 de la season). NULL si el user no jugo
 * suficientes partidas como para entrar al ranking (ver SeasonService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('season_id')->constrained();
            $table->float('final_rating');
            $table->float('final_rd');
            $table->float('peak_rating');
            $table->unsignedInteger('final_rank')->nullable();
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'season_id']);
            $table->index(['season_id', 'final_rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_stats');
    }
};
