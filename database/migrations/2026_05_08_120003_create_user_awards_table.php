<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Awards (logros / placas) que un user desbloqueo.
 *
 * Modelo "una fila por tier alcanzado" — si un user llega a Gold Centurion,
 * tiene tres filas: bronze, silver, gold (con sus respectivos awarded_at,
 * lo que permite mostrar notificaciones tipo "subiste a Silver Centurion").
 *
 * `season_id` es nullable — para awards globales no atados a una season
 * especifica (ej. "First match ever", "Founder", "Beta tester").
 *
 * `award_code` matchea las claves de config/awards.php donde se definen
 * iconos, titulos, scopes y thresholds por tier.
 *
 * `tier` es 1..5: 1=bronze, 2=silver, 3=gold, 4=platinum, 5=prismatic.
 * Para awards single-tier (ej. "Top 1 de la season") el tier siempre es 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('season_id')->nullable()->constrained();
            $table->string('award_code', 60);
            $table->unsignedTinyInteger('tier')->default(1);
            $table->timestamp('awarded_at')->useCurrent();
            $table->json('metadata_json')->nullable();

            // Indice de busqueda principal: dado un user, traer todos sus awards.
            $table->index(['user_id', 'awarded_at']);
            // Para queries del tipo "todos los users con award X de season Y".
            $table->index(['season_id', 'award_code']);
            // No usamos UNIQUE constraint con season_id por el comportamiento
            // de NULL en MySQL/MariaDB (NULL != NULL). La logica de "no
            // duplicar tier" se maneja en AwardService::grant().
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_awards');
    }
};
