<?php

declare(strict_types=1);

namespace Syriable\Casework\Support\Concerns;

use Syriable\Casework\Exceptions\ImmutableRecord;

/**
 * Invariant I-03: after creation, the state column changes only through
 * workflow transitions. The workflow engine (milestone M3) is the sole
 * caller of writeStateThroughTransition().
 */
trait GuardsStateColumn
{
    private bool $allowsStateWrite = false;

    public function setAttribute($key, $value): mixed
    {
        if ($key === 'state' && $this->exists && ! $this->allowsStateWrite) {
            throw ImmutableRecord::stateWriteAttempted($this);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @internal Called by the workflow engine only (ADR-0012).
     */
    public function writeStateThroughTransition(string $state): void
    {
        $this->allowsStateWrite = true;

        try {
            $this->setAttribute('state', $state);
            $this->save();
        } finally {
            $this->allowsStateWrite = false;
        }
    }
}
