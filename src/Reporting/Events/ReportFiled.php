<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: report.filed. After-commit dispatch per ADR-0015.
 */
final readonly class ReportFiled implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Report $report,
        public ActorRef $by,
    ) {}
}
