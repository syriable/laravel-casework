<?php

declare(strict_types=1);

namespace Syriable\Casework\Commands;

use Illuminate\Console\Command;
use Syriable\Casework\Enforcement\Actions\ExpireRestrictions;

/**
 * Formalize due restriction expirations (FR-404, FR-953). Schedule it
 * in the application; enforcement correctness never depends on its
 * cadence (real-time rule, I-09).
 */
final class ExpireRestrictionsCommand extends Command
{
    protected $signature = 'casework:expire-restrictions';

    protected $description = 'Transition due temporary restrictions to expired (audit + events)';

    public function handle(ExpireRestrictions $action): int
    {
        $expired = $action->execute();

        $this->info("Expired {$expired} restrictions.");

        return self::SUCCESS;
    }
}
