<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // El "ID de juego" que muestra AoE2 en el lobby (ej. 475684609).
            // Lo reporta el companion del host una vez que entró al lobby room,
            // y lo consume el companion del joiner para abrir aoe2de://0/{id}.
            $table->string('lobby_id', 20)->nullable()->after('config_json');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('lobby_id');
        });
    }
};
