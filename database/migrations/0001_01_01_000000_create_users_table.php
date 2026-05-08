<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('steam_id', 20)->unique();      // SteamID64, p.ej. 76561198xxxxxxxxx
            $table->string('persona_name')->nullable();    // Nombre Steam (se llena cuando agreguemos API key)
            $table->string('avatar_url')->nullable();      // URL avatar Steam (idem)
            $table->integer('elo')->default(1000);
            $table->rememberToken();
            $table->timestamps();
        });

        // Sessions: necesario para auth de Laravel con SESSION_DRIVER=database
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
