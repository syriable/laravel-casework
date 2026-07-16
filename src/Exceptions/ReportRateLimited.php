<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a reporter has already filed
 * config('casework.reporting.reputation.rate_limit') reports within the
 * configured window. Guards against coordinated report-bombing.
 */
final class ReportRateLimited extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly Model $reporter,
        public readonly int $limit,
        public readonly int $windowMinutes,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Model $reporter, int $limit, int $windowMinutes): self
    {
        return new self($reporter, $limit, $windowMinutes, sprintf(
            'This reporter has already filed %d report(s) in the last %d minute(s).',
            $limit,
            $windowMinutes,
        ));
    }
}
