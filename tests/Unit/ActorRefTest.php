<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Origin;

/**
 * @see docs/adr/0002-reporter-and-actor-identity.md — invariant I-01 holds
 * by construction: origin=model ⇔ actor present.
 */
it('builds a model-origin ref with the actor present', function (): void {
    $actor = new class extends Model {};

    $ref = ActorRef::model($actor);

    expect($ref->origin)->toBe(Origin::Model)
        ->and($ref->actor)->toBe($actor)
        ->and($ref->isModel())->toBeTrue()
        ->and($ref->isSystem())->toBeFalse()
        ->and($ref->isAnonymous())->toBeFalse();
});

it('builds a system ref with no actor', function (): void {
    $ref = ActorRef::system();

    expect($ref->origin)->toBe(Origin::System)
        ->and($ref->actor)->toBeNull()
        ->and($ref->isSystem())->toBeTrue();
});

it('builds an anonymous ref with no actor', function (): void {
    $ref = ActorRef::anonymous();

    expect($ref->origin)->toBe(Origin::Anonymous)
        ->and($ref->actor)->toBeNull()
        ->and($ref->isAnonymous())->toBeTrue();
});

it('backs origins with the ADR-0002 column values', function (): void {
    expect(Origin::Model->value)->toBe('model')
        ->and(Origin::System->value)->toBe('system')
        ->and(Origin::Anonymous->value)->toBe('anonymous');
});
