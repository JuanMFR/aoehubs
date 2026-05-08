<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Models\User;
use App\Services\SeasonService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Cierra la season activa y abre la siguiente. Workflow completo:
 *
 *   1. Snapshotea las stats finales de cada user en `season_stats`
 *   2. Calcula final_rank por rating descendente
 *   3. Aplica soft reset a los ratings vivos (formula: base + (old-base)*factor)
 *   4. Resetea RD al default Glicko (350) y volatility al default (0.06)
 *   5. Crea la nueva season en estado active
 *
 * NO otorga awards de fin de season — eso lo hace `awards:evaluate-end-of-season`
 * por separado (todavia no implementado, fase C).
 *
 * Pide confirmacion interactiva. Para skipearla en automatizaciones usar --force.
 *
 * Ejemplos:
 *   php artisan seasons:advance --name="Pre-season B" --slug=pre-b
 *   php artisan seasons:advance --name="Season 1" --slug=s1 --ends-at=2026-11-08
 *   php artisan seasons:advance --name="Season 1" --slug=s1 --factor=0.5
 */
class AdvanceSeason extends Command
{
    protected $signature = 'seasons:advance
                            {--name= : Nombre de la nueva season (requerido)}
                            {--slug= : Slug de la nueva season (requerido)}
                            {--ends-at= : Fecha de fin planificada (Y-m-d), opcional}
                            {--factor=0.4 : Factor del soft reset (0=reset total, 1=sin reset)}
                            {--base=1500 : Rating base al que regresar parcialmente}
                            {--force : Skipear el prompt de confirmacion}';

    protected $description = 'Cierra la season activa, snapshotea stats, soft-resetea ratings y abre la siguiente';

    public function handle(SeasonService $service): int
    {
        $current = Season::current();
        if ($current === null) {
            $this->error('No hay season activa. Usar seasons:init para crear la primera.');
            return self::FAILURE;
        }

        $name = $this->option('name');
        $slug = $this->option('slug');
        if (!$name || !$slug) {
            $this->error('Faltan --name y --slug para la nueva season.');
            return self::FAILURE;
        }

        $endsAt = null;
        if ($endsAtRaw = $this->option('ends-at')) {
            try {
                $endsAt = Carbon::parse($endsAtRaw)->endOfDay();
            } catch (\Exception $e) {
                $this->error("Fecha invalida: '{$endsAtRaw}'. Usar Y-m-d.");
                return self::FAILURE;
            }
        }

        $resetConfig = [
            'base'   => (float) $this->option('base'),
            'factor' => (float) $this->option('factor'),
        ];

        // Resumen de impacto antes de pedir confirmacion.
        $userCount = User::where('steam_id', '!=', User::BOT_STEAM_ID)->count();
        $matchCount = $current->matches()->where('status', \App\Models\GameMatch::STATUS_COMPLETED)->count();

        $this->newLine();
        $this->line("<comment>Vas a cerrar:</comment> #{$current->id} '{$current->name}'");
        $this->line("<comment>Y abrir:</comment>     '{$name}' (slug={$slug})");
        $this->newLine();
        $this->line("<comment>Impacto:</comment>");
        $this->line("  - {$matchCount} matches completed seran snapshotteadas a season_stats");
        $this->line("  - {$userCount} users tendran su rating resetado con factor={$resetConfig['factor']}, base={$resetConfig['base']}");
        $this->line("  - RD volvera a 350, volatility a 0.06");
        if ($endsAt) {
            $this->line("  - La nueva season terminara el {$endsAt->toDateString()}");
        }
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Confirmar?', false)) {
            $this->info('Cancelado.');
            return self::SUCCESS;
        }

        $next = $service->closeAndStartNext($current, $name, $slug, $endsAt, $resetConfig);

        $this->info("Season #{$current->id} cerrada.");
        $this->info("Season #{$next->id} '{$next->name}' activa.");
        return self::SUCCESS;
    }
}
