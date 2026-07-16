<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Enforcement\Events\RestrictionApplied;
use Syriable\Casework\Enforcement\Events\RestrictionSuperseded;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\RestrictionType;

/**
 * Apply a restriction to a subject. Direct or carried by a
 * decision; optionally supersedes an active restriction in the
 * same transaction (workflow: supersede).
 */
class ApplyRestriction
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly RestrictionWorkflow $workflow,
    ) {}

    public function execute(
        Model $subject,
        ActorRef $by,
        string $type,
        ?Carbon $expiresAt = null,
        ?string $scope = null,
        ?string $rationale = null,
        ?Decision $decision = null,
        ?Restriction $supersedes = null,
    ): Restriction {
        $this->authorizeInScope($by, 'apply', ModelRegistry::classFor('restriction'), $subject);
        $this->guardType($type);

        return DB::transaction(function () use ($subject, $by, $type, $expiresAt, $scope, $rationale, $decision, $supersedes): Restriction {
            $class = ModelRegistry::classFor('restriction');

            /** @var Restriction $restriction */
            $restriction = new $class([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'type' => $type,
                'scope' => $scope,
                'issuer_type' => $by->actor?->getMorphClass(),
                'issuer_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'decision_id' => $decision?->getKey(),
                'expires_at' => $expiresAt,
                'rationale' => $rationale,
            ]);

            (new Workflow($this->workflow))->initialize($restriction, 'apply', $by);

            $restriction->save();

            $this->recorder->record($by, 'restriction.applied', $restriction, array_filter([
                'type' => $type,
                'scope' => $scope,
                'expires_at' => $expiresAt?->toIso8601String(),
                'decision_id' => $decision?->getKey(),
            ]));

            event(new RestrictionApplied($restriction, $by));

            if ($supersedes instanceof Restriction) {
                $this->supersede($supersedes, $restriction, $by);
            }

            return $restriction;
        });
    }

    private function supersede(Restriction $old, Restriction $replacement, ActorRef $by): void
    {
        $from = Workflow::stateOf($old);

        (new Workflow($this->workflow))->transition($old, 'supersede', $by, [
            'replacement' => $replacement,
        ]);

        $old->update(['superseded_by_id' => $replacement->getKey()]);

        $this->recorder->record($by, 'restriction.superseded', $old, [
            'replacement_id' => $replacement->getKey(),
        ]);

        event(new RestrictionSuperseded($old, $replacement, $from, Workflow::stateOf($old), $by));
    }

    private function guardType(string $type): void
    {
        $extra = config('casework.enforcement.restriction_types');
        $known = [...RestrictionType::shipped(), ...(is_array($extra) ? $extra : [])];

        if (! in_array($type, $known, true)) {
            throw InvalidConfiguration::forKey(
                'enforcement.restriction_types',
                "restriction type [{$type}] is not shipped or configured",
            );
        }
    }
}
