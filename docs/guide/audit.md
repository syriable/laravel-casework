# Audit

Every domain action writes exactly one audit entry — who did what to
which record, when, with a scalar payload (FR-700). There is no
unaudited operation and no extension point that can suppress or forge
entries (I-04): the `Recorder` is not swappable.

## Querying

```php
use Syriable\Casework\Audit\Models\AuditEntry;

AuditEntry::query()
    ->forAuditable($case)          // everything that happened to this case
    ->byActor($moderator)          // everything this moderator did
    ->action('case.decided')       // dot-namespaced action keys
    ->between($from, $to)
    ->get();

$entry->actor;       // ?Model — null when origin is system/anonymous
$entry->origin;      // Origin::Model / Origin::System / Origin::Anonymous
$entry->payload;     // array of scalars/ids
```

Action keys mirror the event catalog: `report.filed`, `case.decided`,
`restriction.applied`, `appeal.overturned`, … — the mapping between
events and audit keys is total in both directions
([catalog](../events/catalog.md)).

## Append-only

Audit entries (and decisions) expose no update or delete API — any
attempt throws `ImmutableRecord` (ADR-0003). Reversals are new records
that reference the original: a superseding decision, a lifted
restriction.

**The guarantee is model-layer, not SQL-layer.** `PreventsMutation`
guards every Eloquent path, but raw SQL and DB consoles can still
write to the tables — the package cannot revoke your database
credentials. Treat DB access as the actual trust boundary, and audit
integrity as protection against *application* bugs and extension
code, not against a hostile DBA.

## Payloads and PII

Audit payloads mirror event payloads as scalars and ids. Opaque texts
recorded alongside operations — report comments, rationales, appeal
statements — may contain end-user personal data (NFR-09/10). If you
export audit rows into external systems, your application owns that
disclosure.

## Pruning (opt-in)

Retention is a business decision, so pruning never runs implicitly
(FR-705):

```bash
php artisan casework:prune-audit --before=2025-01-01
```

or configure a retention and schedule it:

```php
// config/casework.php
'audit' => ['prune_after_days' => 730],
```

```php
// routes/console.php (Laravel 11+) or app/Console/Kernel.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('casework:prune-audit')
    ->daily()
    ->withoutOverlapping();
```

Without either, the command refuses to run. Pruning uses a bulk query
delete — the single documented exception to immutability.
