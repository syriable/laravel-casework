<?php

declare(strict_types=1);

namespace Syriable\Casework\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Exceptions\ImmutableRecord;

/**
 * Model-layer immutability (ADR-0003): updates and deletes through
 * Eloquent throw. Not tamper-proof at the SQL layer — that boundary is
 * documented, not enforced here.
 */
trait PreventsMutation
{
    protected static function bootPreventsMutation(): void
    {
        static::updating(function (Model $model): void {
            throw ImmutableRecord::mutationAttempted($model);
        });

        static::deleting(function (Model $model): void {
            throw ImmutableRecord::mutationAttempted($model);
        });
    }
}
