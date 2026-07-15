<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Exceptions\ReviewerNotIndependent;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Reviewer eligibility for appeal review (assignment and startReview):
 * the appellant never reviews their own appeal (FR-604), and when
 * independence is required the reviewer must differ from the actor who
 * made the appealed decision or issued the appealed restriction
 * (FR-505, invariant I-12).
 */
trait GuardsReviewerIndependence
{
    /**
     * @throws AuthorizationException|ReviewerNotIndependent
     */
    private function guardReviewer(Appeal $appeal, Model $reviewer): void
    {
        if (config('casework.authorization.prevent_self_moderation') === true
            && $this->sameParty($reviewer, $appeal->getAttribute('appellant_type'), $appeal->getAttribute('appellant_id'))) {
            throw new AuthorizationException('Actors cannot review appeals concerning themselves.');
        }

        if (config('casework.appeals.require_independent_reviewer') !== true) {
            return;
        }

        [$type, $id] = $this->responsibleParty($appeal);

        if ($this->sameParty($reviewer, $type, $id)) {
            throw ReviewerNotIndependent::for($reviewer, $appeal);
        }
    }

    /**
     * The morph reference of the original decider (appealed decision) or
     * issuer (appealed restriction).
     *
     * @return array{mixed, mixed}
     */
    private function responsibleParty(Appeal $appeal): array
    {
        $target = $appeal->appealed()->getResults();

        if (! $target instanceof Model) {
            return [null, null];
        }

        $decisionClass = ModelRegistry::classFor('decision');

        if ($target instanceof $decisionClass) {
            return [$target->getAttribute('decider_type'), $target->getAttribute('decider_id')];
        }

        return [$target->getAttribute('issuer_type'), $target->getAttribute('issuer_id')];
    }

    /**
     * Morph comparison with in-memory/column key normalization: integer
     * keys read back as strings from the string(36) morph columns
     * (ADR-0010).
     */
    private function sameParty(Model $actor, mixed $type, mixed $id): bool
    {
        $key = $actor->getKey();

        return $type === $actor->getMorphClass()
            && is_scalar($id)
            && is_scalar($key)
            && (string) $id === (string) $key;
    }
}
