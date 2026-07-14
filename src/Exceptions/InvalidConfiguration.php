<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use InvalidArgumentException;

/**
 * Thrown at boot when config/casework.php contains an invalid value
 * (ADR-0016: fail fast at boot, never at runtime).
 */
final class InvalidConfiguration extends InvalidArgumentException implements CaseworkException
{
    private function __construct(
        public readonly string $key,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forKey(string $key, string $reason): self
    {
        return new self($key, "Invalid casework configuration [{$key}]: {$reason}");
    }
}
