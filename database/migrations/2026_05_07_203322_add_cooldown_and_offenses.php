<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema anti-griefing minimal:
 *
 *   - users.cooldown_until: si está seteado a futuro, el user no puede entrar
 *     a queue. Lo setea CooldownService cuando detecta una ofensa.
 *   - match_offenses: registro inmutable de cada incidente. Con esto contamos
 *     ofensas en una ventana móvil (últimas 24h) para escalar el cooldown.
 *
 * Tipos de ofensas registradas:
 *   - 'lobby_abort'        → user abortó el lobby antes de empezar
 *   - 'mid_game_disconnect'→ user dejó de heartbeatear durante in_progress
 *                            (= forfeit aplicado en su contra)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('cooldown_until')->nullable()->after('pings_updated_at');
        });

        Schema::create('match_offenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('match_id')->constrained('matches');
            $table->string('kind', 40);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_offenses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cooldown_until');
        });
    }
};
