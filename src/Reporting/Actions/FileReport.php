<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Strategies\AlwaysStrategy;
use Syriable\Casework\Cases\Strategies\ManualStrategy;
use Syriable\Casework\Cases\Strategies\ThresholdStrategy;
use Syriable\Casework\Contracts\CaseStrategy;
use Syriable\Casework\Exceptions\DuplicateReport;
use Syriable\Casework\Exceptions\UnknownReason;
use Syriable\Casework\Reporting\Events\ReportFiled;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportIntake;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * File a report (FR-101–108). Pipeline: authorize → guard → transact →
 * transition → audit → events (ADR-0005; events after commit, ADR-0015).
 */
class FileReport
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly ReportWorkflow $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     *
     * @throws AuthorizationException|DuplicateReport|UnknownReason
     */
    public function execute(
        Model $subject,
        ActorRef $by,
        Reason|string $reason,
        ?string $comment = null,
        array $metadata = [],
    ): Report {
        $this->authorize($by, 'file', ModelRegistry::classFor('report'));

        if ($by->isAnonymous() && config('casework.reporting.allow_anonymous') !== true) {
            throw new AuthorizationException('Anonymous reporting is disabled.');
        }

        $reason = $this->resolveReason($reason);

        $this->guardAgainstDuplicates($by, $subject, $reason);

        // Intake automation (FR-804, X9): after guards, before
        // persistence. A stage throwing here refuses intake — nothing
        // has been written.
        $intake = $this->runIntakePipeline(
            new ReportIntake($subject, $by, $reason, $comment, $metadata),
        );

        $report = DB::transaction(function () use ($subject, $by, $reason, $intake): Report {
            $class = ModelRegistry::classFor('report');

            /** @var Report $report */
            $report = new $class([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'reporter_type' => $by->actor?->getMorphClass(),
                'reporter_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'reason_id' => $reason->getKey(),
                'comment' => $intake->comment,
                'metadata' => $intake->metadata === [] ? null : $intake->metadata,
            ]);

            (new Workflow($this->workflow))->initialize($report, 'file', $by);

            $report->save();

            $this->recorder->record($by, 'report.filed', $report, [
                'reason' => $reason->getAttribute('key'),
            ]);

            event(new ReportFiled($report, $by));

            // Auto-dismissal (X9): filed and dismissed both land in the
            // audit trail, attributed to the System (FR-805).
            if ($intake->shouldDismiss()) {
                app(DismissReport::class)->execute($report, ActorRef::system());

                return $report;
            }

            $this->applyCaseStrategy($report, $intake);

            return $report;
        });

        return $report;
    }

    private function runIntakePipeline(ReportIntake $intake): ReportIntake
    {
        $stages = config('casework.pipelines.intake');

        if (! is_array($stages) || $stages === []) {
            return $intake;
        }

        $result = app(Pipeline::class)
            ->send($intake)
            ->through($stages)
            ->then(fn (ReportIntake $intake): ReportIntake => $intake);

        // Stages mutate and pass on the same context; a short-circuiting
        // stage returns it directly. Anything else falls back to it.
        return $result instanceof ReportIntake ? $result : $intake;
    }

    /**
     * Case creation strategy (FR-205/206, X7): the configured strategy
     * decides whether the fresh report opens or joins a case, unless an
     * intake stage forced or suppressed it (X9); attachment runs as the
     * System actor inside the same transaction.
     */
    private function applyCaseStrategy(Report $report, ReportIntake $intake): void
    {
        if ($intake->shouldSuppressCase()) {
            return;
        }

        $strategy = $intake->shouldForceCase()
            ? app(AlwaysStrategy::class)
            : $this->resolveStrategy();

        $case = $strategy->caseFor($report);

        if ($case !== null) {
            app(AttachReportToCase::class)
                ->execute($report, $case, ActorRef::system());
        }
    }

    private function resolveStrategy(): CaseStrategy
    {
        $configured = config('casework.cases.strategy');
        $configured = is_string($configured) ? $configured : 'manual';

        $class = match ($configured) {
            'always' => AlwaysStrategy::class,
            'threshold' => ThresholdStrategy::class,
            'manual' => ManualStrategy::class,
            default => $configured,
        };

        /** @var CaseStrategy */
        return app($class);
    }

    private function resolveReason(Reason|string $reason): Reason
    {
        if (is_string($reason)) {
            $class = ModelRegistry::classFor('reason');

            /** @var Reason|null $found */
            $found = $class::query()->where('key', $reason)->first();

            $reason = $found ?? throw UnknownReason::forKey($reason);
        }

        if ($reason->getAttribute('is_active') != true) {
            $key = $reason->getAttribute('key');

            throw UnknownReason::inactive(is_string($key) ? $key : '');
        }

        return $reason;
    }

    /**
     * Invariant I-02: no duplicate open report by the same reporter on
     * the same subject for the same reason. Applies to model reporters
     * only — system and anonymous origins carry no comparable identity.
     */
    private function guardAgainstDuplicates(ActorRef $by, Model $subject, Reason $reason): void
    {
        if ($by->actor === null || config('casework.reporting.allow_duplicates') === true) {
            return;
        }

        $class = ModelRegistry::classFor('report');

        // Raw clauses: the registry-resolved builder is generic and does
        // not carry the Report scopes.
        $duplicate = $class::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('reporter_type', $by->actor->getMorphClass())
            ->where('reporter_id', $by->actor->getKey())
            ->where('reason_id', $reason->getKey())
            ->whereNotIn('state', ['resolved', 'dismissed'])
            ->exists();

        if ($duplicate) {
            throw DuplicateReport::for($by->actor, $subject, $reason);
        }
    }
}
