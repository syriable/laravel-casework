<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Reporting\Models\Report;

/**
 * How a reporter's reputation score moves in response to a report's
 * outcome (extension point X14). Selected via
 * config('casework.reporting.reputation.policy'); the default
 * implementation reads fixed deltas from config, but applications may
 * bind their own — weighting by reason severity, account age, or
 * anything else the score should account for.
 */
interface ReputationPolicy
{
    /**
     * The score delta to apply when one of the reporter's reports is
     * dismissed without ever reaching a decision (found unfounded
     * before a case existed). Negative weakens the reporter's score;
     * zero leaves it unchanged.
     */
    public function deltaForDismissal(Report $report): int;

    /**
     * The score delta to apply when one of the reporter's reports is
     * resolved by a case decision — of any outcome, including
     * application-defined ones. Positive strengthens the reporter's
     * score (the report held up), negative weakens it (it didn't),
     * zero leaves it unchanged (e.g. an outcome the policy has no
     * opinion about).
     */
    public function deltaForResolution(Report $report, Decision $decision): int;
}
