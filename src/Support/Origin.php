<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

/**
 * Attribution origin for any domain action (ADR-0002).
 *
 * `Anonymous` is valid only for report reporters; every other domain
 * action requires `Model` or `System`.
 */
enum Origin: string
{
    case Model = 'model';
    case System = 'system';
    case Anonymous = 'anonymous';
}
