<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a decision/restriction already carries the configured
 * number of appeals (FR-503, invariant I-11). Default limit: one.
 */
final class AppealLimitReached extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $target,
        public readonly int $limit,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $target, int $limit): self
    {
        $key = $target->getKey();

        return new self($target, $limit, sprintf(
            '%s #%s has reached its appeal limit of %d.',
            $target::class,
            is_scalar($key) ? (string) $key : '?',
            $limit,
        ));
    }
}
