<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\CaseStrategy;
use Syriable\Casework\Reporting\Models\Report;

/**
 * Test strategy (X7): records that the configured class was consulted
 * and opens no case.
 */
class RecordingStrategy implements CaseStrategy
{
    /** @var list<int|string> */
    public static array $consultedFor = [];

    public function caseFor(Report $report): ?CaseFile
    {
        $key = $report->getKey();

        self::$consultedFor[] = is_scalar($key) ? (is_int($key) ? $key : (string) $key) : 0;

        return null;
    }
}
