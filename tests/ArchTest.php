<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;

$modelNamespaces = [
    'Syriable\Casework\Reporting\Models',
    'Syriable\Casework\Cases\Models',
    'Syriable\Casework\Enforcement\Models',
    'Syriable\Casework\Appeals\Models',
    'Syriable\Casework\Audit\Models',
];

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
    ->toBeFinal()
    ->ignoring([
        'Syriable\Casework\Support\Concerns',
        // Enums are implicitly final; the source parser cannot see it.
        'Syriable\Casework\Support\Origin',
    ]);

arch('origin is a string-backed enum')
    ->expect('Syriable\Casework\Support\Origin')
    ->toBeStringBackedEnums();

// ADR-0017: exceptions are final; the catch surface is the marker interface.
arch('exceptions are final')
    ->expect('Syriable\Casework\Exceptions')
    ->toBeFinal()
    ->ignoring('Syriable\Casework\Exceptions\CaseworkException');

// ADR-0017: Eloquent models are designed for subclassing (X1).
arch('models are open for extension')
    ->expect($modelNamespaces)
    ->not->toBeFinal();

// ADR-0004 layering: models never dispatch events or touch the container.
arch('models stay side-effect-free')
    ->expect($modelNamespaces)
    ->not->toUse([
        Event::class,
        'event',
        'dispatch',
        'app',
        'resolve',
    ]);

// ADR-0017: the workflow engine and its data carriers are final; the
// definitions are the designed subclass point (ADR-0013).
arch('the workflow engine is final')
    ->expect([
        'Syriable\Casework\States\Workflow',
        'Syriable\Casework\States\TransitionDefinition',
        'Syriable\Casework\States\TransitionContext',
        'Syriable\Casework\States\Events\StateTransitioned',
    ])
    ->toBeFinal();

arch('workflow definitions are open for extension')
    ->expect([
        'Syriable\Casework\Reporting\ReportWorkflow',
        'Syriable\Casework\Cases\CaseWorkflow',
        'Syriable\Casework\Enforcement\RestrictionWorkflow',
        'Syriable\Casework\Appeals\AppealWorkflow',
    ])
    ->not->toBeFinal();

// Integration edge stays final (ADR-0017).
arch('the service provider and facade are final')
    ->expect([
        'Syriable\Casework\CaseworkServiceProvider',
        'Syriable\Casework\Casework',
        'Syriable\Casework\Facades\Casework',
    ])
    ->toBeFinal();
