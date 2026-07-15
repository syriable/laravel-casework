<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Closure;
use Syriable\Casework\Contracts\ReportIntakeStage;
use Syriable\Casework\Reporting\ReportIntake;

/**
 * Test intake stage: suppresses case creation regardless of strategy.
 */
class SuppressCaseStage implements ReportIntakeStage
{
    public function handle(ReportIntake $intake, Closure $next): ReportIntake
    {
        $intake->suppressCase();

        return $next($intake);
    }
}
