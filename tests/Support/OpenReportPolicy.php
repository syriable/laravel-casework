<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\Report;

/**
 * Test policy override (X12): grants dismissal to any model actor —
 * the application owns the consequences of loosening defaults.
 */
class OpenReportPolicy
{
    public function file(Model $actor): bool
    {
        return true;
    }

    public function dismiss(Model $actor, Report $report): bool
    {
        return true;
    }
}
