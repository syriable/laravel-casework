<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Listeners;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Contracts\ReputationPolicy;
use Syriable\Casework\Reporting\Actions\AdjustReporterReputation;
use Syriable\Casework\Reporting\Events\ReportDismissed;
use Syriable\Casework\Reporting\Events\ReportResolved;
use Syriable\Casework\Reporting\Reputation\DefaultReputationPolicy;
use Syriable\Casework\Support\ActorRef;

/**
 * Reacts to a report's outcome by adjusting its reporter's reputation
 * (extension point X14, opt-in via
 * config('casework.reporting.reputation.enabled')). ReportDismissed and
 * ReportResolved dispatch after commit (ADR-0015), so this observes
 * committed state; the adjustment is its own audited unit of work,
 * attributed to the System — the same pattern RunTriagePipeline uses
 * for reactive automation.
 */
final class AdjustReputationOnReportOutcome
{
    public function __construct(
        private readonly AdjustReporterReputation $adjust,
    ) {}

    public function handle(ReportDismissed|ReportResolved $event): void
    {
        if (config('casework.reporting.reputation.enabled') !== true) {
            return;
        }

        $reporter = $event->report->reporter;

        if (! $reporter instanceof Model) {
            return;
        }

        $policy = $this->resolvePolicy();

        if ($event instanceof ReportDismissed) {
            $delta = $policy->deltaForDismissal($event->report);
            $reason = 'report.dismissed';
        } else {
            if ($event->decision === null) {
                return;
            }

            $delta = $policy->deltaForResolution($event->report, $event->decision);
            $reason = 'report.resolved';
        }

        if ($delta === 0) {
            return;
        }

        $this->adjust->execute($reporter, $delta, $reason, ActorRef::system(), $event->report);
    }

    private function resolvePolicy(): ReputationPolicy
    {
        $class = config('casework.reporting.reputation.policy');
        $class = is_string($class) && $class !== '' ? $class : DefaultReputationPolicy::class;

        return app($class);
    }
}
