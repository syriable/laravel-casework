<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

use RuntimeException;

/**
 * Thrown at report intake when the reason key does not exist or the
 * reason is deactivated (FR-153/155 — history keeps inactive reasons,
 * new reports may not use them).
 */
final class UnknownReason extends RuntimeException implements CaseworkException
{
    private function __construct(
        public readonly string $key,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forKey(string $key): self
    {
        return new self($key, "Report reason [{$key}] does not exist.");
    }

    public static function inactive(string $key): self
    {
        return new self($key, "Report reason [{$key}] is deactivated and cannot be used for new reports.");
    }
}
