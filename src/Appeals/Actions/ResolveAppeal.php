<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Appeals\Events\AppealOverturned;
use Syriable\Casework\Appeals\Events\AppealRejected;
use Syriable\Casework\Appeals\Events\AppealUpheld;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Enforcement\Actions\LiftRestriction;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Outcome;

/**
 * Resolve an appeal: uphold, overturn, or reject (FR-502/504). Overturn
 * is atomic (I-13): lifting the associated active restrictions —
 * through the restriction machine's own lift, no special-case writes —
 * and recording a superseding decision commit with the appeal
 * transition or not at all. The superseding decision carries outcome
 * `dismiss`: the original finding no longer stands. Effects dispatch
 * before the summarizing AppealOverturned (ADR-0015).
 */
class ResolveAppeal
{
    use AuthorizesActions;

    public const string UPHOLD = 'uphold';

    public const string OVERTURN = 'overturn';

    public const string REJECT = 'reject';

    public function __construct(
        private readonly Recorder $recorder,
        private readonly AppealWorkflow $workflow,
        private readonly LiftRestriction $liftRestriction,
    ) {}

    public function execute(Appeal $appeal, ActorRef $by, string $resolution, ?string $rationale = null): Appeal
    {
        if (! in_array($resolution, [self::UPHOLD, self::OVERTURN, self::REJECT], true)) {
            throw new InvalidArgumentException("Unknown appeal resolution [{$resolution}].");
        }

        $this->authorize($by, 'resolve', $appeal);

        return DB::transaction(function () use ($appeal, $by, $resolution, $rationale): Appeal {
            $from = Workflow::stateOf($appeal);

            (new Workflow($this->workflow))->transition($appeal, $resolution, $by);

            $to = Workflow::stateOf($appeal);

            match ($resolution) {
                self::UPHOLD => $this->uphold($appeal, $by, $from, $to),
                self::OVERTURN => $this->overturn($appeal, $by, $rationale, $from, $to),
                default => $this->reject($appeal, $by, $rationale, $from, $to),
            };

            return $appeal;
        });
    }

    private function uphold(Appeal $appeal, ActorRef $by, string $from, string $to): void
    {
        $this->recorder->record($by, 'appeal.upheld', $appeal);

        event(new AppealUpheld($appeal, $from, $to, $by));
    }

    private function reject(Appeal $appeal, ActorRef $by, ?string $reason, string $from, string $to): void
    {
        $this->recorder->record($by, 'appeal.rejected', $appeal, array_filter([
            'reason' => $reason,
        ]));

        event(new AppealRejected($appeal, $reason, $from, $to, $by));
    }

    /**
     * FR-504 / I-13. Lifts run through LiftRestriction, so each one
     * authorizes, audits, and dispatches like any other lift — the
     * resolving actor therefore also needs the restriction `lift`
     * ability when restrictions are in play.
     */
    private function overturn(Appeal $appeal, ActorRef $by, ?string $rationale, string $from, string $to): void
    {
        $target = $appeal->appealed()->getResults();

        $lifted = $this->activeRestrictionsOf($target)
            ->map(fn (Restriction $restriction): Restriction => $this->liftRestriction->execute(
                $restriction,
                $by,
                $rationale ?? 'Appeal overturned (FR-504)',
            ))
            ->values();

        $superseding = $this->supersede($this->originalDecisionOf($target), $by, $rationale);

        $appeal->update(['resulting_decision_id' => $superseding?->getKey()]);

        $this->recorder->record($by, 'appeal.overturned', $appeal, array_filter([
            'superseding_decision_id' => $superseding?->getKey(),
            'lifted' => $lifted->pluck('id')->all(),
        ]));

        event(new AppealOverturned($appeal, $superseding, $lifted, $from, $to, $by));
    }

    /**
     * The restrictions an overturn must lift: the appealed restriction
     * itself, or every still-active restriction the appealed decision
     * applied. Inactive ones (expired/lifted) have nothing to lift.
     *
     * @return Collection<int, Restriction>
     */
    private function activeRestrictionsOf(?Model $target): Collection
    {
        if ($target instanceof Restriction) {
            /** @var Collection<int, Restriction> */
            return collect([$target])->filter(fn (Restriction $restriction): bool => $restriction->isActive());
        }

        if ($target instanceof Decision) {
            /** @var Collection<int, Restriction> */
            return $target->restrictions()
                ->get()
                ->filter(fn (Model $restriction): bool => $restriction instanceof Restriction && $restriction->isActive())
                ->values();
        }

        /** @var Collection<int, Restriction> */
        return collect();
    }

    /**
     * The decision an overturn supersedes: the appealed decision, or the
     * decision that carried the appealed restriction. Null for direct
     * restrictions — nothing to supersede (decisions require a case).
     */
    private function originalDecisionOf(?Model $target): ?Decision
    {
        if ($target instanceof Decision) {
            return $target;
        }

        if ($target instanceof Restriction) {
            $decision = $target->decision()->getResults();

            return $decision instanceof Decision ? $decision : null;
        }

        return null;
    }

    private function supersede(?Decision $original, ActorRef $by, ?string $rationale): ?Decision
    {
        if ($original === null) {
            return null;
        }

        $class = ModelRegistry::classFor('decision');

        /** @var Decision $superseding */
        $superseding = new $class([
            'case_id' => $original->getAttribute('case_id'),
            'decider_type' => $by->actor?->getMorphClass(),
            'decider_id' => $by->actor?->getKey(),
            'origin' => $by->origin,
            'outcome' => Outcome::DISMISS,
            'rationale' => $rationale,
            'supersedes_id' => $original->getKey(),
        ]);

        $superseding->save();

        return $superseding;
    }
}
