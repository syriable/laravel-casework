<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

use Closure;
use Syriable\Casework\Reporting\ReportIntake;

/**
 * Intake automation stage (FR-804, extension point X9). Classes listed
 * in config('casework.pipelines.intake') run, in order, inside
 * FileReport — after its guards, before persistence.
 *
 * A stage may adjust the intake (metadata, comment), force or suppress
 * case creation, auto-dismiss, throw a domain exception to refuse the
 * report entirely, or short-circuit by returning without calling $next
 * (later stages then never run).
 *
 * Stages are privileged code (extending spec §4): review them like
 * middleware. They are resolved from config/container only — never
 * from request input.
 */
interface ReportIntakeStage
{
    /**
     * @param  Closure(ReportIntake): ReportIntake  $next
     */
    public function handle(ReportIntake $intake, Closure $next): ReportIntake;
}
