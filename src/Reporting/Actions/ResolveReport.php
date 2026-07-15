<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Reporting\Events\ReportResolved;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Resolve a report (FR-305). Called with a decision when a case is
 * decided (I-06, from milestone M7's DecideCase) or without one when a
 * moderator resolves an unattached report directly.
 */
class ResolveReport
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly ReportWorkflow $workflow,
    ) {}

    public function execute(Report $report, ActorRef $by, ?Decision $decision = null): Report
    {
        $this->authorize($by, 'resolve', $report);

        return DB::transaction(function () use ($report, $by, $decision): Report {
            $from = Workflow::stateOf($report);

            (new Workflow($this->workflow))->transition($report, 'resolve', $by);

            if ($decision instanceof Decision) {
                $report->update(['decision_id' => $decision->getKey()]);
            }

            $this->recorder->record($by, 'report.resolved', $report, array_filter([
                'decision_id' => $decision?->getKey(),
            ]));

            event(new ReportResolved($report, $decision, $from, Workflow::stateOf($report), $by));

            return $report;
        });
    }
}
