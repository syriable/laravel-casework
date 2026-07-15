<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use LogicException;

/**
 * Thrown at boot when a workflow definition violates the ADR-0013
 * extension rules — never at runtime.
 */
final class InvalidWorkflow extends LogicException implements CaseworkException
{
    private function __construct(
        public readonly string $workflow,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function violation(string $workflow, string $reason): self
    {
        return new self($workflow, "Invalid workflow [{$workflow}]: {$reason}");
    }
}
