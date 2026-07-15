<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: warning.issued.
 */
final readonly class WarningIssued implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Warning $warning,
        public ActorRef $by,
    ) {}
}
