<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Scoped moderation (FR-602): the application decides which scopes an
 * actor may moderate and which scope a subject belongs to. Bind your own
 * implementation in the container to activate scoping (extension point X6).
 */
interface ScopeResolver
{
    /**
     * Scopes within which the actor may moderate; null = unscoped (all).
     *
     * @return list<string>|null
     */
    public function scopesFor(Model $actor): ?array;

    /**
     * The scope a given subject belongs to; null = unscoped.
     */
    public function scopeOf(Model $subject): ?string;
}
