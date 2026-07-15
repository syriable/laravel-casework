# Quickstart

The full moderation loop — report → case → decision → enforcement →
appeal — in one sitting. [Install](installation.md) first.

## 1. Mark your models

```php
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Concerns\InteractsWithReports;
use Syriable\Casework\Concerns\InteractsWithRestrictions;
use Syriable\Casework\Contracts\Reportable;
use Syriable\Casework\Contracts\Restrictable;

class User extends Model implements Reportable, Restrictable
{
    use InteractsWithReports;
    use InteractsWithRestrictions;
}
```

Anything reportable gets `reports()`, `openReports()`, `cases()`;
anything restrictable gets `restrictions()`, `warnings()`,
`isRestricted()`, `isSuspended()`.

## 2. Seed a report reason

Reasons are rows, not code:

```php
use Syriable\Casework\Reporting\Models\Reason;

Reason::create(['key' => 'spam', 'label' => 'Spam or misleading', 'is_active' => true]);
```

## 3. File a report

```php
use Syriable\Casework\Facades\Casework;

$report = Casework::report($post)
    ->by($reportingUser)
    ->because('spam')
    ->comment('Links to a phishing site')
    ->file();
```

By default three open reports on a subject open a case automatically
(`casework.cases.strategy`). Open one explicitly any time:

```php
$case = Casework::openCase($post)->bySystem()->open();
```

## 4. Decide, with enforcement attached

```php
use Syriable\Casework\Support\Outcome;

$decision = Casework::decide($case)
    ->by($moderator)
    ->outcome(Outcome::UPHOLD)
    ->rationale('Repeated spam after warning.')
    ->withSuspension(days: 30)
    ->finalize();
```

The case transition, report resolution, restriction, and audit trail
commit atomically. (Moderation abilities are denied by default —
grant them via [policies](authorization.md).)

## 5. Check enforcement — the hot path

```php
$user->isSuspended();                       // one indexed query
Casework::isRestricted($user, type: 'suspension');
```

Expiry is honored in real time; see [enforcement](enforcement.md).

## 6. Appeal

```php
$appeal = Casework::appeal($decision)->by($user)->statement('This was a mistake.')->submit();

Casework::startAppealReview($appeal, by: $reviewer);
Casework::resolveAppeal($appeal)->by($reviewer)->overturn(rationale: 'Evidence insufficient');
```

An overturn lifts the decision's active restrictions and records a
superseding decision, atomically. See [appeals](appeals.md).

## Why "CaseFile"?

The case entity is `CaseFile` because `case` is a reserved word in PHP
(ADR-0008) — tables, config keys, and docs still say "case".

## Where next

Every step above has a full guide: [reporting](reporting.md),
[cases & decisions](cases-and-decisions.md),
[enforcement](enforcement.md), [appeals](appeals.md),
[audit](audit.md), [events](events.md), [automation](automation.md),
[extending](extending.md).
