<?php

declare(strict_types=1);

namespace Syriable\Casework\States\Events;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\ActorRef;

/**
 * Dispatched only for application-defined custom transitions (ADR-0013
 * rule 4; event catalog §Generic). Core transitions carry their own
 * dedicated event classes, dispatched by their actions.
 */
final readonly class StateTransitioned
{
    public function __construct(
        public Model $subject,
        public string $transition,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
