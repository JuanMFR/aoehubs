<?php

namespace App\Console\Commands;

use App\Models\Map;
use Illuminate\Console\Command;

/**
 * Popula la tabla `maps` con los mapas del pool actual + sus iconos.
 * Idempotente: si un mapa ya existe lo deja como esta. Asi se puede
 * correr en deploy sin pisar configuracion del admin.
 *
 * Para regenerar from scratch: php artisan maps:seed --force-reset
 */
class SeedMaps extends Command
{
    protected $signature   = 'maps:seed {--force-reset : Borra todos los mapas y recrea desde la lista hardcoded}';
    protected $description = 'Pobla la tabla maps con el pool 1v1 ranked default (idempotente)';

    /**
     * Lista canonica de mapas + sus iconos (matchea filenames en
     * public/images/maps/). El sort_order va de 10 en 10 para dejar
     * espacio a inserciones intermedias sin tener que renumerar todo.
     */
    private const SEED_MAPS = [
        ['name' => 'Arabia',        'sort_order' => 10],
        ['name' => 'Arena',         'sort_order' => 20],
        ['name' => 'Black Forest',  'sort_order' => 30],
        ['name' => 'Nomad',         'sort_order' => 40],
        ['name' => 'Hideout',       'sort_order' => 50],
        ['name' => 'Hill Fort',     'sort_order' => 60],
        ['name' => 'Acropolis',     'sort_order' => 70],
        ['name' => 'Land Madness',  'sort_order' => 80],
        ['name' => 'Mediterranean', 'sort_order' => 90],
    ];

    public function handle(): int
    {
        if ($this->option('force-reset')) {
            if (!$this->confirm('¿Borrar todos los mapas y recrear? Esto pierde toda configuracion del admin.', false)) {
                return self::SUCCESS;
            }
            Map::truncate();
            $this->warn('Tabla maps vaciada.');
        }

        $created = 0;
        $skipped = 0;
        foreach (self::SEED_MAPS as $data) {
            $slug = strtolower(str_replace(' ', '_', $data['name']));
            $iconPath = "maps/{$slug}.png";

            $map = Map::firstOrCreate(
                ['name' => $data['name']],
                [
                    'icon_path'  => $iconPath,
                    'is_active'  => true,
                    'sort_order' => $data['sort_order'],
                ],
            );

            if ($map->wasRecentlyCreated) {
                $created++;
                $this->info("  + {$data['name']}");
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Creados: {$created} · Ya existian: {$skipped}");
        return self::SUCCESS;
    }
}
