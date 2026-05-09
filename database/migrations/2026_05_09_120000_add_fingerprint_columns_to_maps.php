<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasamos `maps` a un modelo de fingerprint completo asi la validacion de un
 * replay (MatchValidator) deja de depender del nombre y empieza a depender de
 * un identificador estructural del .aoe2record.
 *
 * Para mapas vanilla (built-in del juego), el fingerprint es `rms_map_id`:
 * un entero hardcoded en el codigo de DE (Arabia=9, Nomad=33, ...) que NO
 * varia entre clientes ni se ensucia con map packs del Workshop.
 *
 * Para mapas custom (un pack distribuido por nosotros, ej "semana de pro
 * maps"), `rms_map_id` no alcanza porque suele ser un sentinel compartido
 * entre todos los mapas custom (CUSTOM=59). Se valida por `rms_filename`
 * (nombre estable del .rms si la distribucion la controlamos nosotros) y
 * opcionalmente `rms_hash` (sha256 del contenido del .rms si lo hasheamos
 * al subirlo, para detectar tampering del cliente).
 *
 * `is_custom` decide la estrategia de matching en runtime — preferimos un
 * flag explicito en la fila a inferir "if rms_map_id == 59" porque el
 * sentinel CUSTOM no es estable entre versiones de DE/mgz.
 *
 * `name_es` y `name_en` son los display names traducibles. Por ahora la UI
 * sigue traduciendo via lang/es.json (legacy), pero al popularse estos
 * campos los vamos a preferir en una pasada posterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            // Fingerprint para custom maps (vanilla los identifica rms_map_id solo).
            $table->string('rms_filename', 120)->nullable()->after('rms_map_id')
                  ->comment('archivo .rms del replay — necesario para validar mapas custom');
            $table->string('rms_hash', 64)->nullable()->after('rms_filename')
                  ->comment('sha256 hex del contenido del .rms — opcional, para integridad de pro-maps');

            // Strategy flag: si false → comparar rms_map_id; si true → comparar rms_filename (+ rms_hash si existe).
            $table->boolean('is_custom')->default(false)->after('rms_hash')
                  ->comment('false = built-in del juego (validar por rms_map_id), true = pack custom (validar por rms_filename)');

            // Display names traducibles. Por ahora opcionales; futuro switch de locale.
            $table->string('name_es', 60)->nullable()->after('name');
            $table->string('name_en', 60)->nullable()->after('name_es');

            // Index para resolver rms_map_id → map row en MatchValidator.
            $table->index('rms_map_id', 'maps_rms_map_id_idx');
        });

        // Backfill: las filas existentes son todas vanilla, ya tienen rms_map_id.
        // Copiamos `name` (canonical EN del parser) a `name_en` y a `name_es`
        // como fallback inicial — el admin puede editarlos despues si quiere
        // un fantasy name distinto.
        \DB::statement("UPDATE maps SET name_es = name, name_en = name WHERE name_es IS NULL");
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->dropIndex('maps_rms_map_id_idx');
            $table->dropColumn(['rms_filename', 'rms_hash', 'is_custom', 'name_es', 'name_en']);
        });
    }
};
