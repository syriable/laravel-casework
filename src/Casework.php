<?php

declare(strict_types=1);

namespace Syriable\Casework;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Actions\DismissReport;
use Syriable\Casework\Reporting\Actions\StartReportReview;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\PendingReport;
use Syriable\Casework\Support\ActorRef;

/**
 * Facade root: thin delegation to actions (ADR-0005) — never a second
 * implementation. Case, enforcement, and appeal operations land with
 * milestones M6–M8.
 */
final class Casework
{
    /**
     * Begin filing a report about a Reportable subject (Phase 5 §2).
     */
    public function report(Model $subject): PendingReport
    {
        return new PendingReport($subject);
    }

    /**
     * Dismiss an unattached report (FR-104).
     */
    public function dismissReport(Report $report, Model|ActorRef $by): Report
    {
        return app(DismissReport::class)->execute($report, $this->actor($by));
    }

    /**
     * Move a pending report under review.
     */
    public function startReportReview(Report $report, Model|ActorRef $by): Report
    {
        return app(StartReportReview::class)->execute($report, $this->actor($by));
    }

    private function actor(Model|ActorRef $by): ActorRef
    {
        return $by instanceof ActorRef ? $by : ActorRef::model($by);
    }
}
