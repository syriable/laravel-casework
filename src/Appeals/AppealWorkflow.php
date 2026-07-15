<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals;

use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\WorkflowDefinition;

/**
 * The appeal lifecycle, verbatim from docs/workflows/appeal.md.
 */
class AppealWorkflow extends WorkflowDefinition
{
    final protected function coreStates(): array
    {
        return array_column(AppealState::cases(), 'value');
    }

    final protected function coreTerminalStates(): array
    {
        return [
            AppealState::Upheld->value,
            AppealState::Overturned->value,
            AppealState::Rejected->value,
        ];
    }

    final protected function coreTransitions(): array
    {
        $submitted = AppealState::Submitted->value;
        $underReview = AppealState::UnderReview->value;

        return [
            new TransitionDefinition('submit', [], $submitted),
            new TransitionDefinition('startReview', [$submitted], $underReview),
            new TransitionDefinition('uphold', [$underReview], AppealState::Upheld->value),
            new TransitionDefinition('overturn', [$underReview], AppealState::Overturned->value),
            new TransitionDefinition('reject', [$submitted, $underReview], AppealState::Rejected->value),
        ];
    }
}
