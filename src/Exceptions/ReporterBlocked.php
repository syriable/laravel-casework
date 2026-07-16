<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a reporter whose reputation score is at or below
 * config('casework.reporting.reputation.block_threshold') attempts to
 * file a new report.
 */
final class ReporterBlocked extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $reporter,
        public readonly int $score,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $reporter, int $score): self
    {
        return new self($reporter, $score, sprintf(
            'This reporter is blocked from filing new reports (reputation score %d).',
            $score,
        ));
    }
}
