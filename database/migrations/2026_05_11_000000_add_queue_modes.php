<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queue modes — el user elige qué tipo de match acepta.
 *
 *   full_draft        → como antes: drafts completos de mapa y civ.
 *   random_civ_map    → mapa draftado, civ = "Random" en lobby (skipear civ
 *                       validation en el rec).
 *   random_civ_arabia → sin drafts; mapa = Arabia, civ = "Random".
 *
 * Cada user puede aceptar 1..3 modos. Para emparejar dos players, sus sets
 * tienen que intersectar; el modo del match es el "mas restrictivo" (menos
 * drafts) del overlap.
 *
 * Default para users existentes: los 3 modos (no rompe el flow actual; un
 * user que no toca settings se empareja como antes pero puede caer en un
 * modo random si su rival lo prefiere).
 *
 * Rating: mismo Glicko-2 para todos los modos, sin leaderboard separado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // JSON array de modos aceptados. Default = los 3.
            // Validado a nivel app (debe contener al menos uno; valores
            // restringidos a los modos conocidos).
            $table->json('accepted_modes_json')->nullable()->after('rating_volatility')
                  ->comment('queue modes que el user acepta. null/default = los 3.');

            // Flag para mostrar el tour onboarding la primera vez. Apenas el
            // user cambia su preferencia (o cierra el tour), se setea true.
            $table->boolean('queue_modes_tour_seen')->default(false)->after('accepted_modes_json');
        });

        Schema::table('matches', function (Blueprint $table) {
            // Modo con el que se armo este match. Null en filas legacy
            // (asumimos full_draft en ese caso, ver GameMatch::mode accessor).
            $table->string('mode', 24)->nullable()->after('status')
                  ->comment('full_draft | random_civ_map | random_civ_arabia');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['accepted_modes_json', 'queue_modes_tour_seen']);
        });
    }
};
