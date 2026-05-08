<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asocia cada match con la season a la que pertenece.
 *
 * Nullable porque las matches creadas antes de que existieran seasons no
 * tienen una. El comando `seasons:init` puede backfillearlas a la pre-season
 * A si se desea.
 *
 * On delete: nullOnDelete — borrar una season no borra las matches, solo
 * pierden la asociacion. Eso preserva el historial de partidas para los users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('id')
                  ->constrained()->nullOnDelete();
            $table->index('season_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropIndex(['season_id']);
            $table->dropColumn('season_id');
        });
    }
};
