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
     * With $expectedFrom the write is optimistic (compare-and-swap):
     * a conditional UPDATE that fails — returning false — when a
     * concurrent transition already moved the row (Phase 15 R-01).
     * Without it, the write is unconditional.
     *
     * @internal Called by the workflow engine only (ADR-0012).
     */
    public function writeStateThroughTransition(string $state, ?string $expectedFrom = null): bool
    {
        if ($expectedFrom !== null && $this->exists) {
            $values = ['state' => $state];

            $updatedAt = $this->getUpdatedAtColumn();

            if ($this->usesTimestamps() && is_string($updatedAt)) {
                $values[$updatedAt] = $this->freshTimestamp();
            }

            $written = $this->newQuery()
                ->whereKey($this->getKey())
                ->where('state', $expectedFrom)
                ->toBase()
                ->update($values);

            if ($written === 0) {
                return false;
            }

            $this->allowsStateWrite = true;

            try {
                foreach ($values as $key => $value) {
                    $this->setAttribute($key, $value);
                }
            } finally {
                $this->allowsStateWrite = false;
            }

            $this->syncOriginal();

            return true;
        }

        $this->allowsStateWrite = true;

        try {
            $this->setAttribute('state', $state);
            $this->save();
        } finally {
            $this->allowsStateWrite = false;
        }

        return true;
    }
}
