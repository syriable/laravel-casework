<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Syriable\Casework\Contracts\TransitionGuard;
use Syriable\Casework\States\TransitionContext;

/**
 * Test guard: records the contexts it saw and allows everything.
 */
class RecordingGuard implements TransitionGuard
{
    /** @var list<TransitionContext> */
    public static array $seen = [];

    public function check(TransitionContext $context): void
    {
        self::$seen[] = $context;
    }
}
