<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Enforcement\Events\RestrictionLifted;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Lift an active restriction early, recording actor and reason
 * (FR-408, invariant I-10 — real-time activity, not stored state).
 */
class LiftRestriction
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly RestrictionWorkflow $workflow,
    ) {}

    public function execute(Restriction $restriction, ActorRef $by, string $reason): Restriction
    {
        $this->authorizeInScope($by, 'lift', $restriction, $restriction->subject()->getResults());

        if (! $restriction->isActive()) {
            throw InvalidTransition::withReason(
                $restriction,
                'lift',
                Workflow::stateOf($restriction),
                'only currently active restrictions can be lifted (I-10)',
            );
        }

        return DB::transaction(function () use ($restriction, $by, $reason): Restriction {
            $from = Workflow::stateOf($restriction);

            (new Workflow($this->workflow))->transition($restriction, 'lift', $by);

            $restriction->update([
                'lifted_at' => now(),
                'lifted_by_type' => $by->actor?->getMorphClass(),
                'lifted_by_id' => $by->actor?->getKey(),
                'lift_reason' => $reason,
            ]);

            $this->recorder->record($by, 'restriction.lifted', $restriction, ['reason' => $reason]);

            event(new RestrictionLifted($restriction, $reason, $from, Workflow::stateOf($restriction), $by));

            return $restriction;
        });
    }
}
