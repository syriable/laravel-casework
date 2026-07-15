<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Safe-by-default warning authorization (FR-601).
 */
final class WarningPolicy
{
    public function issue(Model $actor): bool
    {
        return false;
    }
}
