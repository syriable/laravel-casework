<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Closure;
use Syriable\Casework\Contracts\ReportIntakeStage;
use Syriable\Casework\Reporting\ReportIntake;

/**
 * Test intake stage: returns without calling $next — later stages must
 * never run (extension guarantee #4).
 */
class ShortCircuitStage implements ReportIntakeStage
{
    public function handle(ReportIntake $intake, Closure $next): ReportIntake
    {
        $intake->metadata['short_circuited'] = true;

        return $intake;
    }
}
