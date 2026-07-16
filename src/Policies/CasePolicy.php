<?php

declare(strict_types=1);

namespace Syriable\Casework\Policies;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Cases\Models\CaseFile;

/**
 * Safe-by-default case authorization: every moderation ability
 * is denied for model actors until the application registers its own
 * policy. System attribution bypasses policies; scope
 * enforcement runs in the actions on top of any policy grant.
 */
final class CasePolicy
{
    public function open(Model $actor): bool
    {
        return false;
    }

    public function assign(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function startInvestigation(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function submitForDecision(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function escalate(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function decide(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function close(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function note(Model $actor, CaseFile $case): bool
    {
        return false;
    }

    public function attachEvidence(Model $actor, CaseFile $case): bool
    {
        return false;
    }
}
