<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Closure;
use Syriable\Casework\Contracts\ReportIntakeStage;
use Syriable\Casework\Reporting\ReportIntake;

/**
 * Test intake stage: auto-dismisses everything (the banned-reporter
 * use case).
 */
class AutoDismissStage implements ReportIntakeStage
{
    public function handle(ReportIntake $intake, Closure $next): ReportIntake
    {
        $intake->autoDismiss();

        return $next($intake);
    }
}
