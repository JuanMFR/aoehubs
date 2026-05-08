<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Snapshot del rating de cada participante ANTES de la match,
            // y el cambio que produjo el resultado. Esto permite mostrar
            // el delta sin tener que recalcular y mantiene un histórico
            // estable aunque cambien los ratings actuales del user.
            $table->float('host_rating_before')->nullable()->after('lobby_id');
            $table->float('host_rating_change')->nullable()->after('host_rating_before');
            $table->float('opponent_rating_before')->nullable()->after('host_rating_change');
            $table->float('opponent_rating_change')->nullable()->after('opponent_rating_before');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'host_rating_before', 'host_rating_change',
                'opponent_rating_before', 'opponent_rating_change',
            ]);
        });
    }
};
