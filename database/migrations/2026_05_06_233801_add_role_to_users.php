<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de roles minimal:
 *   - 'player' (default): user normal, puede jugar ranked
 *   - 'admin'           : acceso a /admin/* — listar usuarios, ver todas las
 *                         matches, forzar cancel, reprocesar replays, etc.
 *
 * Usamos string en vez de enum nativo porque SQLite no tiene ENUM y queremos
 * que las migraciones sean portables. Validamos en el modelo + middleware.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('player')->after('avatar_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
