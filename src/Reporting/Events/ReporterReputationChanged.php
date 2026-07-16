<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Support\ActorRef;

/**
 * A reporter's reputation score moved (extension point X14). Dispatched
 * by AdjustReporterReputation after commit (ADR-0015). $reason is the
 * audit action key that triggered the change ("report.dismissed",
 * "report.resolved", or "reporter.reputation.manual_adjustment").
 */
final readonly class ReporterReputationChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public ReporterReputation $reputation,
        public Model $reporter,
        public int $before,
        public int $after,
        public string $reason,
        public ?Report $report,
        public ActorRef $by,
    ) {}
}
