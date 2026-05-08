<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Cuando el FileSystemWatcher del companion detectó que AoE2 escribió
            // el .aoe2record (= partida realmente arrancó). Antes de esto el match
            // está en 'pending' (lobby armado pero nadie le dio Iniciar).
            $table->timestamp('started_at')->nullable()->after('status');

            // Ultimo heartbeat del companion. Si no llega uno en N minutos y el
            // match sigue activo, el cron lo marca como 'abandoned'.
            $table->timestamp('last_heartbeat_at')->nullable()->after('started_at');

            // Path relativo dentro del disco 'local' donde guardamos el replay
            // subido (ej: replays/match_42_1715000000.aoe2record).
            $table->string('replay_path')->nullable()->after('replay_size');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'last_heartbeat_at', 'replay_path']);
        });
    }
};
