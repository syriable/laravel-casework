<?php

declare(strict_types=1);

namespace Syriable\Casework\States;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Contracts\Stateful;
use Syriable\Casework\Contracts\TransitionGuard;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\States\Events\StateTransitioned;
use Syriable\Casework\Support\ActorRef;

/**
 * The single small engine executing declarative definitions (ADR-0012).
 * Called only by actions inside their transaction: verify from-state →
 * run guards → write state. Audit and dedicated events are the calling
 * action's next steps (ADR-0005 pipeline); custom transitions dispatch
 * the generic StateTransitioned here (ADR-0013 rule 4).
 */
final readonly class Workflow
{
    public function __construct(
        public WorkflowDefinition $definition,
    ) {}

    /**
     * Execute a transition on a persisted record.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidTransition
     */
    public function transition(Model&Stateful $record, string $name, ActorRef $by, array $context = []): TransitionContext
    {
        $from = self::stateOf($record);

        $transition = $this->definition->find($name, $from)
            ?? throw InvalidTransition::for($record, $name, $from);

        $transitionContext = new TransitionContext($record, $transition, $by, $context);

        $this->runGuards($transitionContext);

        $record->writeStateThroughTransition($transition->to);

        if ($this->definition->isCustom($transition)) {
            event(new StateTransitioned($record, $name, $from, $transition->to, $by));
        }

        return $transitionContext;
    }

    /**
     * Execute a creation transition: guards run, the initial state is set
     * on the unsaved record; the calling action persists it.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidTransition
     */
    public function initialize(Model&Stateful $record, string $name, ActorRef $by, array $context = []): TransitionContext
    {
        $transition = $this->definition->creation($name)
            ?? throw InvalidTransition::for($record, $name, '(new)');

        $transitionContext = new TransitionContext($record, $transition, $by, $context);

        $this->runGuards($transitionContext);

        $record->setAttribute('state', $transition->to);

        return $transitionContext;
    }

    public static function stateOf(Model $record): string
    {
        $state = $record->getAttribute('state');

        return is_string($state) ? $state : '';
    }

    private function runGuards(TransitionContext $context): void
    {
        foreach ($context->transition->guards as $guard) {
            /** @var TransitionGuard $instance */
            $instance = app($guard);

            $instance->check($context);
        }
    }
}
