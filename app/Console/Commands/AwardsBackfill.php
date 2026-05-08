<?php

namespace App\Console\Commands;

use App\Services\AwardService;
use Illuminate\Console\Command;

/**
 * Re-evalua awards instant para todos los users contra todas las matches
 * completed. Idempotente.
 *
 * Cuando correrlo:
 *   - Despues de inicializar la primera season (para otorgar retroactivamente
 *     "first_steps", "first_win", "centurion bronze", etc. a los testers que
 *     ya jugaron en pre-season A)
 *   - Despues de agregar un award nuevo a config/awards.php
 *   - Despues de cambiar un threshold (los users que ya califican lo reciben)
 *
 * Awards de fin de season (champion, elite) NO se backfillean acá — esos
 * solo se otorgan al cerrar una season via SeasonService.
 *
 * Ejemplo:
 *   php artisan awards:backfill
 */
class AwardsBackfill extends Command
{
    protected $signature   = 'awards:backfill';
    protected $description = 'Re-evalua todos los awards instant para todos los users';

    public function handle(AwardService $awards): int
    {
        $this->info('Backfilleando awards instant...');
        $stats = $awards->backfillAll();

        $this->info("Users procesados: {$stats['users_processed']}");
        $this->info("Awards otorgados: {$stats['awards_granted']}");
        return self::SUCCESS;
    }
}
