<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Collection;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: appeal.overturned. Dispatches after its effects —
 * RestrictionLifted per lifted restriction — per occurrence order
 * (ADR-0015). $supersedingDecision is null when the appealed target
 * carried no original decision to supersede (a direct restriction).
 */
final readonly class AppealOverturned implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    /**
     * @param  Collection<int, Restriction>  $lifted
     */
    public function __construct(
        public Appeal $appeal,
        public ?Decision $supersedingDecision,
        public Collection $lifted,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
