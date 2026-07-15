<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Listeners;

use Illuminate\Pipeline\Pipeline;
use Syriable\Casework\Cases\Events\CaseOpened;
use Syriable\Casework\Cases\Models\CaseFile;

/**
 * Runs the triage pipeline (FR-804, extension point X10) when a case
 * opens. CaseOpened dispatches after commit (ADR-0015), so stages see
 * the committed case and everything they do through package operations
 * is its own audited unit of work.
 */
final class RunTriagePipeline
{
    public function handle(CaseOpened $event): void
    {
        $stages = config('casework.pipelines.triage');

        if (! is_array($stages) || $stages === []) {
            return;
        }

        app(Pipeline::class)
            ->send($event->case)
            ->through($stages)
            ->then(fn (CaseFile $case): CaseFile => $case);
    }
}
