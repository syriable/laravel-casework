<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Safe-by-default reputation authorization: manual adjustments are
 * denied for model actors until the application registers its own
 * policy. System attribution (the automatic dismiss/uphold pipeline)
 * bypasses policies.
 */
final class ReporterReputationPolicy
{
    public function adjust(Model $actor): bool
    {
        return false;
    }
}
