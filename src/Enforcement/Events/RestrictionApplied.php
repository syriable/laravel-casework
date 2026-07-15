<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: restriction.applied.
 */
final readonly class RestrictionApplied implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Restriction $restriction,
        public ActorRef $by,
    ) {}
}
