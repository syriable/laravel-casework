<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Enforcement\Events\RestrictionExpired;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Formalize due expirations: transitions stale active rows to
 * expired with audit + events. Correctness never depends on this — the
 * real-time rule already treats them as inactive.
 */
class ExpireRestrictions
{
    /** Rows fetched per pass — bounds memory on large backlogs (R-02). */
    private const int BATCH = 500;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly RestrictionWorkflow $workflow,
    ) {}

    public function execute(): int
    {
        $class = ModelRegistry::classFor('restriction');
        $by = ActorRef::system();
        $count = 0;

        // Each pass re-queries from the top: expiring a row removes it
        // from the due set, so the batch window naturally advances
        // without cursor bookkeeping (and tolerates rows that expire
        // between passes).
        do {
            $due = $class::query()
                ->where('state', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->limit(self::BATCH)
                ->get();

            foreach ($due as $restriction) {
                if (! $restriction instanceof Restriction) {
                    continue;
                }

                DB::transaction(function () use ($restriction, $by): void {
                    $from = Workflow::stateOf($restriction);

                    (new Workflow($this->workflow))->transition($restriction, 'expire', $by);

                    $this->recorder->record($by, 'restriction.expired', $restriction);

                    event(new RestrictionExpired($restriction, $from, Workflow::stateOf($restriction), $by));
                });

                $count++;
            }
        } while ($due->count() === self::BATCH);

        return $count;
    }
}
