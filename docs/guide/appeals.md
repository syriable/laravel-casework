# Appeals

A decision or a restriction can be appealed by the affected actor.
Appeals are first-class records with a state machine-managed
lifecycle: `submitted → under_review → upheld / overturned /
rejected`.

## Submitting an appeal

```php
use Syriable\Casework\Facades\Casework;

$appeal = Casework::appeal($restriction)              // a Decision or a Restriction
    ->by($user)                                       // or ->bySystem()
    ->statement('I believe this was a mistake.')      // optional, opaque
    ->submit();
```

Two guards run before anything is written (invariant I-11 — a refused
appeal never creates a record):

- **Window** (`casework.appeals.window_days`, default 30): submission
  is allowed through the exact end of the window, measured from the
  appealed record's creation, and refused strictly after it with
  `AppealWindowClosed`. Set to `null` to disable.
- **Limit** (`casework.appeals.limit_per_target`, default 1): once a
  target carries that many appeals — regardless of their outcome —
  further submissions throw `AppealLimitReached`.

## Assignment and review

```php
Casework::assignAppeal($appeal, to: $reviewer, by: $lead);  // throws ReviewerNotIndependent
Casework::startAppealReview($appeal, by: $reviewer);
```

When `casework.appeals.require_independent_reviewer` is `true` (the
default), the reviewer must differ from the actor who made the
appealed decision or issued the appealed restriction — otherwise
`ReviewerNotIndependent` is thrown. The guard runs at assignment
*and* at `startAppealReview`, so a config flip between the two cannot
smuggle a dependent reviewer in.

Independently of that toggle, the appellant never reviews their own
appeal while `casework.authorization.prevent_self_moderation` is on.

`startAppealReview` records the acting model actor as the reviewer
when no assignment happened beforehand; a pre-assigned reviewer is
never overwritten.

## Resolving

```php
Casework::resolveAppeal($appeal)
    ->by($reviewer)                                          // or ->bySystem()
    ->overturn(rationale: 'Original evidence insufficient'); // or ->uphold(...) / ->reject(...)
```

The three verbs are the builder's terminal calls:

- **`uphold`** — the original decision/restriction stands.
- **`reject`** — the appeal itself is declined. Also allowed straight
  from `submitted` for administrative rejection; the reason lands in
  the audit payload.
- **`overturn`** — the original outcome is reversed, atomically
  (invariant I-13, FR-504). In one transaction the package:
  1. lifts every still-active restriction the appealed target carries —
     the appealed restriction itself, or all active restrictions the
     appealed decision applied — through the restriction machine's own
     `lift` (each one audits and dispatches `RestrictionLifted` like
     any other lift);
  2. records a **superseding decision** with outcome `dismiss`
     referencing the original via `supersedes` — when there is an
     original decision to supersede. A direct restriction with no
     decision is simply lifted; and
  3. stores that decision on the appeal as `resultingDecision`.

Because lifts run through the regular action, a model actor resolving
an overturn also needs the restriction `lift` ability; grant it in
your policy alongside appeal `resolve`, or resolve as the System
actor.

## Events and audit

| Operation | Event | Audit key |
|---|---|---|
| submit | `AppealSubmitted` | `appeal.submitted` |
| assign | `AppealAssigned` | `appeal.assigned` |
| startReview | `AppealReviewStarted` | `appeal.review_started` |
| uphold | `AppealUpheld` | `appeal.upheld` |
| overturn | `AppealOverturned` | `appeal.overturned` |
| reject | `AppealRejected` | `appeal.rejected` |

`AppealOverturned` carries the superseding decision (nullable) and the
collection of lifted restrictions, and dispatches after its effects —
each `RestrictionLifted` — per occurrence order (ADR-0015). All events
dispatch after commit.

## Authorization

`AppealPolicy` denies every ability (`submit`, `assign`, `review`,
`resolve`) to model actors by default — register your own
policy for the Appeal model to grant them. Applications typically
grant `submit` to the affected actor and the review abilities to
moderation staff. System attribution (`bySystem()` /
`ActorRef::system()`) bypasses policies.

## Querying

```php
use Syriable\Casework\Appeals\Models\Appeal;

Appeal::query()->submitted()->forTarget($restriction)->byAppellant($user)->get();
```
