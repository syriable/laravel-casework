# Testing Your Integration

The package is designed to be exercised in plain feature tests — no
test doubles of package internals needed.

## Factories

Every package model ships a factory:

```php
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Reporting\Models\Report;

$case = CaseFile::factory()->about($user)->create();
$restriction = Restriction::factory()->about($user)->expiringAt(now()->addWeek())->create();
$report = Report::factory()->about($post)->create();
```

Factories create records in their *creation* state (`pending`,
`open`, `active`, `submitted`) — move them along lifecycles through
the real operations, not by writing state.

## Granting abilities in tests

Package policies deny moderation by default. In tests, either act as
the System:

```php
Casework::decide($case)->bySystem()->outcome(Outcome::DISMISS)->finalize();
```

or grant everything up front:

```php
Gate::before(fn () => true);
```

or register the policy you ship to production and assert *it* —
usually the most valuable test.

## Faking events

Package events are ordinary Laravel events:

```php
Event::fake([CaseDecided::class]);

Casework::decide($case)->bySystem()->outcome(Outcome::UPHOLD)->finalize();

Event::assertDispatched(CaseDecided::class, fn (CaseDecided $event) => $event->case->is($case));
```

Fake selectively (pass the class list). A global `Event::fake()`
also swallows `CaseOpened`, which disables triage pipelines and
notifiers — usually not what a test intends.

## Asserting enforcement

```php
expect($user->isSuspended())->toBeTrue();

$this->travel(31)->days();

expect($user->isSuspended())->toBeFalse();   // expiry is real-time
```

Time-travel helpers work everywhere: the appeal window, warning
activity, and restriction expiry all derive from `now()`.

## Asserting the audit trail

```php
use Syriable\Casework\Audit\Models\AuditEntry;

$entry = AuditEntry::query()
    ->action('case.decided')
    ->forAuditable($case)
    ->latest('id')
    ->firstOrFail();

expect($entry->origin)->toBe(Origin::Model)
    ->and($entry->actor->is($moderator))->toBeTrue();
```

## Testing your extensions

- **Stages/notifiers**: list them in config inside the test
  (`config()->set('casework.pipelines.intake', [MyStage::class])`) and
  run the real operation — assert effects, audit, and events.
- **Custom workflow transitions**: bind your definition, then assert
  your custom transitions *and* that a shipped transition still works.
- **Policies/ScopeResolver**: file/decide through the facade as a
  model actor and assert both the grant and the denial paths.
- **Reputation policy**: enable tracking in config, file and dismiss
  (or resolve) a report, and assert the reporter's `reputationScore()`
  moved by the amount your policy returns.

The package's own test suite (`tests/Feature`) doubles as a cookbook
for all of the above.
