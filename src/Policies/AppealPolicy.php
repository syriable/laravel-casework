<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\Models\Appeal;

/**
 * Safe-by-default appeal authorization: denied for model
 * actors until the application registers its own policy. System
 * attribution bypasses policies. Applications typically grant
 * `submit` to the affected actor and the review abilities to
 * moderation staff.
 */
final class AppealPolicy
{
    public function submit(Model $actor): bool
    {
        return false;
    }

    public function assign(Model $actor, Appeal $appeal): bool
    {
        return false;
    }

    public function review(Model $actor, Appeal $appeal): bool
    {
        return false;
    }

    public function resolve(Model $actor, Appeal $appeal): bool
    {
        return false;
    }
}
