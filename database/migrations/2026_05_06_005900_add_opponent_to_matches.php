<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // El user que se une como joiner. El que crea el lobby es host_user_id.
            // Nullable para retro-compatibilidad con matches viejas.
            $table->foreignId('opponent_user_id')->nullable()->after('host_user_id')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opponent_user_id');
        });
    }
};
