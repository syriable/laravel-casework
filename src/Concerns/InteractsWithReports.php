<?php

declare(strict_types=1);

namespace Syriable\Casework\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Syriable\Casework\Support\ModelRegistry;

/**
 * The Reportable relation surface. Apply to models
 * implementing Contracts\Reportable.
 */
trait InteractsWithReports
{
    /**
     * All reports about this model.
     *
     * @return MorphMany<Model, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ModelRegistry::classFor('report'), 'subject');
    }

    /**
     * Reports not yet resolved or dismissed.
     *
     * @return MorphMany<Model, $this>
     */
    public function openReports(): MorphMany
    {
        return $this->reports()->whereNotIn('state', ['resolved', 'dismissed']);
    }

    public function hasOpenReports(): bool
    {
        return $this->openReports()->exists();
    }

    /**
     * Cases where this model is the primary subject.
     *
     * @return MorphMany<Model, $this>
     */
    public function cases(): MorphMany
    {
        return $this->morphMany(ModelRegistry::classFor('case'), 'subject');
    }
}
