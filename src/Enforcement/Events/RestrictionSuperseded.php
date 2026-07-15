<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: restriction.superseded.
 */
final readonly class RestrictionSuperseded implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    public function __construct(
        public Restriction $restriction,
        public Restriction $replacement,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
