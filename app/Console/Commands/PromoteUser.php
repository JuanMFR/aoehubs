<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Cambia el role de un user identificado por steam_id.
 *
 * Ejemplos:
 *   php artisan users:promote 76561198xxxxxxxxx              # → admin
 *   php artisan users:promote 76561198xxxxxxxxx --role=admin
 *   php artisan users:promote 76561198xxxxxxxxx --role=player
 */
class PromoteUser extends Command
{
    protected $signature   = 'users:promote {steam_id : SteamID64 del user} {--role=admin : Role a asignar (player|admin)}';
    protected $description = 'Cambiar el role de un user (player|admin)';

    public function handle(): int
    {
        $steamId = $this->argument('steam_id');
        $role    = $this->option('role');

        if (! in_array($role, User::ROLES, true)) {
            $this->error("Role invalido: '{$role}'. Validos: " . implode(', ', User::ROLES));
            return self::FAILURE;
        }

        $user = User::where('steam_id', $steamId)->first();
        if ($user === null) {
            $this->error("No existe user con steam_id={$steamId}");
            return self::FAILURE;
        }

        $oldRole = $user->role;
        if ($oldRole === $role) {
            $this->info("User '{$user->persona_name}' ({$steamId}) ya es {$role}.");
            return self::SUCCESS;
        }

        $user->update(['role' => $role]);

        $this->info("User '{$user->persona_name}' ({$steamId}): {$oldRole} → {$role}");
        return self::SUCCESS;
    }
}
