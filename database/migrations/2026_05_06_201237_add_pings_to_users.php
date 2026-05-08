<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Pings que reportó el companion del user a cada server de AoE2.
            // Estructura: { "westeurope": 80, "eastus": 145, ... } (ms enteros).
            // Las regiones donde el ping falló (timeout, host no resuelve) no
            // aparecen — el matchmaker descarta esas al calcular el server óptimo.
            $table->json('pings_json')->nullable()->after('rating_volatility');
            $table->timestamp('pings_updated_at')->nullable()->after('pings_json');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pings_json', 'pings_updated_at']);
        });
    }
};
