<?php

declare(strict_types=1);

namespace Syriable\Casework\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\RestrictionType;

/**
 * The Restrictable relation surface (Phase 5 §1). isRestricted() is the
 * FR-405 hot path: one query against the composite index, honoring
 * expires_at in real time (I-09) regardless of the expiry command.
 */
trait InteractsWithRestrictions
{
    /**
     * Full restriction history.
     *
     * @return MorphMany<Model, $this>
     */
    public function restrictions(): MorphMany
    {
        return $this->morphMany(ModelRegistry::classFor('restriction'), 'subject');
    }

    /**
     * Currently enforceable restrictions (state active AND not past
     * expiry — the real-time rule, I-09).
     *
     * @return MorphMany<Model, $this>
     */
    public function activeRestrictions(): MorphMany
    {
        return $this->restrictions()
            ->where('state', 'active')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
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
     * @return MorphMany<Model, $this>
     */
    public function warnings(): MorphMany
    {
        return $this->morphMany(ModelRegistry::classFor('warning'), 'subject');
    }

    /**
     * @return MorphMany<Model, $this>
     */
    public function activeWarnings(): MorphMany
    {
        return $this->warnings()->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
