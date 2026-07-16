<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

/**
 * Shipped restriction types. An open set: applications extend
 * it via config('casework.enforcement.restriction_types').
 */
final class RestrictionType
{
    public const string SUSPENSION = 'suspension';

    /** @return list<string> */
    public static function shipped(): array
    {
        return [self::SUSPENSION];
    }
}
