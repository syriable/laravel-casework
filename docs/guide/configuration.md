# Configuration

One published file: `config/casework.php`. **Every key has a working
default** (FR-951) — you can run the whole flow without publishing
anything. All class-name and enum-like values are boot-validated:
invalid values throw `InvalidConfiguration` naming the offending key
at boot, never at runtime. The design rationale lives in the
[Phase 10 specification](../configuration.md).

```bash
php artisan vendor:publish --tag="casework-config"
```

## Reference

| Key | Default | Meaning |
|---|---|---|
| `table_prefix` | `casework_` | Prefix for all ten tables; set before migrating |
| `models.*` | shipped classes | Model overrides (X1); values must subclass the shipped model |
| `reporting.allow_duplicates` | `false` | Permit the same reporter to re-report the same subject for the same reason while a report is open (I-02) |
| `reporting.allow_anonymous` | `true` | Permit `ActorRef::anonymous()` reports (FR-103) |
| `cases.strategy` | `threshold` | When reports open/join cases: `always`, `threshold`, `manual` (shipped as `AlwaysStrategy`, `ThresholdStrategy`, `ManualStrategy`), or a `CaseStrategy` class (X7) |
| `cases.threshold` | `3` | Open reports per subject that trigger a case under `threshold` |
| `cases.priorities` | `low, normal, high, urgent` | Ordered priority vocabulary (FR-204) |
| `cases.default_priority` | `normal` | Priority for new cases; must be in `priorities` |
| `decisions.outcomes` | `[]` | Extra outcome keys beyond `dismiss`/`uphold`/`escalate` (X3) |
| `enforcement.restriction_types` | `[]` | Extra restriction types beyond `suspension` (X4) |
| `appeals.limit_per_target` | `1` | Appeals permitted per decision/restriction (FR-503) |
| `appeals.window_days` | `30` | Days after the decision/restriction during which an appeal may be submitted; `null` = no window (FR-506) |
| `appeals.require_independent_reviewer` | `true` | Reviewer must differ from the original decider/issuer (I-12) |
| `authorization.prevent_self_moderation` | `true` | Actors cannot decide cases or review appeals concerning themselves (FR-604) |
| `notifiers` | `[]` | `Notifier` classes invoked, in order, for every event after commit (X8) |
| `pipelines.intake` | `[]` | `ReportIntakeStage` classes, in order (X9) |
| `pipelines.triage` | `[]` | `CaseTriageStage` classes, in order (X10) |
| `audit.prune_after_days` | `null` | Retention for `casework:prune-audit`; `null` = command refuses to run |

## The boundary rule

- **Config** is installation-time posture — changing it means a deploy.
- **Database** holds runtime-editable domain data — reasons are rows,
  managed through the API.
- **Code** (container bindings) carries behavior — strategies,
  resolvers, workflow definitions, rebound actions.

Nothing is reachable *only* through obscure config: every key above
maps to a documented feature.

## Validation failures

A misconfigured application fails at boot with the offending key:

```
Casework configuration [pipelines.intake]:
App\Moderation\Triage must implement Syriable\Casework\Contracts\ReportIntakeStage.
```

The full validation matrix (what each key accepts) is exercised in
`tests/Unit/ConfigurationValidatorTest.php`.
