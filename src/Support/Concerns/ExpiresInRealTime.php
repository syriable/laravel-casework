<?php

declare(strict_types=1);

namespace Syriable\Casework\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * The real-time expiry rule (invariant I-09) as a single reusable scope.
 *
 * A row is "not expired" when it has no expiry, or its expiry is still in
 * the future — evaluated against the wall clock, never a stored flag. Both
 * expiring records (restrictions, warnings) share this exact predicate;
 * centralizing it here keeps the rule defined once (Phase 18 review).
 */
trait ExpiresInRealTime
{
    /**
     * Constrain to rows whose expiry has not yet passed.
     *
     * @param  Builder<static>  $query
     */
    public function scopeNotExpired(Builder $query): void
    {
        $query->where(function (Builder $expiry): void {
            $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
