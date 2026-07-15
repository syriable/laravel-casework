<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Exceptions\DuplicateReport;
use Syriable\Casework\Exceptions\UnknownReason;
use Syriable\Casework\Reporting\Events\ReportFiled;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
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

        $report = DB::transaction(function () use ($subject, $by, $reason, $comment, $metadata): Report {
            $class = ModelRegistry::classFor('report');

            /** @var Report $report */
            $report = new $class([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'reporter_type' => $by->actor?->getMorphClass(),
                'reporter_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'reason_id' => $reason->getKey(),
                'comment' => $comment,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            (new Workflow($this->workflow))->initialize($report, 'file', $by);

            $report->save();

            $this->recorder->record($by, 'report.filed', $report, [
                'reason' => $reason->getAttribute('key'),
            ]);

            event(new ReportFiled($report, $by));

            return $report;
        });

        return $report;
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
