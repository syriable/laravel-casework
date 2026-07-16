<?php

declare(strict_types=1);

namespace Syriable\Casework\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\RestrictionType;

/**
 * The Restrictable relation surface. isRestricted() is the
 * FR-405 hot path: one query against the composite index, honoring
 * expires_at in real time regardless of the expiry command.
 */
trait InteractsWithRestrictions
{
    /**
     * Full restriction history. Typed to the shipped model so
     * the `active` scope resolves; overrides subclass it (X1).
     *
     * @return MorphMany<Restriction, $this>
     */
    public function restrictions(): MorphMany
    {
        /** @var MorphMany<Restriction, $this> */
        return $this->morphMany(ModelRegistry::classFor('restriction'), 'subject');
    }

    /**
     * Currently enforceable restrictions (state active AND not past
     * expiry — the real-time rule, I-09). Delegates to the model's
     * `active` scope so the predicate lives in exactly one place.
     *
     * @return MorphMany<Restriction, $this>
     */
    public function activeRestrictions(): MorphMany
    {
        return $this->restrictions()->active();
    }

    public function isRestricted(?string $type = null, ?string $scope = null): bool
    {
        $query = $this->activeRestrictions();

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($scope !== null) {
            $query->where('scope', $scope);
        }

        return $query->exists();
    }

    public function isSuspended(): bool
    {
        return $this->isRestricted(RestrictionType::SUSPENSION);
    }

    /**
     * @return MorphMany<Warning, $this>
     */
    public function warnings(): MorphMany
    {
        /** @var MorphMany<Warning, $this> */
        return $this->morphMany(ModelRegistry::classFor('warning'), 'subject');
    }

    /**
     * @return MorphMany<Warning, $this>
     */
    public function activeWarnings(): MorphMany
    {
        return $this->warnings()->active();
    }
}
