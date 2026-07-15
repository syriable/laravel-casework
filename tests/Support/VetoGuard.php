<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use RuntimeException;
use Syriable\Casework\Contracts\TransitionGuard;
use Syriable\Casework\Exceptions\CaseworkException;
use Syriable\Casework\States\TransitionContext;

/**
 * Test guard: vetoes every transition it guards.
 */
class VetoGuard implements TransitionGuard
{
    public function check(TransitionContext $context): void
    {
        throw new class('vetoed') extends RuntimeException implements CaseworkException {};
    }
}
