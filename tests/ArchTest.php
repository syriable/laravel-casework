<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('package code declares strict types')
    ->expect('Syriable\Casework')
    ->toUseStrictTypes();

// ADR-0017: contracts are pure interfaces.
arch('contracts are interfaces')
    ->expect('Syriable\Casework\Contracts')
    ->toBeInterfaces();

// ADR-0017: value objects and support services are final.
arch('support classes are final')
    ->expect('Syriable\Casework\Support')
    ->classes()
    ->toBeFinal();

// ADR-0017: exceptions are final (catch surfaces are interfaces/classes).
arch('exceptions are final')
    ->expect('Syriable\Casework\Exceptions')
    ->classes()
    ->toBeFinal();

// ADR-0017: Eloquent models are designed for subclassing (X1).
arch('models are open for extension')
    ->expect('Syriable\Casework')
    ->classes()
    ->extending(Model::class)
    ->not->toBeFinal();

// ADR-0004 layering: models never dispatch events or touch the container.
arch('models stay side-effect-free')
    ->expect('Syriable\Casework\*\Models')
    ->not->toUse([
        Event::class,
        'event',
        'dispatch',
        'app',
        'resolve',
    ]);
