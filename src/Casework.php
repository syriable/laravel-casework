<?php

declare(strict_types=1);

namespace Syriable\Casework;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\Actions\AssignAppeal;
use Syriable\Casework\Appeals\Actions\StartAppealReview;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Appeals\PendingAppeal;
use Syriable\Casework\Appeals\PendingAppealResolution;
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
use Syriable\Casework\Cases\PendingDecision;
use Syriable\Casework\Enforcement\Actions\LiftRestriction;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\PendingRestriction;
use Syriable\Casework\Enforcement\PendingWarning;
use Syriable\Casework\Reporting\Actions\AdjustReporterReputation;
use Syriable\Casework\Reporting\Actions\AttachReportToCase;
use Syriable\Casework\Reporting\Actions\DismissReport;
use Syriable\Casework\Reporting\Actions\StartReportReview;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Reporting\PendingReport;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\RestrictionType;

/**
 * Facade root: thin delegation to actions (ADR-0005) — never a second
 * implementation.
 */
final class Casework
{
    /**
     * Begin filing a report about a Reportable subject.
     */
    public function report(Model $subject): PendingReport
    {
        return new PendingReport($subject);
    }

    /**
     * Dismiss an unattached report.
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
     * Begin opening a case about a subject.
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

    /**
     * Begin deciding a case.
     */
    public function decide(CaseFile $case): PendingDecision
    {
        return new PendingDecision($case);
    }

    /**
     * Begin restricting a subject.
     */
    public function restrict(Model $subject, string $type): PendingRestriction
    {
        return new PendingRestriction($subject, $type);
    }

    /**
     * Begin suspending a subject — a restriction of the shipped
     * suspension type.
     */
    public function suspend(Model $subject): PendingRestriction
    {
        return new PendingRestriction($subject, RestrictionType::SUSPENSION);
    }

    /**
     * Begin warning a subject.
     */
    public function warn(Model $subject): PendingWarning
    {
        return new PendingWarning($subject);
    }

    /**
     * Lift an active restriction early.
     */
    public function lift(Restriction $restriction, Model|ActorRef $by, string $reason): Restriction
    {
        return app(LiftRestriction::class)->execute($restriction, $this->actor($by), $reason);
    }

    /**
     * Begin appealing a decision or restriction.
     */
    public function appeal(Model $decisionOrRestriction): PendingAppeal
    {
        return new PendingAppeal($decisionOrRestriction);
    }

    /**
     * Assign an appeal to a reviewer.
     */
    public function assignAppeal(Appeal $appeal, Model $to, Model|ActorRef $by): Appeal
    {
        return app(AssignAppeal::class)->execute($appeal, $to, $this->actor($by));
    }

    /**
     * Move a submitted appeal under review.
     */
    public function startAppealReview(Appeal $appeal, Model|ActorRef $by): Appeal
    {
        return app(StartAppealReview::class)->execute($appeal, $this->actor($by));
    }

    /**
     * Begin resolving an appeal — uphold, overturn, or reject.
     */
    public function resolveAppeal(Appeal $appeal): PendingAppealResolution
    {
        return new PendingAppealResolution($appeal);
    }

    /**
     * The FR-405 hot path for non-trait contexts: one indexed query,
     * honoring expiry in real time. Reuses the Restriction model scopes so
     * the "active and not expired" predicate lives in exactly one place.
     */
    public function isRestricted(Model $subject, ?string $type = null, ?string $scope = null): bool
    {
        /** @var class-string<Restriction> $class */
        $class = ModelRegistry::classFor('restriction');

        $query = $class::query()->forSubject($subject)->active();

        if ($type !== null) {
            $query->ofType($type);
        }

        if ($scope !== null) {
            $query->inScope($scope);
        }

        return $query->exists();
    }

    /**
     * Manually adjust a reporter's reputation score (extension point
     * X14) — for moderator-initiated corrections outside the automatic
     * dismiss/uphold pipeline. Fully audited like any other operation.
     */
    public function adjustReputation(Model $reporter, int $delta, string $reason, Model|ActorRef $by): ReporterReputation
    {
        return app(AdjustReporterReputation::class)->execute($reporter, $delta, $reason, $this->actor($by));
    }

    /**
     * The reputation hot path for non-trait contexts: true only when
     * config('casework.reporting.reputation.block_threshold') is set
     * and the reporter's score is at or below it.
     */
    public function isReporterBlocked(Model $reporter): bool
    {
        /** @var class-string<ReporterReputation> $class */
        $class = ModelRegistry::classFor('reporter_reputation');

        $reputation = $class::query()
            ->where('reporter_type', $reporter->getMorphClass())
            ->where('reporter_id', $reporter->getKey())
            ->first();

        return $reputation instanceof ReporterReputation && $reputation->isBlocked();
    }

    private function actor(Model|ActorRef $by): ActorRef
    {
        return $by instanceof ActorRef ? $by : ActorRef::model($by);
    }
}
