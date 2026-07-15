<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: appeal.submitted.
 */
final readonly class AppealSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Appeal $appeal,
        public ActorRef $by,
    ) {}
}
