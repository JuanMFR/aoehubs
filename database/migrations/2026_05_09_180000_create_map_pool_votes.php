<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de votacion comunitaria de pool de mapas.
 *
 * Concepto:
 *   - Admin marca algunos mapas como `is_fixed_in_pool` (Arabia, Arena, etc).
 *     Estan SIEMPRE en el pool, nunca son candidatos en votaciones.
 *   - Admin crea una votacion con N candidatos (mapas no fijos) y define
 *     `pool_size_voted` = cuantos top-N van al pool.
 *   - Pool final al cerrarse = mapas fijos + top-N ganadores.
 *   - Anti-repeat: el admin ve precargados solo los mapas que NO ganaron la
 *     votacion anterior (filtrado client/server al crear).
 *   - Cierre auto: cron `map-vote:close-expired` corre y al pasar `ends_at`
 *     computa ranking, activa ganadores, desactiva el resto, marca applied_at.
 *   - Voto del user: hasta `pool_size_voted` mapas; sobreescribible mientras
 *     la votacion este `open`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Flag por mapa: si es fijo (siempre en el pool) o si compite por voto.
        Schema::table('maps', function (Blueprint $table) {
            $table->boolean('is_fixed_in_pool')->default(false)->after('is_custom')
                  ->comment('si true, esta siempre en el pool y nunca es candidato a votacion');
        });

        // La eleccion en si.
        Schema::create('map_pool_votes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->comment('ej "Pool Junio 2026"');
            $table->timestamp('starts_at')->comment('los users votan desde aca');
            $table->timestamp('ends_at')->comment('cierre programado; el cron aplica al pasar este ts');
            $table->unsignedSmallInteger('pool_size_voted')
                  ->comment('cuantos top-N van al pool. Pool final = fijos + estos N');

            // open    → votacion en curso
            // closed  → ends_at pasado, ganadores aplicados al pool
            // cancelled → admin cancelo manual (eventos pro-pack, error, etc)
            $table->string('status', 20)->default('open');
            $table->timestamp('applied_at')->nullable()
                  ->comment('cuando el cron aplico ganadores al pool. null si cancelled o sin votos');

            // Snapshot de ganadores al aplicar. Lo usamos para:
            //   - mostrar resultados historicos sin recomputar ballots
            //   - filtrar candidatos de la SIGUIENTE votacion (anti-repeat)
            $table->json('winners_json')->nullable()
                  ->comment('array de map_ids que ganaron, ordenados por votos desc');

            $table->timestamps();

            // Para el cron: scan rapido de votaciones por cerrar.
            $table->index(['status', 'ends_at'], 'votes_status_ends_idx');
        });

        // Pivot: que mapas son candidatos en cada votacion.
        Schema::create('map_pool_vote_candidates', function (Blueprint $table) {
            $table->foreignId('vote_id')->constrained('map_pool_votes')->cascadeOnDelete();
            $table->foreignId('map_id')->constrained('maps')->cascadeOnDelete();
            $table->primary(['vote_id', 'map_id']);
        });

        // Voto de un user. votes_json es un array de map_ids (entre 1 y
        // pool_size_voted, todos miembros de los candidatos). El user puede
        // sobrescribirlo mientras status=open — usamos updateOrCreate por
        // (vote_id, user_id).
        Schema::create('map_pool_vote_ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained('map_pool_votes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('votes_json');
            $table->timestamps();

            $table->unique(['vote_id', 'user_id'], 'ballots_vote_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_pool_vote_ballots');
        Schema::dropIfExists('map_pool_vote_candidates');
        Schema::dropIfExists('map_pool_votes');

        Schema::table('maps', function (Blueprint $table) {
            $table->dropColumn('is_fixed_in_pool');
        });
    }
};
