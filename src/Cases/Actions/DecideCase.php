<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Events\CaseDecided;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Enforcement\Actions\ApplyRestriction;
use Syriable\Casework\Enforcement\Actions\IssueWarning;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Reporting\Actions\ResolveReport;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Outcome;

/**
 * Decide a case (FR-301–305). Atomic (I-06, I-08): the case transition,
 * open-report resolution, and enforcement application commit together
 * or not at all. Enforcement events dispatch before the summarizing
 * CaseDecided (occurrence order, ADR-0015).
 */
class DecideCase
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly CaseWorkflow $workflow,
        private readonly ApplyRestriction $applyRestriction,
        private readonly IssueWarning $issueWarning,
        private readonly ResolveReport $resolveReport,
    ) {}

    /**
     * @param  list<array{type: string, expires_at: Carbon|null, scope: string|null}>  $restrictions
     * @param  list<string>  $warnings
     */
    public function execute(
        CaseFile $case,
        ActorRef $by,
        string $outcome,
        ?string $rationale = null,
        array $restrictions = [],
        array $warnings = [],
        ?Decision $supersedes = null,
    ): Decision {
        $subject = $case->subject()->getResults();

        $this->authorizeInScope($by, 'decide', $case, $subject);
        $this->guardSelfModeration($by, $case);
        $this->guardOutcome($outcome);

        return DB::transaction(function () use ($case, $by, $outcome, $rationale, $restrictions, $warnings, $supersedes, $subject): Decision {
            $from = Workflow::stateOf($case);

            (new Workflow($this->workflow))->transition($case, 'decide', $by);

            $class = ModelRegistry::classFor('decision');

            /** @var Decision $decision */
            $decision = new $class([
                'case_id' => $case->getKey(),
                'decider_type' => $by->actor?->getMorphClass(),
                'decider_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'outcome' => $outcome,
                'rationale' => $rationale,
                'supersedes_id' => $supersedes?->getKey(),
            ]);

            $decision->save();

            // Enforcement first — effects before their summary (ADR-0015).
            $applied = collect();
            $issued = collect();

            foreach ($restrictions as $restriction) {
                if ($subject !== null) {
                    $applied->push($this->applyRestriction->execute(
                        $subject,
                        $by,
                        $restriction['type'],
                        $restriction['expires_at'],
                        $restriction['scope'],
                        $rationale,
                        $decision,
                    ));
                }
            }

            foreach ($warnings as $reason) {
                if ($subject !== null) {
                    $issued->push($this->issueWarning->execute($subject, $by, $reason, null, $decision));
                }
            }

            // Resolve the case's open reports with this decision (I-06).
            $case->reports()
                ->whereNotIn('state', ['resolved', 'dismissed'])
                ->get()
                ->each(function ($report) use ($by, $decision): void {
                    if ($report instanceof Report) {
                        $this->resolveReport->execute($report, $by, $decision);
                    }
                });

            $this->recorder->record($by, 'case.decided', $case, array_filter([
                'decision_id' => $decision->getKey(),
                'outcome' => $outcome,
                'restrictions' => $applied->count(),
                'warnings' => $issued->count(),
            ]));

            event(new CaseDecided($case, $decision, $applied, $issued, $from, Workflow::stateOf($case), $by));

            return $decision;
        });
    }

    private function guardSelfModeration(ActorRef $by, CaseFile $case): void
    {
        if ($by->actor === null || config('casework.authorization.prevent_self_moderation') !== true) {
            return;
        }

        $subjectId = $case->getAttribute('subject_id');
        $actorId = $by->actor->getKey();

        $isSelf = $case->getAttribute('subject_type') === $by->actor->getMorphClass()
            && is_scalar($subjectId)
            && is_scalar($actorId)
            && (string) $subjectId === (string) $actorId;

        if ($isSelf) {
            throw new AuthorizationException('Actors cannot decide cases concerning themselves.');
        }
    }

    private function guardOutcome(string $outcome): void
    {
        $extra = config('casework.decisions.outcomes');
        $known = [...Outcome::shipped(), ...(is_array($extra) ? $extra : [])];

        if (! in_array($outcome, $known, true)) {
            throw InvalidConfiguration::forKey(
                'decisions.outcomes',
                "outcome [{$outcome}] is not shipped or configured",
            );
        }
    }
}
