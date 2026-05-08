<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use App\Models\Season;
use App\Services\SeasonService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Crea la primera season del sistema y opcionalmente backfillea las matches
 * existentes (con season_id=NULL) hacia esta season.
 *
 * Ejecutar UNA SOLA VEZ tras desplegar las migrations de seasons. Para
 * crear seasons posteriores usar `seasons:advance`.
 *
 * Ejemplos:
 *   php artisan seasons:init
 *   php artisan seasons:init --name="Pre-season A" --slug=pre-a --backfill
 *   php artisan seasons:init --ends-at="2026-08-08"
 */
class InitSeason extends Command
{
    protected $signature = 'seasons:init
                            {--name=Pre-season A : Nombre de la season}
                            {--slug=pre-a : Slug corto para URLs}
                            {--ends-at= : Fecha de fin planificada (Y-m-d), opcional}
                            {--backfill : Asociar matches existentes (con season_id=NULL) a esta season}';

    protected $description = 'Crea la primera season y opcionalmente backfillea matches existentes';

    public function handle(SeasonService $service): int
    {
        if (Season::current() !== null) {
            $this->error('Ya existe una season activa. Para crear la siguiente usar seasons:advance.');
            return self::FAILURE;
        }

        $name = $this->option('name');
        $slug = $this->option('slug');

        $endsAt = null;
        if ($endsAtRaw = $this->option('ends-at')) {
            try {
                $endsAt = Carbon::parse($endsAtRaw)->endOfDay();
            } catch (\Exception $e) {
                $this->error("Fecha invalida: '{$endsAtRaw}'. Usar formato Y-m-d.");
                return self::FAILURE;
            }
        }

        $season = $service->init($name, $slug, $endsAt);
        $this->info("Season creada: #{$season->id} '{$season->name}' (slug={$season->slug}, ends_at=" . ($endsAt?->toDateString() ?? '—') . ')');

        if ($this->option('backfill')) {
            $orphans = GameMatch::whereNull('season_id')->count();
            if ($orphans > 0) {
                $updated = $service->backfillOrphanMatches($season);
                $this->info("Backfill: {$updated} matches asociadas a la season.");
            } else {
                $this->line('No habia matches huerfanas para backfillear.');
            }
        }

        return self::SUCCESS;
    }
}
