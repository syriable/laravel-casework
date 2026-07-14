# Laravel Trust & Safety — Public API Specification

**Phase:** 5 — Public API
**Produced by:** Public API Design team (T6)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G5)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Architecture](../architecture/overview.md) + ADRs 0004–0007 (Gate G4 approved 2026-07-14)
**ADRs introduced:** [0008](../adr/0008-case-entity-naming.md), [0009](../adr/0009-pending-operation-builders.md)

This specification is the complete developer-facing API for v1.0. Everything shown here is
public and BC-governed after release (NFR-08); anything not shown is internal. Signatures
are normative; parameter names are part of the API (named arguments). All operations
delegate to action classes (ADR-0005) — the facade and traits are sugar, never a second
implementation.

**API shape rule (ADR-0009):** operations with multiple optional aspects use a fluent
*pending-operation builder* ending in an explicit verb; operations with ≤2 required
aspects are single facade methods with named arguments.

---

## 1. Making Models Participate

```php
use Syriable\Casework\Contracts\Reportable;
use Syriable\Casework\Contracts\Restrictable;
use Syriable\Casework\Concerns\InteractsWithReports;
use Syriable\Casework\Concerns\InteractsWithRestrictions;

class Post extends Model implements Reportable
{
    use InteractsWithReports;
}

class User extends Model implements Reportable, Restrictable
{
    use InteractsWithReports;
    use InteractsWithRestrictions;
}
```

### `InteractsWithReports` provides

```php
public function reports(): MorphMany;              // all reports about this model
public function openReports(): MorphMany;          // pending / under_review / attached_to_case
public function hasOpenReports(): bool;
public function cases(): MorphMany;                // cases where this model is primary subject
```

### `InteractsWithRestrictions` provides

```php
public function restrictions(): MorphMany;         // full history
public function activeRestrictions(): MorphMany;   // active AND not past expires_at (I-09)
public function isRestricted(?string $type = null, ?string $scope = null): bool;
public function isSuspended(): bool;               // isRestricted(RestrictionType::SUSPENSION)
public function warnings(): MorphMany;
public function activeWarnings(): MorphMany;
```

`isRestricted()` is the hot path (FR-405/NFR-04): one indexed query, honoring `expires_at`
in real time regardless of scheduler cadence.

## 2. Reporting

```php
use Syriable\Casework\Facades\Casework;

$report = Casework::report($post)          // Reportable subject
    ->by($user)                            // or ->anonymously() or ->bySystem()
    ->because('spam')                      // reason key or Reason model — required
    ->comment('Links to a phishing site')  // optional, stored opaquely
    ->withMetadata(['url' => $url])        // optional (FR-107)
    ->file();                              // Report — throws DuplicateReport, UnknownReason
```

Reason management (`Reason` is a normal Eloquent model — FR-151–155):

```php
Reason::create(['key' => 'phishing', 'label' => 'Phishing', 'category' => 'fraud']);
$reason->deactivate();      // historical reports keep referencing it (I-14)
Reason::active()->get();
```

Report queries (FR-108):

```php
Report::query()
    ->whereState(ReportState::Pending)     // ->pending() shorthand scope per state
    ->forSubject($post)
    ->byReporter($user)                    // or ->fromSystem() / ->anonymous()
    ->withReason('spam')
    ->get();
```

Resolving outside a case (FR-104): `Casework::dismissReport($report, by: $moderator);`

## 3. Cases

Model class is **`CaseFile`** (`case` is a PHP reserved word — ADR-0008); the domain term
remains "case" everywhere in language and docs.

```php
$case = Casework::openCase($post)                 // primary subject, fixed at open (I-05)
    ->by($moderator)                              // or ->bySystem() (automation)
    ->withReports($reports)                       // optional; attaches + transitions them
    ->priority(Priority::HIGH)                    // optional, default from config
    ->open();                                     // CaseFile

Casework::attachReport($report, to: $case, by: $moderator);
Casework::assignCase($case, to: $moderator, by: $lead);        // FR-203
Casework::startInvestigation($case, by: $moderator);           // open → under_investigation
Casework::escalateCase($case, by: $moderator, priority: Priority::URGENT);
Casework::closeCase($case, by: $moderator);                    // decided → closed
```

Automatic case creation follows the configured strategy (FR-205): `always`,
`threshold` (N open reports on one subject), or `manual` — evaluated on `file()`.

Investigation records (immutable — I-07):

```php
Casework::note($case, by: $moderator, body: 'Subject has two prior cases.');
Casework::attachEvidence($case, by: $moderator, subject: $otherPost, data: ['note' => '…']);

$case->notes;       // HasMany, chronological
$case->evidence;    // HasMany
$case->reports;     // HasMany
```

Case queries:

```php
CaseFile::query()->open()->assignedTo($moderator)->wherePriority(Priority::HIGH)->get();
CaseFile::query()->forSubject($user)->decided()->get();
```

## 4. Decisions

```php
$decision = Casework::decide($case)
    ->by($moderator)
    ->outcome(Outcome::UPHOLD)                    // UPHOLD / DISMISS / ESCALATE / custom string
    ->rationale('Repeated spam after warning.')   // optional
    ->withSuspension(days: 30)                    // sugar for withRestriction(...)
    ->withRestriction('posting', permanent: true, scope: 'listings')
    ->withWarning('Final notice.')
    ->finalize();                                 // Decision — atomic (I-06, I-08):
                                                  // transitions case, resolves reports,
                                                  // applies enforcement, audits, events
```

Decisions are immutable (I-07). Amending = new decision: 
`Casework::decide($case)->…->supersedes($decision)->finalize();`

## 5. Enforcement

Direct (decision-less) enforcement — same builders decisions use internally:

```php
$restriction = Casework::restrict($user, 'posting')   // Restrictable subject + type
    ->by($moderator)
    ->for(days: 7)                                    // or ->until($date) or ->permanently()
    ->inScope('listings')                             // optional (FR-402)
    ->because('Spam wave')                            // optional rationale
    ->apply();                                        // Restriction

$suspension = Casework::suspend($user)->by($moderator)->for(days: 30)->apply();
$warning    = Casework::warn($user)->by($moderator)->because('First offence.')->issue();

Casework::lift($restriction, by: $moderator, reason: 'Appeal upheld');   // FR-408
```

Runtime checks (FR-405) — via trait (§1) or facade for non-trait contexts:

```php
Casework::isRestricted($user, type: 'posting', scope: 'listings');   // bool, O(1)
```

Restriction queries:

```php
Restriction::query()->active()->ofType('suspension')->forSubject($user)->get();
Restriction::query()->expiringBefore(now()->addDay())->get();
Warning::query()->activeFor($user)->count();
```

Operational command (FR-404/953): `php artisan casework:expire-restrictions`
(schedulable by the app; real-time checks never depend on it).

## 6. Appeals

```php
$appeal = Casework::appeal($restriction)              // Decision or Restriction (FR-501)
    ->by($user)
    ->statement('I believe this was a mistake.')      // optional, opaque
    ->submit();   // throws AppealWindowClosed, AppealLimitReached (I-11)

Casework::assignAppeal($appeal, to: $reviewer, by: $lead);  // throws ReviewerNotIndependent (I-12)
Casework::startAppealReview($appeal, by: $reviewer);

Casework::resolveAppeal($appeal)
    ->by($reviewer)
    ->overturn(rationale: 'Original evidence insufficient')  // or ->uphold(...) / ->reject(...)
    ;   // overturn: lifts restrictions + records superseding decision, atomically (I-13)
```

```php
Appeal::query()->submitted()->forTarget($restriction)->byAppellant($user)->get();
```

## 7. Audit History

```php
AuditEntry::query()
    ->forAuditable($case)          // everything that happened to this case
    ->byActor($moderator)          // everything this moderator did
    ->action('case.decided')       // dot-namespaced action keys (catalog in Phase 8)
    ->between($from, $to)
    ->get();

$entry->actor;       // ?Model (null when origin is system/anonymous)
$entry->origin;      // Origin::Model / Origin::System / Origin::Anonymous
$entry->payload;     // array (AuditPayload)
```

Append-only: no update/delete API exists; attempts throw `ImmutableRecord` (ADR-0003).
Opt-in pruning (FR-705): `php artisan casework:prune-audit --before=2025-01-01` (explicit).

## 8. Authorization Surface (FR-600)

Package policies cover every operation; applications override by registering their own
policy for the package models. Scoped moderation resolves through a contract:

```php
interface ScopeResolver
{
    /** Scopes within which the actor may moderate; null = unscoped/all. */
    public function scopesFor(Model $actor): ?array;

    /** The scope a given subject belongs to; null = unscoped. */
    public function scopeOf(Model $subject): ?string;
}
```

Default: everything unscoped; every action authorizes via `Gate` before executing.
Self-moderation guard (FR-604) enforced in policies, configurable.

## 9. Events (surface only — full catalog is Phase 8)

Naming: past-tense facts per glossary rule 3 — `ReportFiled`, `ReportDismissed`,
`CaseOpened`, `CaseAssigned`, `CaseDecided`, `RestrictionApplied`, `RestrictionLifted`,
`RestrictionExpired`, `WarningIssued`, `AppealSubmitted`, `AppealOverturned`, ….
Every transition event exposes `$from`, `$to`, and actor attribution (FR-802).

## 10. Exceptions per Operation

| Operation | Throws |
|---|---|
| `->file()` | `DuplicateReport`, `UnknownReason`, `AuthorizationException` |
| `openCase/attach/assign/…` | `InvalidTransition`, `AuthorizationException` |
| `->finalize()` | `InvalidTransition` (case not decidable), `AuthorizationException` |
| `->apply()/issue()` | `AuthorizationException`, `InvalidTransition` |
| `lift()` | `InvalidTransition` (not active), `AuthorizationException` |
| `->submit()` | `AppealWindowClosed`, `AppealLimitReached`, `AuthorizationException` |
| `assignAppeal()` | `ReviewerNotIndependent`, `AuthorizationException` |
| any mutation of immutable records | `ImmutableRecord` |

All implement `CaseworkException` (ADR-0006).

## 11. Coverage Check (review criterion, roadmap §2)

Reporting ✓ (§2) · Reasons ✓ (§2) · Cases ✓ (§3) · Workflows/states ✓ (§3–6, machines in
Phase 7) · Investigation ✓ (§3) · Decisions ✓ (§4) · Restrictions temp/permanent ✓ (§5) ·
Warnings ✓ (§5) · Suspensions ✓ (§5) · Appeals ✓ (§6) · Scoped permissions ✓ (§8) ·
Audit ✓ (§7) · Events ✓ (§9) · Notification/automation hooks → contracts in Phase 8–9 ·
Policies/contracts ✓ (§8, Phase 9) · Extension points → Phase 9 (models/reasons/outcomes/
types already extensible above).

## 12. Definition of Done — Phase 5

- [x] Every §2 roadmap capability has signatures + usage example
- [x] Naming consistent with glossary; builders vs methods rule fixed (ADR-0009)
- [x] Case class name decided (ADR-0008)
- [x] Exceptions documented per operation; nothing exposed beyond need
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G5**

**Next phase upon approval:** Phase 6 — Database Design (schema, identifiers ADR per
NFR-12, morph/index strategy, migration plan).
