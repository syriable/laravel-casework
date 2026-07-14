<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Uniform actor attribution (ADR-0002): a nullable polymorphic reference
 * plus an explicit origin. Invariant I-01 holds by construction — the only
 * way to build an ActorRef with an actor is via model(), and the only ways
 * to build one without are system() and anonymous().
 */
final readonly class ActorRef
{
    private function __construct(
        public ?Model $actor,
        public Origin $origin,
    ) {}

    public static function model(Model $actor): self
    {
        return new self($actor, Origin::Model);
    }

    public static function system(): self
    {
        return new self(null, Origin::System);
    }

    public static function anonymous(): self
    {
        return new self(null, Origin::Anonymous);
    }

    public function isModel(): bool
    {
        return $this->origin === Origin::Model;
    }

    public function isSystem(): bool
    {
        return $this->origin === Origin::System;
    }

    public function isAnonymous(): bool
    {
        return $this->origin === Origin::Anonymous;
    }
}
