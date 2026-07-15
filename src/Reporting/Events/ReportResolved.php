<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: report.resolved. $decision is null when an unattached
 * report is resolved directly (catalog note).
 */
final readonly class ReportResolved implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public Report $report,
        public ?Decision $decision,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
