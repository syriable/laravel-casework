# Phase 16 — Stabilization Exit Report

**Phase:** 16 — Stabilization
**Reviewer:** Fable (Project Director)
**Approver:** Project owner (Gate G16)
**Status:** DRAFT — awaiting approval
**Date:** 2026-07-15
**Upstream:** Phase 15 approved (G15, PR #27 merged)

Stabilization freezes the public API, validates the upgrade path, and
verifies the documentation against real behavior. No defects surfaced
requiring code change; the phase adds the freeze manifest and its
mechanical guard.

## 1. Public API freeze

The complete NFR-08 surface is enumerated and frozen in
[`docs/api/frozen-api-1.0.md`](../api/frozen-api-1.0.md): 10 contracts,
22 facade operations, 26 events, 11 exceptions, 2 traits, the value
objects/helpers, all config keys, and both artisan commands.

**Mechanical enforcement.** `tests/Feature/ApiSurfaceTest.php` asserts
the live surface equals the manifest — contracts, facade methods,
exceptions, traits, and events. Any addition, removal, or rename fails
CI, so public-API drift cannot land without a deliberate manifest edit
(and therefore an ADR). This satisfies the review criterion "no API
changes without ADR" as an executable gate rather than a convention.

Deliberately excluded from the freeze (ADR-0017 §BC scope):
concrete action classes and constructor signatures, builder internals,
the workflow engine internals, and model non-relation internals —
replaceable but not stable; decorating subclasses call the parent.

## 2. Upgrade-path validation

- **Format.** `UPGRADE.md` fixes the per-major entry format (What /
  Why+ADR / Before / After / Effort) and the stability boundary. v1 is
  the initial release — nothing to upgrade from — which the file
  states.
- **Fresh install.** Boot → config validation → migrate → the full
  report-to-appeal flow is exercised by `ServiceProviderTest`,
  `MigrationsTest`, and `QuickstartWalkthroughTest` (the README flow
  verbatim). All green.
- **Config parity.** The eleven top-level config keys in
  `config/casework.php` match the frozen manifest exactly; every key
  is boot-validated (`ConfigurationValidatorTest`).

## 3. Docs verified against code

- Doc-completeness script: every public class and facade method is
  named in a guide or the README — green.
- FR-traceability script: all 65 MUST/SHOULD requirements anchored —
  green.
- README/quickstart executes verbatim against the workbench app.
- CHANGELOG carries the 1.0.0 entry including the Phase 15 R-01/R-02
  fixes.

## 4. CI matrix

Unit/feature (16-job matrix), integration (MySQL 8 / PostgreSQL 14 /
MariaDB 10.11), PHPStan level 9, Pint, and the documentation checks
are green. The coverage gate's type-coverage step was made
deterministic in Phase 15 (Pokio synchronous) and passes.

## 5. Verification snapshot

- Pest: **191 tests, 784 assertions** — all passing (5 new freeze
  tests)
- PHPStan level 9: **0 errors** · Pint: clean · Type coverage:
  **99.8 %**
- Doc-completeness + FR-traceability: green

## 6. Definition of Done — Phase 16

- [x] Public API frozen and documented (frozen-api-1.0.md)
- [x] Freeze enforced mechanically (ApiSurfaceTest) — no API change
      without an ADR
- [x] Upgrade path validated (format + fresh-install smoke)
- [x] Docs verified against code (scripts green, examples execute)
- [x] CI matrix fully green
- [ ] Project owner approval — **Gate G16**

**Next phase upon approval:** Phase 17 — Release Preparation (tag,
packagist metadata, final release checklist).
