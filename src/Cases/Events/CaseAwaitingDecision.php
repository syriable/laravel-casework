<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.awaiting_decision.
 */
final readonly class CaseAwaitingDecision implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public CaseFile $case,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
