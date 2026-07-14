<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when an immutable record (decision, note, evidence, audit entry)
 * is updated or deleted, or when a lifecycle state column is written
 * directly instead of through a transition (ADR-0003, invariant I-03/I-07).
 */
final class ImmutableRecord extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $record,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function mutationAttempted(Model $record): self
    {
        return new self($record, sprintf(
            '%s records are immutable; corrections are new records (ADR-0003).',
            $record::class,
        ));
    }

    public static function stateWriteAttempted(Model $record): self
    {
        return new self($record, sprintf(
            'The state of %s may only change through a workflow transition (invariant I-03).',
            $record::class,
        ));
    }
}
