<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool de mapas configurable desde el panel de admin.
 *
 * Reemplaza la constante hardcodeada Matchmaking::MAP_POOL. Beneficios:
 *   - Activar/desactivar mapas sin tocar codigo
 *   - Reordenar via sort_order
 *   - Asociar metadata del replay (rms_map_id) cuando se sube uno de muestra
 *
 * `name` es el nombre canonico ingles que retorna el parser de replays
 * (scripts/parse_replay.py + aocref). Se compara case-sensitive contra
 * el `map_name` del replay parseado para validar partidas.
 *
 * Para mostrar el nombre en español, los blade views siguen usando __()
 * con lang/es.json — el admin puede agregar/editar entradas ahí cuando
 * crea un mapa nuevo (o se renderiza el canonical en ingles si no hay
 * traduccion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique()
                  ->comment('canonical English name from replay parser, ej. Black Forest');
            $table->string('icon_path')->nullable()
                  ->comment('relative path under public/images/, ej. maps/black_forest.png');
            $table->unsignedInteger('rms_map_id')->nullable()
                  ->comment('numeric map ID from replay (rms_map_id field)');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
