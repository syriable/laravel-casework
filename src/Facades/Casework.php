<?php

namespace Syriable\Casework\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Syriable\Casework\Casework
 */
class Casework extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Casework\Casework::class;
    }
}
