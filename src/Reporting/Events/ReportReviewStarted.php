<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: report.review_started.
 */
final readonly class ReportReviewStarted implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public Report $report,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
