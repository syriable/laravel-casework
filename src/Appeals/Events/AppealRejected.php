<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: appeal.rejected. Reachable from `submitted` (administrative
 * rejection) and `under_review`.
 */
final readonly class AppealRejected implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public Appeal $appeal,
        public ?string $reason,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
