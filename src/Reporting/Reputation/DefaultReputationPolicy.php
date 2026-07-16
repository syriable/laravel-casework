<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Reputation;

use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Contracts\ReputationPolicy;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\Outcome;

/**
 * The shipped ReputationPolicy (extension point X14): fixed deltas read
 * from config. Applications that need something richer — weighting by
 * reason severity, account age, report volume — bind their own class
 * via config('casework.reporting.reputation.policy').
 */
final class DefaultReputationPolicy implements ReputationPolicy
{
    public function deltaForDismissal(Report $report): int
    {
        return $this->configuredInt('casework.reporting.reputation.dismissed_delta', -1);
    }

    public function deltaForResolution(Report $report, Decision $decision): int
    {
        return match ($decision->getAttribute('outcome')) {
            Outcome::DISMISS => $this->configuredInt('casework.reporting.reputation.dismissed_delta', -1),
            Outcome::UPHOLD, Outcome::ESCALATE => $this->configuredInt('casework.reporting.reputation.upheld_delta', 1),
            // Application-defined outcomes carry no default polarity —
            // apps that need one bind their own policy.
            default => 0,
        };
    }

    private function configuredInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }
}
