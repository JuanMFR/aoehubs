<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches');
            // Quién banea primero — random al pairing
            $table->foreignId('starting_user_id')->constrained('users');
            // bans en orden: [{user_id, map_name}, ...]
            // Inferimos turno actual a partir del count: si bans.length es par,
            // turno del starting_user; si es impar, del otro.
            $table->json('bans_json')->default('[]');
            $table->string('final_map')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_drafts');
    }
};
