<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: report.attached_to_case.
 */
final readonly class ReportAttachedToCase implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public Report $report,
        public CaseFile $case,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
