# Authorization

Every operation authorizes before executing (FR-601). Three layers:

1. **Policies** — Gate policies on the package models, safe-by-default.
2. **Scopes** — a `ScopeResolver` bounding *where* an actor may moderate.
3. **Built-in guards** — self-moderation and reviewer independence.

## Actors and attribution

Operations take a `Model`, `ActorRef::model($m)`, `ActorRef::system()`,
or (for reporting) `ActorRef::anonymous()`. **System attribution
bypasses policies by design** (FR-805) — it is how schedulers, intake
stages, and triage automation act. Anonymous actors are governed by
the specific operation's own rules (e.g.
`casework.reporting.allow_anonymous`).

## Default policies

The package registers a policy per model at boot — only when your
application hasn't registered its own. Defaults are safe-by-default:
any model actor may `file` a report and that is all; every moderation
ability (`startReview`, `dismiss`, `decide`, `apply`, `lift`, `warn`,
`submit`/`assign`/`review`/`resolve` on appeals, …) denies until you
grant it.

Override by registering your own policy — it replaces the default
entirely:

```php
use Syriable\Casework\Cases\Models\CaseFile;

Gate::policy(CaseFile::class, App\Policies\CasePolicy::class);
```

```php
class CasePolicy
{
    public function decide(User $user, CaseFile $case): bool
    {
        return $user->hasRole('moderator');   // e.g. spatie/laravel-permission
    }
}
```

**Overrides own their consequences.** The shipped defaults deny
unknown actors and keep the self-moderation guard meaningful; a policy
that returns `true` broadly hands moderation power to whoever reaches
your code path. The package will not re-tighten what you loosen —
review policy overrides like you review middleware.

## Scoped moderation

Bind a `ScopeResolver` to bound actors to segments of your platform
(FR-602):

```php
use Syriable\Casework\Contracts\ScopeResolver;

class CategoryScopeResolver implements ScopeResolver
{
    public function scopesFor(Model $actor): ?array   // null = unscoped
    {
        return $actor->moderatedCategories()->pluck('slug')->all();
    }

    public function scopeOf(Model $subject): ?string  // null = unscoped
    {
        return $subject->category?->slug;
    }
}

$this->app->singleton(ScopeResolver::class, CategoryScopeResolver::class);
```

When the resolver scopes an actor and a subject belongs to a scope
outside that set, the operation is denied **regardless of policy
grants**. The default `NullScopeResolver` leaves everything unscoped.

## Self-moderation and independence

With `casework.authorization.prevent_self_moderation` on (default),
actors cannot decide cases about themselves or review appeals they
filed (FR-604). Independently,
`casework.appeals.require_independent_reviewer` keeps the appeal
reviewer distinct from the original decider/issuer (I-12) — see
[appeals](appeals.md).
