# Laravel Trust & Safety — Configuration Specification

**Phase:** 10 — Configuration
**Produced by:** Laravel & Package Architecture team (T5)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G10)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Extension System](extending.md) (Gate G9 approved 2026-07-14)

One published file: `config/casework.php`. **Zero-config guarantee (FR-951):** every key
below has a working default; `composer require` → publish migrations → migrate → the full
report-to-appeal flow works. All class-name and enum-like values are **boot-validated**
(ADR-0016): unknown classes, non-implementing classes, invalid strategy names, and
malformed values throw a descriptive exception at boot, never at runtime.

## The Boundary Rule (review criterion)

- **Config** = installation-time posture: table prefix, model map, policy toggles,
  strategy selection, class lists. Changes require a deploy.
- **Database** = runtime-editable domain data: reasons (rows), and everything moderators
  produce. Changes happen through the API at runtime.
- **Code** = behavior: contracts, actions, guards, workflow definitions (container).

No behavior is reachable *only* through obscure config: every key corresponds to a
documented feature (FR trace below), and nothing here switches hidden code paths.

## Annotated Specification

```php
return [

    /* Table prefix for all ten package tables. Applied at migration
     * publish and at runtime model resolution. (FR-952, FR-954)     */
    'table_prefix' => 'casework_',

    /* Model overrides (X1, FR-901). Values must subclass the shipped
     * model — boot-validated. All package relations resolve through
     * this map.                                                      */
    'models' => [
        'report'      => \Syriable\Casework\Reporting\Models\Report::class,
        'reason'      => \Syriable\Casework\Reporting\Models\Reason::class,
        'case'        => \Syriable\Casework\Cases\Models\CaseFile::class,
        'note'        => \Syriable\Casework\Cases\Models\Note::class,
        'evidence'    => \Syriable\Casework\Cases\Models\Evidence::class,
        'decision'    => \Syriable\Casework\Cases\Models\Decision::class,
        'restriction' => \Syriable\Casework\Enforcement\Models\Restriction::class,
        'warning'     => \Syriable\Casework\Enforcement\Models\Warning::class,
        'appeal'      => \Syriable\Casework\Appeals\Models\Appeal::class,
        'audit_entry' => \Syriable\Casework\Audit\Models\AuditEntry::class,
    ],

    'reporting' => [
        /* Reject same reporter + subject + reason while open. (FR-105, I-02) */
        'allow_duplicates' => false,

        /* Permit origin=anonymous at intake. (FR-103; ADR-0002 — reports only) */
        'allow_anonymous' => true,
    ],

    'cases' => [
        /* When reports become/join cases (FR-205, X7):
         * 'always' | 'threshold' | 'manual' | fully-qualified CaseStrategy class */
        'strategy'  => 'threshold',
        'threshold' => 3,                    // open reports per subject (strategy=threshold)

        /* Ordered priority set + default. (FR-204) */
        'priorities'       => ['low', 'normal', 'high', 'urgent'],
        'default_priority' => 'normal',
    ],

    'decisions' => [
        /* Additional outcome keys beyond dismiss/uphold/escalate. (FR-302, X3) */
        'outcomes' => [],
    ],

    'enforcement' => [
        /* Additional restriction types beyond 'suspension'. (FR-402/407, X4) */
        'restriction_types' => [],
    ],

    'appeals' => [
        /* Appeals per decision/restriction. (FR-503; guard AppealLimitReached) */
        'limit_per_target' => 1,

        /* Days after decision/restriction creation during which an appeal
         * may be submitted; null = no window. (FR-506; AppealWindowClosed) */
        'window_days' => 30,

        /* Reviewer must differ from original decider/issuer. (FR-505, I-12) */
        'require_independent_reviewer' => true,
    ],

    'authorization' => [
        /* Actors cannot decide cases / review appeals about themselves.
         * (FR-604; enforced in default policies)                       */
        'prevent_self_moderation' => true,
    ],

    /* Notifier classes, invoked in order for every catalog event,
     * after commit. Must implement Contracts\Notifier. (FR-803, X8)  */
    'notifiers' => [],

    'pipelines' => [
        /* ReportIntakeStage classes, in order. (FR-804, X9)  */
        'intake' => [],
        /* CaseTriageStage classes, in order. (FR-804, X10)   */
        'triage' => [],
    ],

    'audit' => [
        /* Retention for casework:prune-audit; null = the command refuses
         * to run (pruning is opt-in, FR-705). Real-time behavior never
         * reads this.                                                  */
        'prune_after_days' => null,
    ],
];
```

## Key Inventory & Justification

| Key | Default | Trace | Why config (not code/DB) |
|---|---|---|---|
| `table_prefix` | `casework_` | FR-952/954 | installation posture |
| `models.*` (10) | shipped classes | FR-901/X1 | class map, deploy-time |
| `reporting.allow_duplicates` | `false` | FR-105 | policy toggle |
| `reporting.allow_anonymous` | `true` | FR-103 | policy toggle |
| `cases.strategy` / `threshold` | `threshold` / `3` | FR-205/X7 | strategy selection + parameter |
| `cases.priorities` / `default_priority` | 4 levels / `normal` | FR-204 | ordered set is installation vocabulary |
| `decisions.outcomes` | `[]` | FR-302/X3 | open-set extension (shipped constants always valid) |
| `enforcement.restriction_types` | `[]` | FR-402/X4 | open-set extension |
| `appeals.limit_per_target` | `1` | FR-503 | policy parameter |
| `appeals.window_days` | `30` | FR-506 | policy parameter (`null` = off) |
| `appeals.require_independent_reviewer` | `true` | FR-505/I-12 | policy toggle |
| `authorization.prevent_self_moderation` | `true` | FR-604 | policy toggle |
| `notifiers` | `[]` | FR-803/X8 | class list |
| `pipelines.intake` / `.triage` | `[]` | FR-804/X9-X10 | class lists |
| `audit.prune_after_days` | `null` | FR-705 | opt-in retention |

**Deliberately absent:** reasons (database rows, runtime data — FR-151); workflow
customization (container/`WorkflowDefinition`, ADR-0013 — too structural for config);
queue/notification channels (app's domain, W-03); per-scope settings (belongs to the
app's `ScopeResolver`); identifier types (published-migration edit, ADR-0010). Defaults
are safe-side: duplicates rejected, independence and self-moderation guards on, pruning
off.

## Definition of Done — Phase 10

- [x] Every key: default, FR trace, and config-vs-code-vs-DB justification
- [x] Zero-config guarantee holds (all defaults work standalone)
- [x] Boot validation posture stated for every key kind
- [x] Deliberately-absent list prevents future config sprawl
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G10**

**Next phase upon approval:** Phase 11 — Testing Strategy (test plan, coverage targets,
CI matrix, Testbench setup, per-invariant and per-transition test mapping).
