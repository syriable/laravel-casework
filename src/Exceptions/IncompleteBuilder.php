<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use LogicException;

/**
 * Thrown by a pending-operation builder's terminal verb when a required
 * aspect was never provided (ADR-0009: builders validate at the terminal
 * call).
 */
final class IncompleteBuilder extends LogicException implements CaseworkException
{
    private function __construct(
        public readonly string $builder,
        public readonly string $missing,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missing(string $builder, string $missing): self
    {
        return new self($builder, $missing, "{$builder} requires {$missing} before its terminal call.");
    }
}
