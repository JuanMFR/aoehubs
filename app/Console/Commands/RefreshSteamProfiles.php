<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SteamProfile;
use Illuminate\Console\Command;

/**
 * Refresca persona_name + avatar_url para todos los users via Steam Web API.
 *
 * Cuando? Tipicamente:
 *   - Una sola vez despues de configurar STEAM_API_KEY en .env por primera
 *     vez (sino los users existentes nunca se actualizan hasta que se
 *     re-loguean)
 *   - Periodicamente (ej. una vez por dia) si querés que los nombres de
 *     Steam se mantengan al dia mas alla del refresh-on-login
 *
 * Excluye al bot (no es un user real de Steam).
 *
 * Ejemplo:
 *   php artisan users:refresh-profiles
 *   php artisan users:refresh-profiles --force   # ignora TTL de 24h
 */
class RefreshSteamProfiles extends Command
{
    protected $signature   = 'users:refresh-profiles {--force : Saltar el cache TTL de 24h y forzar refresh}';
    protected $description = 'Refresca persona_name y avatar_url desde Steam Web API';

    public function handle(): int
    {
        if (empty(config('services.steam.api_key'))) {
            $this->error('STEAM_API_KEY no esta configurada en .env. Sin esa key Steam Web API no responde.');
            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        $users = User::where('steam_id', '!=', User::BOT_STEAM_ID)->get();
        if ($users->isEmpty()) {
            $this->info('No hay users reales para refrescar.');
            return self::SUCCESS;
        }

        $this->info("Refrescando " . $users->count() . " user(s)" . ($force ? ' (force=true)' : '') . '...');

        $updated = $skipped = 0;
        foreach ($users as $u) {
            $before = $u->persona_name;
            SteamProfile::refresh($u, $force);
            $u->refresh();

            if ($u->persona_name !== $before) {
                $this->line("  {$u->steam_id}: '" . ($before ?? '(null)') . "' -> '{$u->persona_name}'");
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->info("Listo. Actualizados: {$updated} | Sin cambios: {$skipped}");
        return self::SUCCESS;
    }
}
