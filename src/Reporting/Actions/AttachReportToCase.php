<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Reporting\Events\ReportAttachedToCase;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Attach an open report to an open-phase case about the same subject
 * — see the report workflow guards in docs/guide/workflows.md.
 */
class AttachReportToCase
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly ReportWorkflow $workflow,
    ) {}

    public function execute(Report $report, CaseFile $case, ActorRef $by): Report
    {
        $this->authorize($by, 'attachToCase', $report);

        $from = Workflow::stateOf($report);

        if (in_array(Workflow::stateOf($case), ['decided', 'closed'], true)) {
            throw InvalidTransition::withReason($report, 'attachToCase', $from, 'the target case is no longer open');
        }

        // Morph ids normalize to string: an in-memory model may hold the
        // application key as int while the string(36) column re-reads as
        // string (ADR-0010).
        $caseSubjectId = $case->getAttribute('subject_id');
        $reportSubjectId = $report->getAttribute('subject_id');

        $sameSubject = $case->getAttribute('subject_type') === $report->getAttribute('subject_type')
            && is_scalar($caseSubjectId)
            && is_scalar($reportSubjectId)
            && (string) $caseSubjectId === (string) $reportSubjectId;

        if (! $sameSubject) {
            throw InvalidTransition::withReason($report, 'attachToCase', $from, 'the case concerns a different subject');
        }

        return DB::transaction(function () use ($report, $case, $by, $from): Report {
            (new Workflow($this->workflow))->transition($report, 'attachToCase', $by);

            $report->update(['case_id' => $case->getKey()]);

            $this->recorder->record($by, 'report.attached_to_case', $report, [
                'case_id' => $case->getKey(),
            ]);

            event(new ReportAttachedToCase($report, $case, $from, Workflow::stateOf($report), $by));

            return $report;
        });
    }
}
