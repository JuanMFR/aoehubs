<?php

namespace Database\Seeders;

use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class BotDevSeeder extends Seeder
{
    /**
     * Crea un user fantasma "Bot Dev" usado para testing solo. Vive
     * permanentemente en la queue, así cualquier user real que entre
     * a la queue queda emparejado de inmediato contra él.
     */
    public function run(): void
    {
        $bot = User::firstOrCreate(
            ['steam_id' => 'BOTDEV_PERMANENT_QUEUE'],
            ['persona_name' => 'Bot Dev', 'elo' => 1000],
        );

        QueueEntry::firstOrCreate(
            ['user_id' => $bot->id],
            ['is_bot' => true, 'joined_at' => now()],
        );
    }
}
