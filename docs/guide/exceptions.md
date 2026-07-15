# Exceptions

Every package exception implements the `CaseworkException` marker
(ADR-0006), so one catch covers the domain:

```php
use Syriable\Casework\Exceptions\CaseworkException;

try {
    Casework::report($post)->by($user)->because('spam')->file();
} catch (CaseworkException $exception) {
    // domain refusal — duplicate, unknown reason, closed window, …
} catch (AuthorizationException $exception) {
    // policy/scope denial (standard Laravel exception)
}
```

## Per operation

| Operation | Throws |
|---|---|
| `report(...)->file()` | `DuplicateReport`, `UnknownReason`, `AuthorizationException` |
| `openCase` / `attachReport` / `assignCase` / … | `InvalidTransition`, `AuthorizationException` |
| `decide(...)->finalize()` | `InvalidTransition`, `InvalidConfiguration` (unknown outcome), `AuthorizationException` |
| `restrict/suspend(...)->apply()`, `warn(...)->issue()` | `InvalidConfiguration` (unknown type), `AuthorizationException` |
| `lift()` | `InvalidTransition` (not currently active, I-10), `AuthorizationException` |
| `appeal(...)->submit()` | `AppealWindowClosed`, `AppealLimitReached`, `AuthorizationException` |
| `assignAppeal()` / `startAppealReview()` | `ReviewerNotIndependent`, `InvalidTransition`, `AuthorizationException` |
| `resolveAppeal(...)->uphold()/overturn()/reject()` | `InvalidTransition`, `AuthorizationException` |
| any builder terminal missing a required aspect | `IncompleteBuilder` |
| any mutation of immutable records | `ImmutableRecord` |
| invalid config at boot | `InvalidConfiguration` |
| invalid workflow extension at boot | `InvalidWorkflow` |

## The classes

| Exception | Raised when |
|---|---|
| `DuplicateReport` | same reporter, subject, and reason while a report is open (I-02) |
| `UnknownReason` | reason key missing or inactive (FR-151) |
| `InvalidTransition` | transition not allowed from the record's state (I-03); carries `$record`, `$transition`, `$fromState` |
| `IncompleteBuilder` | a pending-operation builder's terminal verb called before required aspects (ADR-0009) |
| `AppealWindowClosed` | submission after `appeals.window_days` elapsed (FR-506) |
| `AppealLimitReached` | target already carries `appeals.limit_per_target` appeals (FR-503) |
| `ReviewerNotIndependent` | reviewer is the original decider/issuer while independence is required (I-12) |
| `ImmutableRecord` | update/delete attempted on decisions or audit entries (ADR-0003) |
| `InvalidConfiguration` | boot validation failure, unknown outcome/restriction type at runtime; carries `$key` |
| `InvalidWorkflow` | workflow extension violating the add-only rules (ADR-0013) |

Guard-level refusals never leave partial records: an exception from
`file()` or `submit()` means nothing was persisted.
