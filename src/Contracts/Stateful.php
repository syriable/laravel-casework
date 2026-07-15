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
     * @internal Called by the workflow engine only (ADR-0012).
     */
    public function writeStateThroughTransition(string $state): void;
}
