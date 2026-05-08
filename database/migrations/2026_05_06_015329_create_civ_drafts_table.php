<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('civ_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches');
            // 4 picks de cada uno (cuál arrancó primero da igual, son simultáneos)
            $table->json('host_picks_json')->nullable();
            $table->json('opponent_picks_json')->nullable();
            // 2 bans de cada uno (sobre los picks del rival)
            $table->json('host_bans_json')->nullable();
            $table->json('opponent_bans_json')->nullable();
            // Civ final elegida tras los bans
            $table->string('host_final_civ')->nullable();
            $table->string('opponent_final_civ')->nullable();
            // Estado del draft: picking → banning → finalizing → completed
            $table->string('phase', 20)->default('picking')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civ_drafts');
    }
};
