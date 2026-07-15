<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: appeal.review_started.
 */
final readonly class AppealReviewStarted implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public Appeal $appeal,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
