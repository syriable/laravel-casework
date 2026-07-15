<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

use Closure;
use Syriable\Casework\Cases\Models\CaseFile;

/**
 * Triage automation stage (FR-804, extension point X10). Classes listed
 * in config('casework.pipelines.triage') run, in order, after
 * CaseOpened commits.
 *
 * Stages act through package operations (assign, escalate, decide, …)
 * with System attribution (FR-805) and therefore receive the full
 * authorize → guard → transact → audit → event treatment — an
 * automated decision is audited exactly like a human one. A stage
 * short-circuits by returning without calling $next.
 *
 * Stages are privileged code (extending spec §4): review them like
 * middleware. They are resolved from config/container only — never
 * from request input.
 */
interface CaseTriageStage
{
    /**
     * @param  Closure(CaseFile): CaseFile  $next
     */
    public function handle(CaseFile $case, Closure $next): CaseFile;
}
