<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users');
            $table->json('config_json');
            // pending = creada por la web, esperando que el host la inicie
            // in_progress = host entró al lobby
            // completed = el companion reportó resultado
            // abandoned = ninguno reportó / cancelada
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('winner_user_id')->nullable()->constrained('users');
            $table->string('replay_filename')->nullable();
            $table->unsignedBigInteger('replay_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
