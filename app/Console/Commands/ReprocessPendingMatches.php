<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\CompanionApiController;
use App\Models\GameMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reintenta parsear los replays de matches que quedaron en
 * pending_validation. La causa típica es que mgz estaba atrasado vs el patch
 * de DE cuando subió el replay; cuando el upstream se actualice (o le metamos
 * un patch nosotros), corremos este comando y los matches se resuelven.
 *
 * Idempotente: si el parser sigue fallando, los matches quedan en
 * pending_validation. Si parsea pero la validación falla → invalid (no
 * se reintenta más). Si parsea y valida → completed (con rating aplicado).
 */
class ReprocessPendingMatches extends Command
{
    protected $signature   = 'matches:reprocess-pending {--limit=50}';
    protected $description = 'Re-run the replay parser on matches stuck in pending_validation';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $matches = GameMatch::with(['host', 'opponent', 'mapDraft', 'civDraft'])
            ->where('status', GameMatch::STATUS_PENDING_VALIDATION)
            ->whereNotNull('replay_path')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No hay matches en pending_validation.');
            return self::SUCCESS;
        }

        $still = $valid = $invalid = 0;

        foreach ($matches as $match) {
            $absolutePath = Storage::disk('local')->path($match->replay_path);
            if (! file_exists($absolutePath)) {
                $this->warn("match #{$match->id}: replay no existe en disco ({$match->replay_path})");
                continue;
            }

            $resolution = CompanionApiController::resolveReplay($match, $absolutePath);

            DB::transaction(function () use ($match, $resolution) {
                $match->update($resolution['updates']);
                if ($resolution['ratingApplied']) {
                    $match->applyRatingChange($resolution['winnerUserId']);
                }
            });

            $newStatus = $resolution['updates']['status'];
            $this->line("match #{$match->id}: {$newStatus}");

            match ($newStatus) {
                GameMatch::STATUS_COMPLETED          => $valid++,
                GameMatch::STATUS_INVALID            => $invalid++,
                GameMatch::STATUS_PENDING_VALIDATION => $still++,
                default                              => null,
            };
        }

        $this->info("Resueltos: {$valid} válidos / {$invalid} inválidos / {$still} siguen pendientes");
        return self::SUCCESS;
    }

}
