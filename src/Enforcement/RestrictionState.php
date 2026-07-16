<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement;

/**
 * Core restriction lifecycle states (docs/guide/workflows.md).
 */
enum RestrictionState: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Lifted = 'lifted';
    case Superseded = 'superseded';
}
