<?php

namespace App\Console\Commands;

use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Habilita o deshabilita al Bot Dev en la queue. Util para dev local cuando
 * querés testear el flow alone (sin otro humano). En produccion el bot esta
 * deshabilitado para que solo se rate matches reales.
 *
 * Uso:
 *   php artisan bot enable    → mete al bot en la cola permanentemente
 *   php artisan bot disable   → lo saca
 *   php artisan bot status    → muestra el estado actual
 */
class BotToggle extends Command
{
    protected $signature   = 'bot {action : enable | disable | status}';
    protected $description = 'Habilita/deshabilita el Bot Dev en la queue (solo para dev local)';

    public function handle(): int
    {
        $action = $this->argument('action');

        $bot = User::where('steam_id', User::BOT_STEAM_ID)->first();
        if ($bot === null) {
            $this->error('Bot user no existe en la DB. (Verificá que steam_id=' . User::BOT_STEAM_ID . ' exista).');
            return self::FAILURE;
        }

        $existing = QueueEntry::where('user_id', $bot->id)->first();

        switch ($action) {
            case 'enable':
                if ($existing) {
                    $this->info('Bot ya está en queue.');
                } else {
                    QueueEntry::create([
                        'user_id'   => $bot->id,
                        'is_bot'    => true,
                        // joined_at viejo a propósito para que siempre sea el primero en pairear
                        'joined_at' => '2020-01-01 00:00:00',
                    ]);
                    $this->info('Bot agregado a queue (joined_at=2020-01-01).');
                }
                return self::SUCCESS;

            case 'disable':
                if ($existing) {
                    $existing->delete();
                    $this->info('Bot quitado de queue.');
                } else {
                    $this->info('Bot ya no estaba en queue.');
                }
                return self::SUCCESS;

            case 'status':
                $this->info($existing ? 'Bot está en queue.' : 'Bot está fuera de queue.');
                return self::SUCCESS;

            default:
                $this->error("Acción inválida: '{$action}'. Usar enable | disable | status.");
                return self::FAILURE;
        }
    }
}
