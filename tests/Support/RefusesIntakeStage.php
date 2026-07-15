<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Closure;
use RuntimeException;
use Syriable\Casework\Contracts\ReportIntakeStage;
use Syriable\Casework\Exceptions\CaseworkException;
use Syriable\Casework\Reporting\ReportIntake;

/**
 * Test intake stage: refuses every report by throwing before
 * persistence.
 */
class RefusesIntakeStage implements ReportIntakeStage
{
    public function handle(ReportIntake $intake, Closure $next): ReportIntake
    {
        throw new class('refused at intake') extends RuntimeException implements CaseworkException {};
    }
}
