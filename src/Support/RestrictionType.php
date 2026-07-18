<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

/**
 * Shipped restriction types. An open set: applications extend
 * it via config('casework.enforcement.restriction_types').
 *
 * `MESSAGING` is the conventional type name for communication
 * gating (e.g. syriable/laravel-converse's NotRestrictedPolicy).
 * It is listed in the default `restriction_types` config rather
 * than here, so hosts can remove it without colliding with the
 * open-set validator.
 */
final class RestrictionType
{
    public const string SUSPENSION = 'suspension';

    public const string MESSAGING = 'messaging';

    /** @return list<string> */
    public static function shipped(): array
    {
        return [self::SUSPENSION];
    }
}
