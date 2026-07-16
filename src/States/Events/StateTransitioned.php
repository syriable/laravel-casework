<?php

declare(strict_types=1);

namespace Syriable\Casework\States\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\ActorRef;

/**
 * Dispatched only for application-defined custom transitions
 * (ADR-0019). Core transitions carry their own dedicated event
 * classes, dispatched by their actions. After-commit dispatch per
 * ADR-0015.
 */
final readonly class StateTransitioned implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Model $subject,
        public string $transition,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
