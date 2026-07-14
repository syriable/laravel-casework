# Laravel Trust & Safety — Testing Strategy

**Phase:** 11 — Testing Strategy
**Produced by:** Testing Strategy team (T12)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G11)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Configuration](configuration.md) (Gate G10 approved 2026-07-14); all design docs G0–G10

Tooling is already in the skeleton: **Pest 4** (+ Laravel & type-coverage plugins),
**Orchestra Testbench 10/11**, **PHPStan/larastan**, **Pint**, **Rector**. This strategy
assigns what gets tested where, with what targets, on what matrix.

---

## 1. Compatibility Target Resolution

`composer.json` currently claims Laravel `^11||^12||^13` but ships Testbench `^10||^11`
(Laravel 12/13 only), and CI tests Laravel 12/13 only. **Resolution: v1.0 supports
Laravel 12 and 13**; the composer constraint is corrected to `^12.0||^13.0` in Phase 14's
first milestone. NFR-02 ("versions declared in composer.json with a full CI matrix") is
then satisfied exactly. Laravel 11 support is declined, not deferred (it leaves LTS-less
support windows before our v1.0 matures).

## 2. Test Levels

| Level | Scope | DB | Location |
|---|---|---|---|
| **Unit** | Guards (each in isolation), VOs, workflow definitions (structure), config boot-validation, builders (inertness, validation) | none | `tests/Unit` |
| **Feature** | Actions end-to-end through the facade/traits: full authorize→guard→transact→transition→audit→event pipeline | SQLite in-memory | `tests/Feature` |
| **Integration** | Same feature suite re-run against real engines; migration up/down; index existence | MySQL 8, PostgreSQL 14, MariaDB 10.11 (CI services) | same tests, DB matrix |
| **Architecture** | Pest arch tests: ADR-0017 final/open policy, module dependency direction (ADR-0004), models never dispatch events (layering §2) | none | `tests/Arch` |

Workbench (`workbench/app`) hosts test-only `Reportable`/`Restrictable` models (a `Post`
and a `User` variant per key type — bigint and ULID — validating ADR-0010's universal
morph columns).

## 3. Mandatory Coverage Maps (review criterion: every requirement maps to tests)

### 3.1 State machines — exhaustive (NFR-07)

Tests are **generated from the `WorkflowDefinition`s** (ADR-0012): for each lifecycle,
every allowed transition asserts success + event + audit entry + actor attribution, and
every (state, transition) pair *not* in the definition asserts `InvalidTransition`.
Counts (from Phase 7): Report 5 transitions / 25 pairs; Case 5 / 30 incl. creation;
Restriction 4 / 16; Appeal 5 / 25. Direct `state` assignment throws — one test per model.

### 3.2 Invariants I-01 … I-15

One named feature test (minimum) per invariant, e.g. `I-02` duplicate report rejected +
allowed when configured; `I-08`/`I-13` atomicity via induced mid-transaction failure
asserting full rollback (no decision, no restrictions, no audit, no events per ADR-0015);
`I-09` real-time expiry with a past-`expires_at` active row; `I-12` independence toggle.

### 3.3 Requirements traceability

Every FR/NFR gets `@see FR-xxx` annotations in its covering tests; a CI grep script
fails if any frozen FR number appears in zero tests (cheap, mechanical traceability).

### 3.4 Extension surface (ADR-0013/0016, Phase 9 §3)

Valid custom state/transition registration incl. full pipeline on custom transitions;
each ADR-0013 rule violation → boot exception; model override map (valid subclass works
end-to-end incl. relations; non-subclass → boot exception); action rebinding; pipeline
stage ordering + short-circuit; notifier ordering; every config key's invalid-value boot
failure.

### 3.5 Events (ADR-0014/0015)

`Event::fake()` per catalog event at its dispatch point; after-commit semantics: an app
transaction wrapping `decide()` that rolls back dispatches nothing; occurrence ordering
(restrictions before `CaseDecided`).

### 3.6 Performance guards (NFR-04/05)

Query-count assertions: `isRestricted()` ≤ 1 query; report/case list scopes with eager
loads ≤ fixed counts (no N+1); `EXPLAIN`-based index-use smoke test on the hot path,
integration matrix only.

## 4. Quality Gates (all CI-enforced, per commit)

| Gate | Target |
|---|---|
| PHPStan (larastan) | level **9**, zero baseline growth (`phpstan-baseline.neon` stays empty) |
| Type coverage (Pest plugin) | **≥ 95%** (NFR-06) |
| Line coverage | **≥ 90%** overall; **100%** for guards, workflow definitions, and actions |
| Pint | clean (existing workflow) |
| Rector | dry-run clean (config in repo) |
| Arch tests | ADR-0017/0004 rules pass |

## 5. CI Matrix Plan

Extends the existing `run-tests.yml` (kept) plus one new `integration-tests.yml`:

- **run-tests.yml** (unchanged shape): PHP 8.4 & 8.5 × Laravel 12 & 13 × prefer-lowest &
  prefer-stable × ubuntu + windows — SQLite, fast suite. 16 jobs.
- **integration-tests.yml** (new): PHP 8.4 × Laravel 13 prefer-stable × {MySQL 8,
  PostgreSQL 14, MariaDB 10.11} as service containers, running the feature suite with
  `DB_CONNECTION` switched + §3.6 index smoke tests. 3 jobs, ubuntu only.
- **phpstan.yml** (existing): add type-coverage and arch-test steps.
- Coverage report job (PHP 8.4/L13/SQLite) enforcing §4 thresholds via
  `pest --coverage --min=90` and `--type-coverage --min=95`.

## 6. Fixtures & Factories

Factory per package model (`database/factories`, already autoloaded), each with states
mirroring lifecycle states (`Report::factory()->attachedToCase()`,
`Restriction::factory()->expired()`), built strictly through actions where invariants
apply — factories may not write states unreachable through transitions (keeps fixtures
honest; enforced by making factories call actions for stateful setup).

## 7. Definition of Done — Phase 11

- [x] Every requirement, invariant, transition, extension point, and event mapped to a test class of a named level
- [x] Targets numeric and CI-enforceable; matrix covers §1's supported versions and NFR-03 databases
- [x] Compatibility discrepancy resolved (Laravel 12/13)
- [x] Generated-from-definition transition testing keeps NFR-07 exhaustive by construction
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G11**

**Next phase upon approval:** Phase 12 — Documentation Plan (README structure, docs tree,
newcomer path, upgrade guide format).
