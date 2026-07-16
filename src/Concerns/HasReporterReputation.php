<?php

declare(strict_types=1);

namespace Syriable\Casework\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Opt-in reporter-side reputation surface (extension point X14). A
 * model does not need this trait to file reports — reputation tracking
 * runs regardless, when enabled — but it makes the score ergonomic to
 * read from the reporter's own side of the relationship.
 */
trait HasReporterReputation
{
    /**
     * @return MorphOne<ReporterReputation, $this>
     */
    public function reputation(): MorphOne
    {
        /** @var MorphOne<ReporterReputation, $this> */
        return $this->morphOne(ModelRegistry::classFor('reporter_reputation'), 'reporter');
    }

    /**
     * Zero when no reputation row exists yet — a reporter with no
     * dismissed or upheld reports is neutral, not penalized.
     */
    public function reputationScore(): int
    {
        $score = $this->reputation()->value('score');

        return is_int($score) ? $score : 0;
    }

    public function isBlockedFromReporting(): bool
    {
        $reputation = $this->reputation()->first();

        return $reputation instanceof ReporterReputation && $reputation->isBlocked();
    }
}
