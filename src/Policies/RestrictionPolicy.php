<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Enforcement\Models\Restriction;

/**
 * Safe-by-default enforcement authorization: denied for model
 * actors until the application registers its own policy. System
 * attribution bypasses policies.
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
