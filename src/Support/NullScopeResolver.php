<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Contracts\ScopeResolver;

/**
 * Default ScopeResolver: everything is unscoped (Phase 9 contracts list).
 */
final class NullScopeResolver implements ScopeResolver
{
    public function scopesFor(Model $actor): ?array
    {
        return null;
    }

    public function scopeOf(Model $subject): ?string
    {
        return null;
    }
}
