<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Output completo de mgz al parsear el replay (winner, civs, map,
            // settings, mods, etc.). Lo guardamos para auditoría y para que el
            // matches/index pueda mostrar detalles. Null mientras no se haya
            // parseado.
            $table->json('parsed_metadata')->nullable()->after('replay_path');

            // Lista de errores que devolvió el MatchValidator. Vacío cuando
            // valid; con strings descriptivos cuando invalid.
            $table->json('validation_errors')->nullable()->after('parsed_metadata');

            // Cuándo el parser corrió con éxito (parser python no falló).
            // Distinto de status=completed: un match puede ser pending_validation
            // (parser falla por incompatibilidad de versión) por largo tiempo.
            $table->timestamp('parsed_at')->nullable()->after('validation_errors');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['parsed_metadata', 'validation_errors', 'parsed_at']);
        });
    }
};
