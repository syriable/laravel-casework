<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: reporter.blocked. Dispatched only on the transition into
 * the blocked state — a reputation change that leaves an already-blocked
 * reporter blocked does not re-fire this.
 */
final readonly class ReporterBlocked implements ShouldDispatchAfterCommit
{
    public function __construct(
        public ReporterReputation $reputation,
        public Model $reporter,
        public int $score,
        public ActorRef $by,
    ) {}
}
