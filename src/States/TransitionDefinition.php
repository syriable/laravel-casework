<?php

declare(strict_types=1);

namespace Syriable\Casework\States;

use Syriable\Casework\Contracts\TransitionGuard;

/**
 * One row of a lifecycle's transition table (ADR-0012). An empty $from
 * list marks a creation transition — from the implicit (new) pseudo-state
 * (workflows overview §3).
 */
final readonly class TransitionDefinition
{
    /**
     * @param  list<string>  $from
     * @param  list<class-string<TransitionGuard>>  $guards
     */
    public function __construct(
        public string $name,
        public array $from,
        public string $to,
        public array $guards = [],
    ) {}

    public function isCreation(): bool
    {
        return $this->from === [];
    }

    public function allowsFrom(string $state): bool
    {
        return in_array($state, $this->from, true);
    }
}
