<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('elo');
            // Glicko-2: defaults estándar del paper de Glickman
            $table->float('rating')->default(1500.0)->after('avatar_url');
            $table->float('rating_deviation')->default(350.0)->after('rating');
            $table->float('rating_volatility')->default(0.06)->after('rating_deviation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_deviation', 'rating_volatility']);
            $table->integer('elo')->default(1000);
        });
    }
};
