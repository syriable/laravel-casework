<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Enforcement\Models\Restriction;

/**
 * Safe-by-default enforcement authorization (FR-601): denied for model
 * actors until the application registers its own policy. System
 * attribution bypasses policies (FR-805).
 */
final class RestrictionPolicy
{
    public function apply(Model $actor): bool
    {
        return false;
    }

    public function lift(Model $actor, Restriction $restriction): bool
    {
        return false;
    }
}
