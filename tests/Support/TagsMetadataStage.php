<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Closure;
use Syriable\Casework\Contracts\ReportIntakeStage;
use Syriable\Casework\Reporting\ReportIntake;

/**
 * Test intake stage: enriches metadata (the external-scoring use case).
 */
class TagsMetadataStage implements ReportIntakeStage
{
    public function handle(ReportIntake $intake, Closure $next): ReportIntake
    {
        $intake->metadata['spam_score'] = 0.97;

        return $next($intake);
    }
}
