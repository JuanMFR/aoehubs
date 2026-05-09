<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counter denormalizado: cuantas veces este mapa fue ganador de una
 * votacion cerrada. Sirve como tiebreaker en `MapPoolVote::pickWinners()`
 * para favorecer rotacion (el mapa con menos victorias previas gana
 * empates) en lugar del orden alfabetico, que era arbitrario.
 *
 * MapPoolVote::applyToPool incrementa este counter al cerrar votaciones
 * con winners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->unsignedInteger('pool_winner_count')->default(0)->after('is_fixed_in_pool')
                  ->comment('cuantas veces fue ganador de una votacion cerrada');
        });

        // Backfill desde votaciones ya cerradas: parseamos winners_json e
        // incrementamos por map. Independiente del modelo Eloquent (raw SQL)
        // para que la migration sea robusta a refactors futuros del modelo.
        $rows = DB::table('map_pool_votes')
            ->where('status', 'closed')
            ->whereNotNull('winners_json')
            ->pluck('winners_json');

        $counts = [];
        foreach ($rows as $json) {
            $ids = is_string($json) ? json_decode($json, true) : $json;
            foreach ($ids ?? [] as $id) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        foreach ($counts as $mapId => $count) {
            DB::table('maps')->where('id', $mapId)->update(['pool_winner_count' => $count]);
        }
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->dropColumn('pool_winner_count');
        });
    }
};
