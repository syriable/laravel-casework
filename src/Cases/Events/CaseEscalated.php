<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.escalated. A priority change, not a state transition.
 */
final readonly class CaseEscalated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public CaseFile $case,
        public string $fromPriority,
        public string $toPriority,
        public ActorRef $by,
    ) {}
}
