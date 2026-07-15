<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when the same reporter already has an open report on the same
 * subject for the same reason (FR-105, invariant I-02). Disable via
 * config('casework.reporting.allow_duplicates').
 */
final class DuplicateReport extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $reporter,
        public readonly Model $subject,
        public readonly Model $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $reporter, Model $subject, Model $reason): self
    {
        $key = $reason->getAttribute('key');

        return new self($reporter, $subject, $reason, sprintf(
            'An open report by this reporter already exists for %s with reason [%s].',
            $subject::class,
            is_string($key) ? $key : 'unknown',
        ));
    }
}
