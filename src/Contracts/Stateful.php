<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

/**
 * A model whose lifecycle state is machine-managed (invariant I-03).
 * Implemented by the GuardsStateColumn concern.
 */
interface Stateful
{
    /**
     * Write the state column. With $expectedFrom, the write is a
     * compare-and-swap: it succeeds only if the stored state still
     * matches, returning false when a concurrent transition moved the
     * row first (Phase 15 finding R-01).
     *
     * @internal Called by the workflow engine only (ADR-0012).
     */
    public function writeStateThroughTransition(string $state, ?string $expectedFrom = null): bool;
}
