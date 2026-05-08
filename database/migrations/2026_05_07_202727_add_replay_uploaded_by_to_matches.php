<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de quien subio el replay actualmente activo. Necesario para que
 * el OTRO participante pueda overridearlo si el primero quedo invalid/
 * pending_validation (caso clasico: A alt+f4 mid-game, su replay parcial
 * sube como invalid; B con su replay completo lo sobreescribe y resuelve).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('replay_uploaded_by')->nullable()->after('replay_path')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replay_uploaded_by');
        });
    }
};
