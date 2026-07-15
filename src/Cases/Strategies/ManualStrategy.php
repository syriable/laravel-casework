<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Strategies;

use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\CaseStrategy;
use Syriable\Casework\Reporting\Models\Report;

/**
 * config: 'manual' — reports never open or join cases automatically.
 */
class ManualStrategy implements CaseStrategy
{
    public function caseFor(Report $report): ?CaseFile
    {
        return null;
    }
}
