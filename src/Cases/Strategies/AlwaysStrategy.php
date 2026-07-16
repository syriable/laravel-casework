<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Strategies;

use Syriable\Casework\Cases\Actions\OpenCase;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\CaseStrategy;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\ModelRegistry;

/**
 * config: 'always' — every report joins the subject's open case,
 * opening one when none exists.
 */
class AlwaysStrategy implements CaseStrategy
{
    public function __construct(
        private readonly OpenCase $openCase,
    ) {}

    public function caseFor(Report $report): ?CaseFile
    {
        return $this->existingOpenCase($report)
            ?? $this->openCase->execute($report->subject()->firstOrFail(), ActorRef::system());
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
}
