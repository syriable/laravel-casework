<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: appeal.assigned. A non-transition operation —
 * assignment never moves the appeal's state.
 */
final readonly class AppealAssigned implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Appeal $appeal,
        public Model $reviewer,
        public ActorRef $by,
    ) {}
}
