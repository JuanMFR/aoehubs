<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AwardService;
use Illuminate\Console\Command;

/**
 * Otorga manualmente un award (typically con evaluator='manual') a un user.
 *
 * Ejemplos:
 *   php artisan awards:grant founder_a 76561198xxxxxxxxx
 *   php artisan awards:grant founder_a --all-active
 */
class AwardsGrant extends Command
{
    protected $signature = 'awards:grant
                            {code : codigo del award (clave en config/awards.php)}
                            {steam_id? : SteamID64 del user (omitir si --all-active)}
                            {--tier= : tier explicito (1-5). Default: el unico tier del award}
                            {--all-active : Otorgar a todos los users que jugaron al menos 1 match en una season}
                            {--season= : slug de la season para --all-active (default: la activa)}';

    protected $description = 'Otorga manualmente un award a un user (o a todos los participantes de una season)';

    public function handle(AwardService $awards): int
    {
        $code = $this->argument('code');
        $tier = $this->option('tier') ? (int) $this->option('tier') : null;

        if (!config("awards.{$code}")) {
            $this->error("Award no encontrado en config: '{$code}'");
            return self::FAILURE;
        }

        if ($this->option('all-active')) {
            return $this->grantToAllActive($awards, $code, $tier);
        }

        $steamId = $this->argument('steam_id');
        if (!$steamId) {
            $this->error('Falta steam_id (o usar --all-active).');
            return self::FAILURE;
        }

        $user = User::where('steam_id', $steamId)->first();
        if (!$user) {
            $this->error("No existe user con steam_id={$steamId}");
            return self::FAILURE;
        }

        $award = $awards->grantManual($user, $code, $tier);
        if ($award === null) {
            $this->info("'{$user->persona_name}' ya tenia el award '{$code}'. No-op.");
        } else {
            $this->info("Award '{$code}' otorgado a '{$user->persona_name}' (tier {$award->tier}).");
        }

        return self::SUCCESS;
    }

    private function grantToAllActive(AwardService $awards, string $code, ?int $tier): int
    {
        $slug = $this->option('season');
        $season = $slug
            ? \App\Models\Season::where('slug', $slug)->first()
            : \App\Models\Season::current();

        if (!$season) {
            $this->error($slug ? "No existe season con slug='{$slug}'" : 'No hay season activa.');
            return self::FAILURE;
        }

        $this->info("Otorgando '{$code}' a todos los participantes de '{$season->name}'...");

        // Users con al menos 1 match completed en la season indicada.
        $userIds = \App\Models\GameMatch::where('season_id', $season->id)
            ->where('status', 'completed')
            ->get(['host_user_id', 'opponent_user_id'])
            ->flatMap(fn ($m) => [$m->host_user_id, $m->opponent_user_id])
            ->unique();

        $granted = 0;
        $skipped = 0;
        foreach ($userIds as $uid) {
            $user = User::find($uid);
            if (!$user || $user->isBot()) continue;

            $award = $awards->grantManual($user, $code, $tier);
            if ($award) $granted++; else $skipped++;
        }

        $this->info("Otorgados: {$granted} · Ya tenian: {$skipped}");
        return self::SUCCESS;
    }
}
