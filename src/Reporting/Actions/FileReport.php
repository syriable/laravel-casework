<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Strategies\AlwaysStrategy;
use Syriable\Casework\Cases\Strategies\ManualStrategy;
use Syriable\Casework\Cases\Strategies\ThresholdStrategy;
use Syriable\Casework\Contracts\CaseStrategy;
use Syriable\Casework\Exceptions\DuplicateReport;
use Syriable\Casework\Exceptions\ReporterBlocked;
use Syriable\Casework\Exceptions\ReportRateLimited;
use Syriable\Casework\Exceptions\UnknownReason;
use Syriable\Casework\Reporting\Events\ReportFiled;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Reporting\ReportIntake;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * File a report. Pipeline: authorize → guard → transact →
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
        $this->guardReputationAndRateLimit($by);

        // Intake automation (FR-804, X9): after guards, before
        // persistence. A stage throwing here refuses intake — nothing
        // has been written.
        $intake = $this->runIntakePipeline(
            new ReportIntake($subject, $by, $reason, $comment, $metadata),
        );

        // The dedupe key is the DB-level backstop for I-02 (Phase 18
        // review): a unique index rejects a second open report for the
        // same (subject, reporter, reason) tuple even when two requests
        // race past the pre-check above. Null for system/anonymous
        // origins and when duplicates are allowed — those carry no key,
        // so the index never constrains them.
        $dedupeKey = $this->dedupeKey($by, $subject, $reason);

        try {
            $report = DB::transaction(function () use ($subject, $by, $reason, $intake, $dedupeKey): Report {
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
                    'dedupe_key' => $dedupeKey,
                ]);

                (new Workflow($this->workflow))->initialize($report, 'file', $by);

                $report->save();

                $this->recorder->record($by, 'report.filed', $report, [
                    'reason' => $reason->getAttribute('key'),
                ]);

                event(new ReportFiled($report, $by));

                // Auto-dismissal (X9): filed and dismissed both land in the
                // audit trail, attributed to the System.
                if ($intake->shouldDismiss()) {
                    app(DismissReport::class)->execute($report, ActorRef::system());

                    return $report;
                }

                $this->applyCaseStrategy($report, $intake);

                return $report;
            });
        } catch (QueryException $exception) {
            // A concurrent filer won the race and inserted the same open
            // tuple first; the unique index rejected this one. Translate
            // it to the same exception the pre-check throws.
            if ($by->actor !== null && $this->isUniqueViolation($exception)) {
                throw DuplicateReport::for($by->actor, $subject, $reason);
            }

            throw $exception;
        }

        return $report;
    }

    /**
     * The value guarding invariant I-02 at the database layer. Present
     * only for model reporters when duplicates are disallowed — exactly
     * the case the pre-check covers — so the unique index and the
     * pre-check agree on scope.
     */
    private function dedupeKey(ActorRef $by, Model $subject, Reason $reason): ?string
    {
        if ($by->actor === null || config('casework.reporting.allow_duplicates') === true) {
            return null;
        }

        return hash('sha256', implode('|', [
            $subject->getMorphClass(),
            $this->stringifyKey($subject->getKey()),
            $by->actor->getMorphClass(),
            $this->stringifyKey($by->actor->getKey()),
            $this->stringifyKey($reason->getKey()),
        ]));
    }

    /**
     * Model keys are scalar in every supported key strategy (bigint,
     * UUID, ULID — ADR-0010); the guard keeps the fingerprint total.
     */
    private function stringifyKey(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * SQLSTATE 23000 (MySQL/SQLite integrity violation) or 23505
     * (PostgreSQL unique_violation). The only unique index touched by a
     * report insert is the dedupe key, so this is unambiguous here.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
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

    /**
     * Reporter reputation and rate limiting (extension point X14),
     * opt-in via config('casework.reporting.reputation.*'). Applies
     * only to model reporters — system and anonymous origins carry no
     * identity to score or limit, exactly like the duplicate guard.
     */
    private function guardReputationAndRateLimit(ActorRef $by): void
    {
        if ($by->actor === null) {
            return;
        }

        $threshold = config('casework.reporting.reputation.block_threshold');

        if (is_int($threshold)) {
            $class = ModelRegistry::classFor('reporter_reputation');

            /** @var ReporterReputation|null $reputation */
            $reputation = $class::query()
                ->where('reporter_type', $by->actor->getMorphClass())
                ->where('reporter_id', $by->actor->getKey())
                ->first();

            if ($reputation !== null && $reputation->isBlocked()) {
                throw ReporterBlocked::for($by->actor, $reputation->score);
            }
        }

        $limit = config('casework.reporting.reputation.rate_limit');

        if (is_int($limit)) {
            $windowMinutes = config('casework.reporting.reputation.rate_limit_window_minutes');
            $windowMinutes = is_int($windowMinutes) ? $windowMinutes : 60;

            $reportClass = ModelRegistry::classFor('report');

            $recent = $reportClass::query()
                ->where('reporter_type', $by->actor->getMorphClass())
                ->where('reporter_id', $by->actor->getKey())
                ->where('created_at', '>=', now()->subMinutes($windowMinutes))
                ->count();

            if ($recent >= $limit) {
                throw ReportRateLimited::for($by->actor, $limit, $windowMinutes);
            }
        }
    }
}
