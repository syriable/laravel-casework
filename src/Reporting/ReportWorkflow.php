<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting;

use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\WorkflowDefinition;

/**
 * The report lifecycle, verbatim from docs/guide/workflows.md.
 */
class ReportWorkflow extends WorkflowDefinition
{
    final protected function coreStates(): array
    {
        return array_column(ReportState::cases(), 'value');
    }

    final protected function coreTerminalStates(): array
    {
        return [ReportState::Resolved->value, ReportState::Dismissed->value];
    }

    final protected function coreTransitions(): array
    {
        $pending = ReportState::Pending->value;
        $underReview = ReportState::UnderReview->value;
        $attached = ReportState::AttachedToCase->value;

        return [
            new TransitionDefinition('file', [], $pending),
            new TransitionDefinition('startReview', [$pending], $underReview),
            new TransitionDefinition('attachToCase', [$pending, $underReview], $attached),
            new TransitionDefinition('dismiss', [$pending, $underReview], ReportState::Dismissed->value),
            new TransitionDefinition('resolve', [$pending, $underReview, $attached], ReportState::Resolved->value),
        ];
    }
}
