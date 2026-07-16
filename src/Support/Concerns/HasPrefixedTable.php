<?php

declare(strict_types=1);

namespace Syriable\Casework\Support\Concerns;

/**
 * Resolves the model's table from the configured prefix.
 */
trait HasPrefixedTable
{
    public function getTable(): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$this->tableSuffix();
    }

    abstract protected function tableSuffix(): string;
}
