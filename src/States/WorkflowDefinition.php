<?php

declare(strict_types=1);

namespace Syriable\Casework\States;

use Syriable\Casework\Exceptions\InvalidWorkflow;

/**
 * A declarative lifecycle definition (ADR-0012). Concrete shipped
 * definitions declare their core tables as final methods; applications
 * extend by subclassing and overriding customStates()/customTransitions()
 * — add-only, within the ADR-0013 rules, verified by validate() at boot.
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
     * Application-added states (ADR-0013). Add-only.
     *
     * @return list<string>
     */
    protected function customStates(): array
    {
        return [];
    }

    /**
     * Application-added transitions (ADR-0013). Add-only.
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
        return [...$this->coreStates(), ...$this->customStates()];
    }

    /**
     * Terminal states are always the core terminals: custom states may
     * never be terminal and terminals stay closed (ADR-0013 rules 2–3).
     *
     * @return list<string>
     */
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
     * win over custom rows with the same name (rule 1).
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
     * Boot-time enforcement of the ADR-0013 rules. Violations throw —
     * never at runtime.
     *
     * @throws InvalidWorkflow
     */
    final public function validate(): void
    {
        $name = static::class;
        $core = $this->coreStates();
        $custom = $this->customStates();
        $states = $this->states();
        $terminals = $this->terminalStates();

        // Rule 5: custom state names are non-empty, fit the column, and
        // do not collide with core (or each other).
        foreach ($custom as $state) {
            if ($state === '' || strlen($state) > 32) {
                throw InvalidWorkflow::violation($name, 'custom state names must be non-empty strings of at most 32 characters');
            }

            if (in_array($state, $core, true)) {
                throw InvalidWorkflow::violation($name, "custom state [{$state}] collides with a core state");
            }
        }

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

                // Rule 2: terminals stay closed — for everyone.
                if (in_array($from, $terminals, true)) {
                    throw InvalidWorkflow::violation($name, "transition [{$transition->name}] leaves terminal state [{$from}]");
                }
            }

            if (! $isCustom) {
                continue;
            }

            // Rule 1: core transitions are non-retargetable. A custom row
            // sharing a core name may only widen the from-set toward the
            // same target (rule 6), and never duplicate a core pair.
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

        $this->assertConnected($name, $custom, $terminals);
    }

    /**
     * Rule 3: every custom state is reachable from creation and has a
     * path back to a core state or terminal — no traps.
     *
     * @param  list<string>  $custom
     * @param  list<string>  $terminals
     */
    private function assertConnected(string $name, array $custom, array $terminals): void
    {
        if ($custom === []) {
            return;
        }

        $forward = [];

        foreach ($this->transitions() as $transition) {
            $sources = $transition->isCreation() ? ['(new)'] : $transition->from;

            foreach ($sources as $source) {
                $forward[$source][] = $transition->to;
            }
        }

        $reachable = $this->walk('(new)', $forward);

        foreach ($custom as $state) {
            if (! in_array($state, $reachable, true)) {
                throw InvalidWorkflow::violation($name, "custom state [{$state}] is unreachable");
            }

            $exits = $this->walk($state, $forward);
            $core = array_diff($this->coreStates(), [$state]);

            if (array_intersect($exits, [...$core, ...$terminals]) === []) {
                throw InvalidWorkflow::violation($name, "custom state [{$state}] has no path back to a core or terminal state");
            }
        }
    }

    /**
     * States reachable from $start (exclusive) following $forward edges.
     *
     * @param  array<string, list<string>>  $forward
     * @return list<string>
     */
    private function walk(string $start, array $forward): array
    {
        $seen = [];
        $queue = $forward[$start] ?? [];

        while ($queue !== []) {
            $state = array_shift($queue);

            if (in_array($state, $seen, true)) {
                continue;
            }

            $seen[] = $state;
            $queue = [...$queue, ...($forward[$state] ?? [])];
        }

        return $seen;
    }
}
