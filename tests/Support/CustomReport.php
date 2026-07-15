<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Syriable\Casework\Reporting\Models\Report;

/**
 * Test model override (X1): a subclass adding an application scope.
 */
class CustomReport extends Report
{
    /** @param Builder<static> $query */
    public function scopeFlaggedAsSpam(Builder $query): void
    {
        $query->where('metadata->spam', true);
    }
}
