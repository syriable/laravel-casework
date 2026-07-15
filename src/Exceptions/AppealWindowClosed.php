<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when an appeal is submitted after the configured window has
 * elapsed (FR-506, invariant I-11). The window is measured in days from
 * the decision/restriction's creation; null disables it.
 */
final class AppealWindowClosed extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $target,
        public readonly int $windowDays,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $target, int $windowDays): self
    {
        $key = $target->getKey();

        return new self($target, $windowDays, sprintf(
            'The %d-day appeal window for %s #%s has closed.',
            $windowDays,
            $target::class,
            is_scalar($key) ? (string) $key : '?',
        ));
    }
}
