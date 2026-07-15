<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Reporting\Models\Report;

/**
 * When reports become or join cases (FR-205, extension point X7).
 * Selected via config('casework.cases.strategy'): 'always', 'threshold',
 * 'manual', or an implementing class name. Runs inside report intake as
 * the System actor (FR-805).
 */
interface CaseStrategy
{
    /**
     * The case the freshly filed report should be attached to — the
     * strategy may open one — or null to leave the report unattached.
     */
    public function caseFor(Report $report): ?CaseFile;
}
