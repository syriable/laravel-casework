# Enforcement

Restrictions, suspensions, and warnings are first-class records with
lifecycles — never boolean columns. Mark restrictable models:

```php
use Syriable\Casework\Concerns\InteractsWithRestrictions;
use Syriable\Casework\Contracts\Restrictable;

class User extends Model implements Restrictable
{
    use InteractsWithRestrictions;
}
```

## Applying restrictions

Directly, or carried by a decision (see [cases & decisions](cases-and-decisions.md)):

```php
use Syriable\Casework\Facades\Casework;

$restriction = Casework::restrict($user, 'posting')
    ->by($moderator)                 // or ->bySystem()
    ->for(days: 7)                   // or ->until($date) or ->permanently()
    ->inScope('listings')            // optional
    ->because('Spam wave')           // optional rationale
    ->apply();

$suspension = Casework::suspend($user)->by($moderator)->for(days: 30)->apply();
$warning    = Casework::warn($user)->by($moderator)->because('First offence.')->issue();
```

A duration choice is required — `for()`, `until()`, or `permanently()`.
Restriction types are an open set: `suspension` ships; add your own via
`casework.enforcement.restriction_types`.

## Checking restrictions — the hot path

One indexed query, honoring expiry in real time:

```php
$user->isRestricted();                       // any active restriction
$user->isRestricted('posting', 'listings');  // by type and scope
$user->isSuspended();

Casework::isRestricted($user, type: 'posting', scope: 'listings');
```

**The real-time rule:** a temporary restriction past its `expires_at` counts
as inactive everywhere immediately — the scheduled command below only
formalizes the transition for audit and events. Correctness never depends on
scheduler cadence.

## Lifting and expiring

```php
Casework::lift($restriction, by: $moderator, reason: 'Appeal upheld');
```

Lifting requires a *currently* active restriction and records who lifted it
and why. Schedule the expiry bookkeeping in your application — the package
registers no schedule itself (it stays UI-agnostic), so add it where you
define scheduled tasks:

```php
// routes/console.php (Laravel 11+) or app/Console/Kernel.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('casework:expire-restrictions')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

Cadence is a bookkeeping choice, never a correctness one: `isRestricted()`
and `activeRestrictions()` already honor expiry in real time (I-09), so a
missed run only delays the `RestrictionExpired` events and audit entries,
never the enforcement itself.

## Warnings

Warnings are deliberately not a state machine — activity is time-derived:

```php
$user->warnings()->count();
$user->activeWarnings()->count();
```

## Querying history

```php
use Syriable\Casework\Enforcement\Models\Restriction;

Restriction::query()->active()->ofType('suspension')->forSubject($user)->get();
Restriction::query()->expiringBefore(now()->addDay())->get();

$user->restrictions;        // full history — history survives everything
$user->activeRestrictions;  // currently enforceable only
```

## Authorization

`apply`, `lift`, and `issue` are denied by default for model actors —
register your own policies for the restriction and warning models to grant
them. System attribution (automation, decisions applied by triage) bypasses
policies; moderator scopes from your `ScopeResolver` apply on top of any
grant.

## Events

`RestrictionApplied`, `RestrictionLifted`, `RestrictionExpired`,
`RestrictionSuperseded`, `WarningIssued` — all after-commit, all with
matching `restriction.*` / `warning.*` audit entries. When applied through a
decision, enforcement events dispatch before the summarizing `CaseDecided`.
