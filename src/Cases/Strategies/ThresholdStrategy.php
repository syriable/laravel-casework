<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Strategies;

use Syriable\Casework\Cases\Actions\OpenCase;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\CaseStrategy;
use Syriable\Casework\Reporting\Actions\AttachReportToCase;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\ModelRegistry;

/**
 * config: 'threshold' (shipped default) — a report joins the subject's
 * open case when one exists; otherwise a case opens once the subject
 * accumulates config('casework.cases.threshold') open reports, and the
 * earlier open reports are attached alongside the triggering one.
 */
class ThresholdStrategy implements CaseStrategy
{
    public function __construct(
        private readonly OpenCase $openCase,
        private readonly AttachReportToCase $attach,
    ) {}

    public function caseFor(Report $report): ?CaseFile
    {
        $existing = $this->existingOpenCase($report);

        if ($existing instanceof CaseFile) {
            return $existing;
        }

        $threshold = config('casework.cases.threshold');
        $threshold = is_int($threshold) ? $threshold : 1;

        $openReports = $this->openUnattachedReports($report);

        // The triggering report is already persisted and counts itself.
        if (count($openReports) < $threshold) {
            return null;
        }

        $case = $this->openCase->execute($report->subject()->firstOrFail(), ActorRef::system());

        foreach ($openReports as $sibling) {
            if ($sibling->isNot($report)) {
                $this->attach->execute($sibling, $case, ActorRef::system());
            }
        }

        return $case;
    }

    protected function existingOpenCase(Report $report): ?CaseFile
    {
        /** @var class-string<CaseFile> $class */
        $class = ModelRegistry::classFor('case');

        return $class::query()
            ->where('subject_type', $report->getAttribute('subject_type'))
            ->where('subject_id', $report->getAttribute('subject_id'))
            ->whereNotIn('state', ['decided', 'closed'])
            ->first();
    }

    /** @return list<Report> */
    protected function openUnattachedReports(Report $report): array
    {
        /** @var class-string<Report> $class */
        $class = ModelRegistry::classFor('report');

        return array_values($class::query()
            ->where('subject_type', $report->getAttribute('subject_type'))
            ->where('subject_id', $report->getAttribute('subject_id'))
            ->whereIn('state', ['pending', 'under_review'])
            ->whereNull('case_id')
            ->get()
            ->all());
    }
}
