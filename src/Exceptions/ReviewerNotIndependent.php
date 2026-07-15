<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when the appeal reviewer is the actor who made the appealed
 * decision or issued the appealed restriction (FR-505, invariant I-12).
 * Disable via config('casework.appeals.require_independent_reviewer').
 */
final class ReviewerNotIndependent extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $reviewer,
        public readonly Model $appeal,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $reviewer, Model $appeal): self
    {
        $key = $appeal->getKey();

        return new self($reviewer, $appeal, sprintf(
            'The reviewer for appeal #%s must differ from the original decider/issuer (I-12).',
            is_scalar($key) ? (string) $key : '?',
        ));
    }
}
