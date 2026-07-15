<?php

declare(strict_types=1);

namespace Syriable\Casework\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Opt-in audit pruning (FR-705) — the single documented exception to
 * audit immutability (ADR-0003), operating via bulk delete so no model
 * API is involved. Refuses to run unless retention was chosen: either
 * --before or config('casework.audit.prune_after_days').
 */
final class PruneAuditCommand extends Command
{
    protected $signature = 'casework:prune-audit
        {--before= : Prune entries created before this date (overrides the configured retention)}';

    protected $description = 'Prune audit entries older than the configured retention (opt-in)';

    public function handle(): int
    {
        $cutoff = $this->cutoff();

        if (! $cutoff instanceof Carbon) {
            $this->error(
                'Audit pruning is opt-in: pass --before= or set casework.audit.prune_after_days.',
            );

            return self::FAILURE;
        }

        $class = ModelRegistry::classFor('audit_entry');

        // Bulk query delete: bypasses Eloquent model events deliberately —
        // PreventsMutation guards the model API, not retention (FR-705).
        $deleted = $class::query()->where('created_at', '<', $cutoff)->delete();
        $pruned = is_int($deleted) ? $deleted : 0;

        $this->info("Pruned {$pruned} audit entries created before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }

    private function cutoff(): ?Carbon
    {
        $before = $this->option('before');

        if (is_string($before) && $before !== '') {
            return Carbon::parse($before);
        }

        $days = config('casework.audit.prune_after_days');

        return is_int($days) ? now()->subDays($days) : null;
    }
}
