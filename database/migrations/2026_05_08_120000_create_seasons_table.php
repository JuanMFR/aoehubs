<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de seasons (temporadas).
 *
 * Cada season agrupa partidas dentro de una ventana temporal definida. Al
 * cerrar una season:
 *   - Se snapshottean los ratings finales en `season_stats`
 *   - Se otorgan awards de fin de season (Top 1, Top 10, Centurion, etc.)
 *   - Se aplica un "soft reset" a los ratings de todos los users (regresion
 *     a la media) y el RD vuelve al default — ver SeasonService::close()
 *
 * Estados:
 *   - upcoming → planificada, todavia no arranco
 *   - active   → corriendo, las partidas nuevas se asocian aca
 *   - closed   → cerrada, ratings ya fueron reseteados
 *
 * El admin avanza manualmente con un boton — no hay close automatico aunque
 * `ends_at` ya haya pasado. El `ends_at` es solo planning/displayed countdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 40)->unique();
            $table->string('status', 20)->default('upcoming');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('reset_config_json')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
