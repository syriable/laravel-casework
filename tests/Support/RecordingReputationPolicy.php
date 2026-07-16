<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Contracts\ReputationPolicy;
use Syriable\Casework\Reporting\Models\Report;

/**
 * Test policy (X14): fixed, distinctive deltas so tests can tell the
 * custom policy was actually consulted instead of the shipped default.
 */
class RecordingReputationPolicy implements ReputationPolicy
{
    public function deltaForDismissal(Report $report): int
    {
        return -5;
    }

    public function deltaForResolution(Report $report, Decision $decision): int
    {
        return 10;
    }
}
