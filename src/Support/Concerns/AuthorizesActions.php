<?php

declare(strict_types=1);

namespace Syriable\Casework\Support\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Support\ActorRef;

/**
 * The authorize step of the action pipeline (ADR-0005, FR-601): model
 * actors pass through Gate policies; System attribution acts with
 * system authority and anonymous origins are authorized by
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

    /**
     * Policy check plus scope enforcement: when the resolver
     * scopes the actor and the subject belongs to a scope outside that
     * set, the operation is denied regardless of policy grants.
     *
     * @throws AuthorizationException
     */
    private function authorizeInScope(ActorRef $by, string $ability, mixed $arguments, ?Model $subject): void
    {
        $this->authorize($by, $ability, $arguments);

        if ($by->actor === null || ! $subject instanceof Model) {
            return;
        }

        $resolver = app(ScopeResolver::class);

        $scopes = $resolver->scopesFor($by->actor);
        $subjectScope = $resolver->scopeOf($subject);

        if ($scopes !== null && $subjectScope !== null && ! in_array($subjectScope, $scopes, true)) {
            throw new AuthorizationException(
                "This action is outside the actor's moderation scopes.",
            );
        }
    }
}
