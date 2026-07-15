<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\Report;

/**
 * Safe-by-default report authorization (FR-601, extending.md §4):
 * any authenticated model may file; moderation abilities are denied
 * until the application registers its own policy (which overrides this
 * one — the package registers defaults only when none exist). System
 * attribution bypasses policies by design (FR-805).
 */
final class ReportPolicy
{
    public function file(Model $actor): bool
    {
        return true;
    }

    public function startReview(Model $actor, Report $report): bool
    {
        return false;
    }

    public function attachToCase(Model $actor, Report $report): bool
    {
        return false;
    }

    public function dismiss(Model $actor, Report $report): bool
    {
        return false;
    }

    public function resolve(Model $actor, Report $report): bool
    {
        return false;
    }
}
