<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases;

use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\WorkflowDefinition;

/**
 * The case lifecycle, verbatim from docs/workflows/case.md. `decide` is
 * legal from every pre-decided core state — lightweight flows may skip
 * investigation.
 */
class CaseWorkflow extends WorkflowDefinition
{
    final protected function coreStates(): array
    {
        return array_column(CaseState::cases(), 'value');
    }

    final protected function coreTerminalStates(): array
    {
        return [CaseState::Closed->value];
    }

    final protected function coreTransitions(): array
    {
        $open = CaseState::Open->value;
        $investigating = CaseState::UnderInvestigation->value;
        $awaiting = CaseState::AwaitingDecision->value;
        $decided = CaseState::Decided->value;

        return [
            new TransitionDefinition('open', [], $open),
            new TransitionDefinition('startInvestigation', [$open], $investigating),
            new TransitionDefinition('submitForDecision', [$investigating], $awaiting),
            new TransitionDefinition('decide', [$open, $investigating, $awaiting], $decided),
            new TransitionDefinition('close', [$decided], CaseState::Closed->value),
        ];
    }
}
