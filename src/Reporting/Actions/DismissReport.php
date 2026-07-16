<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Reporting\Events\ReportDismissed;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Dismiss an unattached report (FR-104). Attached reports are dismissed
 * only through their case's decision (docs/workflows/report.md †).
 */
class DismissReport
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly ReportWorkflow $workflow,
    ) {}

    public function execute(Report $report, ActorRef $by): Report
    {
        $this->authorize($by, 'dismiss', $report);

        $from = Workflow::stateOf($report);

        if ($report->getAttribute('case_id') !== null) {
            throw InvalidTransition::withReason($report, 'dismiss', $from, 'attached reports are dismissed through their case decision');
        }

        return DB::transaction(function () use ($report, $by, $from): Report {
            (new Workflow($this->workflow))->transition($report, 'dismiss', $by);

            // Releasing the dedupe key frees the (subject, reporter,
            // reason) slot for a future report — I-02 scopes uniqueness
            // to open reports.
            $report->update(['dedupe_key' => null]);

            $this->recorder->record($by, 'report.dismissed', $report);

            event(new ReportDismissed($report, $from, Workflow::stateOf($report), $by));

            return $report;
        });
    }
}
