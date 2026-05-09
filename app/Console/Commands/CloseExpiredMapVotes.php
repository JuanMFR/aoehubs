<?php

namespace App\Console\Commands;

use App\Models\MapPoolVote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cierra automaticamente las votaciones de pool de mapas que pasaron
 * `ends_at` y aplica los ganadores al pool. Se schedulea cada minuto en
 * routes/console.php.
 *
 * Cada `applyToPool()` corre dentro de su propia transaction; si una falla
 * (ej. DB lock, race con un admin clickeando "aplicar ahora"), el output
 * lo loguea pero no aborta el resto del scan.
 */
class CloseExpiredMapVotes extends Command
{
    protected $signature   = 'map-vote:close-expired';
    protected $description = 'Cierra votaciones de pool expiradas y aplica los ganadores al pool de mapas';

    public function handle(): int
    {
        $expired = MapPoolVote::where('status', MapPoolVote::STATUS_OPEN)
            ->where('ends_at', '<=', now())
            ->orderBy('ends_at')
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($expired as $vote) {
            try {
                $winners = $vote->applyToPool();
                $count   = count($winners);
                $msg     = $count > 0
                    ? "Vote #{$vote->id} '{$vote->name}' aplicado: {$count} ganadores."
                    : "Vote #{$vote->id} '{$vote->name}' cerrado SIN votos — pool sin cambios.";

                $this->info($msg);
                Log::info($msg);
            } catch (\Throwable $e) {
                $err = "Error aplicando vote #{$vote->id}: " . $e->getMessage();
                $this->error($err);
                Log::error($err, ['exception' => $e]);
            }
        }

        return self::SUCCESS;
    }
}
