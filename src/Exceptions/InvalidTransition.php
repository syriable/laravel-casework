<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a transition is not allowed from the record's current
 * state (invariant I-03; ADR-0006).
 */
final class InvalidTransition extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $record,
        public readonly string $transition,
        public readonly string $fromState,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $record, string $transition, string $fromState): self
    {
        return new self($record, $transition, $fromState, sprintf(
            'Transition [%s] is not allowed from state [%s] on %s.',
            $transition,
            $fromState,
            $record::class,
        ));
    }
}
