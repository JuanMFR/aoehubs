<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el heartbeat global del match con heartbeats por jugador. El
 * cron de expiración necesita saber cuál de los dos companions sigue vivo
 * para distinguir "corte mutuo" de "uno se fue, el otro juega" — y aplicar
 * forfeit al que se fue en el segundo caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });

        Schema::table('matches', function (Blueprint $table) {
            // Último heartbeat del companion del host / opponent. Null hasta
            // que el companion empiece a heartbeatear (típicamente cuando ve
            // la match activa al iniciar).
            $table->timestamp('host_heartbeat_at')->nullable()->after('started_at');
            $table->timestamp('opponent_heartbeat_at')->nullable()->after('host_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['host_heartbeat_at', 'opponent_heartbeat_at']);
            $table->timestamp('last_heartbeat_at')->nullable()->after('started_at');
        });
    }
};
