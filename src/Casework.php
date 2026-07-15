<?php

declare(strict_types=1);

namespace Syriable\Casework;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Cases\Actions\AddNote;
use Syriable\Casework\Cases\Actions\AssignCase;
use Syriable\Casework\Cases\Actions\AttachEvidence;
use Syriable\Casework\Cases\Actions\CloseCase;
use Syriable\Casework\Cases\Actions\EscalateCase;
use Syriable\Casework\Cases\Actions\StartInvestigation;
use Syriable\Casework\Cases\Actions\SubmitForDecision;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Cases\PendingCase;
use Syriable\Casework\Reporting\Actions\AttachReportToCase;
use Syriable\Casework\Reporting\Actions\DismissReport;
use Syriable\Casework\Reporting\Actions\StartReportReview;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\PendingReport;
use Syriable\Casework\Support\ActorRef;

/**
 * Facade root: thin delegation to actions (ADR-0005) — never a second
 * implementation. Enforcement and appeal operations land with
 * milestones M7–M8.
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

    /**
     * Begin opening a case about a subject (Phase 5 §3).
     */
    public function openCase(Model $subject): PendingCase
    {
        return new PendingCase($subject);
    }

    public function attachReport(Report $report, CaseFile $to, Model|ActorRef $by): Report
    {
        return app(AttachReportToCase::class)->execute($report, $to, $this->actor($by));
    }

    public function assignCase(CaseFile $case, Model $to, Model|ActorRef $by): CaseFile
    {
        return app(AssignCase::class)->execute($case, $to, $this->actor($by));
    }

    public function startInvestigation(CaseFile $case, Model|ActorRef $by): CaseFile
    {
        return app(StartInvestigation::class)->execute($case, $this->actor($by));
    }

    public function submitForDecision(CaseFile $case, Model|ActorRef $by): CaseFile
    {
        return app(SubmitForDecision::class)->execute($case, $this->actor($by));
    }

    public function escalateCase(CaseFile $case, Model|ActorRef $by, string $priority): CaseFile
    {
        return app(EscalateCase::class)->execute($case, $this->actor($by), $priority);
    }

    public function closeCase(CaseFile $case, Model|ActorRef $by): CaseFile
    {
        return app(CloseCase::class)->execute($case, $this->actor($by));
    }

    public function note(CaseFile $case, Model|ActorRef $by, string $body): Note
    {
        return app(AddNote::class)->execute($case, $this->actor($by), $body);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function attachEvidence(CaseFile $case, Model|ActorRef $by, ?Model $subject = null, ?array $data = null): Evidence
    {
        return app(AttachEvidence::class)->execute($case, $this->actor($by), $subject, $data);
    }

    private function actor(Model|ActorRef $by): ActorRef
    {
        return $by instanceof ActorRef ? $by : ActorRef::model($by);
    }
}
