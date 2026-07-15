# Cases & Decisions

A **case** is the unit of moderation work: one or more reports about a primary
subject, moving through investigation to a decision. The model class is
`CaseFile` (`case` is a PHP reserved word); everywhere else — methods, events,
tables — the domain word stays "case".

## Opening cases

Manually:

```php
use Syriable\Casework\Facades\Casework;

$case = Casework::openCase($post)
    ->by($moderator)            // or ->bySystem()
    ->withReports($reports)     // optional: attach existing open reports
    ->priority('high')          // optional: default from config
    ->open();
```

Automatically, at report intake, per `casework.cases.strategy`:

| Strategy | Behavior |
|---|---|
| `threshold` (default) | a report joins the subject's open case when one exists; otherwise a case opens once the subject accumulates `casework.cases.threshold` open reports — the earlier open reports are attached alongside |
| `always` | every report opens or joins the subject's case |
| `manual` | reports never open or join cases automatically |
| class name | your own `Contracts\CaseStrategy` implementation |

## Working a case

```php
Casework::attachReport($report, to: $case, by: $moderator);
Casework::assignCase($case, to: $moderator, by: $lead);
Casework::startInvestigation($case, by: $moderator);        // open → under_investigation
Casework::submitForDecision($case, by: $moderator);         // → awaiting_decision
Casework::escalateCase($case, by: $moderator, priority: 'urgent');
Casework::closeCase($case, by: $moderator);                 // decided → closed
```

Assignment and escalation are recorded and evented but are not state
transitions. Closing requires a decided case; there is no reopen — corrections
are superseding decisions or new cases.

## Investigation records

Notes and evidence are immutable once written (corrections are new records):

```php
Casework::note($case, by: $moderator, body: 'Subject has two prior cases.');
Casework::attachEvidence($case, by: $moderator, subject: $otherPost, data: ['hash' => '…']);

$case->notes;      // chronological
$case->evidence;
$case->reports;
```

## Authorization and scopes

All case abilities are **denied by default** for model actors — register your
own policy for the case model to grant them. On top of any policy grant, the
`ScopeResolver` contract confines moderators to their scopes: when the resolver
gives an actor a scope list and the case's subject belongs to a scope outside
it, the action is denied. Bind your resolver to activate scoping:

```php
$this->app->singleton(
    \Syriable\Casework\Contracts\ScopeResolver::class,
    App\Moderation\CategoryScopeResolver::class,
);
```

## Querying

```php
use Syriable\Casework\Cases\Models\CaseFile;

CaseFile::query()->open()->assignedTo($moderator)->wherePriority('high')->get();
CaseFile::query()->forSubject($post)->decided()->get();
```

## Decisions

A decision resolves a case — atomically transitioning it, resolving its open
reports, and applying any enforcement actions in one transaction:

```php
$decision = Casework::decide($case)
    ->by($moderator)
    ->outcome(Outcome::UPHOLD)                 // dismiss / uphold / escalate / custom
    ->rationale('Repeated spam after warning.')
    ->withSuspension(days: 30)
    ->withRestriction('posting', permanent: true, scope: 'listings')
    ->withWarning('Final notice.')
    ->finalize();
```

Decisions are immutable: amending or reversing means a new decision
referencing the original via `->supersedes($decision)`. Outcomes are an open
set — `dismiss`, `uphold`, and `escalate` ship; add your own via
`casework.decisions.outcomes`. When `prevent_self_moderation` is on
(default), an actor cannot decide a case whose subject is themselves.

Enforcement details — restriction types, durations, lifting, the real-time
expiry rule — live in the [enforcement guide](enforcement.md).
