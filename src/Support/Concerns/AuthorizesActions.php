<?php

declare(strict_types=1);

namespace Syriable\Casework\Support\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Support\ActorRef;

/**
 * The authorize step of the action pipeline (ADR-0005, FR-601): model
 * actors pass through Gate policies; System attribution acts with
 * system authority (FR-805) and anonymous origins are authorized by
 * the specific action's own rules.
 */
trait AuthorizesActions
{
    /**
     * @throws AuthorizationException
     */
    private function authorize(ActorRef $by, string $ability, mixed $arguments): void
    {
        if ($by->actor === null) {
            return;
        }

        Gate::forUser($by->actor)->authorize($ability, $arguments);
    }
}
