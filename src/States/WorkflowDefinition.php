<?php

declare(strict_types=1);

namespace Syriable\Casework\States;

use Syriable\Casework\Exceptions\InvalidWorkflow;

/**
 * A declarative lifecycle definition (ADR-0012). Concrete shipped
 * definitions declare their core tables as final methods; applications
 * extend by subclassing and overriding customTransitions() — add-only
 * transitions between existing states, within the ADR-0019 rules,
 * verified by validate() at boot.
 */
abstract class WorkflowDefinition
{
    /** @var list<TransitionDefinition>|null */
    private ?array $resolvedCore = null;

    /** @var list<TransitionDefinition>|null */
    private ?array $resolvedCustom = null;

    /** @return list<string> */
    abstract protected function coreStates(): array;

    /** @return list<string> */
    abstract protected function coreTerminalStates(): array;

    /** @return list<TransitionDefinition> */
    abstract protected function coreTransitions(): array;

    /**
     * Application-added transitions between existing states (ADR-0019).
     * Add-only: a custom transition may never introduce a new state,
     * retarget a core transition, or leave a terminal state.
     *
     * @return list<TransitionDefinition>
     */
    protected function customTransitions(): array
    {
        return [];
    }

    /** @return list<string> */
    final public function states(): array
    {
        return $this->coreStates();
    }

    /** @return list<string> */
    final public function terminalStates(): array
    {
        return $this->coreTerminalStates();
    }

    /** @return list<TransitionDefinition> */
    final public function transitions(): array
    {
        return [...$this->resolvedCoreTransitions(), ...$this->resolvedCustomTransitions()];
    }

    final public function isCustom(TransitionDefinition $transition): bool
    {
        return in_array($transition, $this->resolvedCustomTransitions(), true);
    }

    /**
     * The declared tables are built once — lookups and isCustom() rely on
     * instance identity, so every accessor must see the same objects.
     *
     * @return list<TransitionDefinition>
     */
    private function resolvedCoreTransitions(): array
    {
        return $this->resolvedCore ??= $this->coreTransitions();
    }

    /** @return list<TransitionDefinition> */
    private function resolvedCustomTransitions(): array
    {
        return $this->resolvedCustom ??= $this->customTransitions();
    }

    /**
     * The definition allowing $name from $fromState, if any. Core rows
     * win over custom rows with the same name.
     */
    final public function find(string $name, string $fromState): ?TransitionDefinition
    {
        foreach ($this->transitions() as $transition) {
            if ($transition->name === $name && $transition->allowsFrom($fromState)) {
                return $transition;
            }
        }

        return null;
    }

    final public function creation(string $name): ?TransitionDefinition
    {
        foreach ($this->transitions() as $transition) {
            if ($transition->name === $name && $transition->isCreation()) {
                return $transition;
            }
        }

        return null;
    }

    /**
     * Boot-time enforcement of the ADR-0019 rules. Violations throw —
     * never at runtime. Custom transitions may only connect existing
     * states: there is no state-reachability analysis to run, because
     * a custom transition can never introduce a state that wasn't
     * already there.
     *
     * @throws InvalidWorkflow
     */
    final public function validate(): void
    {
        $name = static::class;
        $states = $this->states();
        $terminals = $this->terminalStates();

        if (count($states) !== count(array_unique($states))) {
            throw InvalidWorkflow::violation($name, 'state names must be unique');
        }

        $coreTransitions = $this->resolvedCoreTransitions();

        foreach ($this->transitions() as $transition) {
            $isCustom = $this->isCustom($transition);

            // Every endpoint must be a declared state.
            if (! in_array($transition->to, $states, true)) {
                throw InvalidWorkflow::violation($name, "transition [{$transition->name}] targets undeclared state [{$transition->to}]");
            }

            foreach ($transition->from as $from) {
                if (! in_array($from, $states, true)) {
                    throw InvalidWorkflow::violation($name, "transition [{$transition->name}] starts from undeclared state [{$from}]");
                }

                // Terminals stay closed — for everyone.
                if (in_array($from, $terminals, true)) {
                    throw InvalidWorkflow::violation($name, "transition [{$transition->name}] leaves terminal state [{$from}]");
                }
            }

            if (! $isCustom) {
                continue;
            }

            // Core transitions are non-retargetable. A custom row sharing
            // a core name may only widen the from-set toward the same
            // target, and never duplicate a core pair.
            foreach ($coreTransitions as $coreTransition) {
                if ($coreTransition->name !== $transition->name) {
                    continue;
                }

                if ($coreTransition->to !== $transition->to) {
                    throw InvalidWorkflow::violation($name, "custom transition [{$transition->name}] retargets a core transition");
                }

                if (array_intersect($transition->from, $coreTransition->from) !== []) {
                    throw InvalidWorkflow::violation($name, "custom transition [{$transition->name}] duplicates a core from-state");
                }
            }
        }
    }
}
