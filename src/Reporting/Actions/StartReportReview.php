<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Reporting\Events\ReportReviewStarted;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Move a pending report under review (workflow: startReview).
 */
class StartReportReview
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly ReportWorkflow $workflow,
    ) {}

    public function execute(Report $report, ActorRef $by): Report
    {
        $this->authorize($by, 'startReview', $report);

        return DB::transaction(function () use ($report, $by): Report {
            $from = Workflow::stateOf($report);

            (new Workflow($this->workflow))->transition($report, 'startReview', $by);

            $this->recorder->record($by, 'report.review_started', $report);

            event(new ReportReviewStarted($report, $from, Workflow::stateOf($report), $by));

            return $report;
        });
    }
}
