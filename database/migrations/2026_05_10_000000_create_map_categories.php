<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de ladders por categoria de mapa.
 *
 * Concepto: cada mapa puede pertenecer a una o varias categorias
 * (ej. "Cerrados", "Agua", "Open"). Cuando un user gana un match en un
 * mapa que pertenece a la categoria X, se actualiza tanto su rating
 * GLOBAL (existente, en users.rating) como su rating EN ESA CATEGORIA
 * (nuevo, en user_category_ratings).
 *
 * El rating por categoria es Glicko-2 independiente — tiene su propio
 * RD y volatility que reflejan la incertidumbre especifica de la
 * habilidad del user en ese tipo de mapas.
 *
 * Side effects para el matchmaking: NINGUNO. El emparejamiento sigue
 * usando el rating global. Las leaderboards de categoria son visuales
 * ("podes ser top de agua aunque no seas top global").
 *
 * Las filas en user_category_ratings se crean lazy: la primera vez que
 * un user juega un match en una categoria. Sin retroactividad — matches
 * historicos no se backfillean (documentado en PENDING.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique()
                  ->comment('display name, ej "Cerrados", "Agua"');
            $table->string('slug', 60)->unique()
                  ->comment('URL-friendly, ej "closed", "water" — usado en /leaderboard?category=...');
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable()
                  ->comment('relative a public/images/, ej "categories/closed.png"');
            $table->integer('sort_order')->default(999);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Pivot: que mapas pertenecen a que categorias. Many-to-many.
        Schema::create('map_category', function (Blueprint $table) {
            $table->foreignId('map_id')->constrained('maps')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('map_categories')->cascadeOnDelete();
            $table->primary(['map_id', 'category_id']);
        });

        // Rating del user en cada categoria — analogo a users.rating/rd/vol
        // pero per-categoria. Se crea on-demand al primer match en esa cat.
        Schema::create('user_category_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('map_categories')->cascadeOnDelete();
            // Defaults Glicko-2 estandar (mismos que users):
            //   rating=1500, RD=350 (max uncertainty), vol=0.06
            $table->float('rating')->default(1500);
            $table->float('rating_deviation')->default(350);
            $table->float('rating_volatility')->default(0.06);
            $table->unsignedInteger('matches_played')->default(0);
            $table->timestamps();

            // 1 sola fila por (user, category)
            $table->unique(['user_id', 'category_id'], 'ucr_user_cat_unique');
            // Para leaderboard: SELECT users JOIN ucr WHERE category_id=? ORDER BY rating DESC
            $table->index(['category_id', 'rating'], 'ucr_cat_rating_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_category_ratings');
        Schema::dropIfExists('map_category');
        Schema::dropIfExists('map_categories');
    }
};
