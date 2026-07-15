<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

use Syriable\Casework\States\TransitionContext;

/**
 * A transition precondition (ADR-0012). Guards are container-resolved and
 * individually rebindable (extension point X13); they veto by throwing a
 * domain exception implementing CaseworkException.
 */
interface TransitionGuard
{
    public function check(TransitionContext $context): void;
}
