<?php

declare(strict_types=1);

namespace Syriable\Casework\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Syriable\Casework\Casework
 */
final class Casework extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Casework\Casework::class;
    }
}
