<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

/**
 * Shipped decision outcomes. An open set: applications extend it
 * via config('casework.decisions.outcomes') — string-backed by design, not
 * by subclassing (ADR-0017).
 */
final class Outcome
{
    public const string DISMISS = 'dismiss';

    public const string UPHOLD = 'uphold';

    public const string ESCALATE = 'escalate';

    /** @return list<string> */
    public static function shipped(): array
    {
        return [self::DISMISS, self::UPHOLD, self::ESCALATE];
    }
}
