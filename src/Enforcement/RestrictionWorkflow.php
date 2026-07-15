<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement;

use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\WorkflowDefinition;

/**
 * The restriction lifecycle, verbatim from docs/workflows/restriction.md.
 * The real-time expiry rule (I-09) lives in the model's activity checks;
 * the `expire` transition is bookkeeping.
 */
class RestrictionWorkflow extends WorkflowDefinition
{
    final protected function coreStates(): array
    {
        return array_column(RestrictionState::cases(), 'value');
    }

    final protected function coreTerminalStates(): array
    {
        return [
            RestrictionState::Expired->value,
            RestrictionState::Lifted->value,
            RestrictionState::Superseded->value,
        ];
    }

    final protected function coreTransitions(): array
    {
        $active = RestrictionState::Active->value;

        return [
            new TransitionDefinition('apply', [], $active),
            new TransitionDefinition('expire', [$active], RestrictionState::Expired->value),
            new TransitionDefinition('lift', [$active], RestrictionState::Lifted->value),
            new TransitionDefinition('supersede', [$active], RestrictionState::Superseded->value),
        ];
    }
}
