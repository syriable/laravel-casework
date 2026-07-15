# Phase 17 — Release Preparation

**Phase:** 17 — Release Preparation
**Reviewer:** Fable (Project Director)
**Approver:** Project owner (Gate G17)
**Status:** DRAFT — awaiting approval
**Date:** 2026-07-15
**Upstream:** Phase 16 approved (G16, PR #28 merged)

Final phase before `v1.0.0`. Semver decision, release metadata,
policies, and the tag checklist.

## 1. Semver decision

**v1.0.0** — the first stable release. The public API is frozen
(`docs/api/frozen-api-1.0.md`) and enforced (`ApiSurfaceTest`); the
support policy (`docs/support-policy.md`) fixes what minor/major mean
from here.

## 2. Release metadata (verified)

- `composer.json`: name, description, keywords, MIT license, author,
  `require` (`php ^8.4`, `illuminate/contracts ^12||^13`,
  `spatie/laravel-package-tools ^1.16`), PSR-4 autoload, and the
  Laravel auto-discovery block (provider + `Casework` alias) — all
  correct.
- `LICENSE.md` — MIT, correct holder.
- `CONTRIBUTING.md` — contribution + ADR process, freeze rules, local
  gate. (Added this phase; README link now resolves.)
- `SECURITY.md` — private reporting via GitHub advisories / email, and
  the documented-boundary scope notes. (Added this phase; the README
  security-policy link now resolves.)
- `docs/support-policy.md` — semver, the public-API definition, and
  Laravel/PHP support window. (Added this phase.)
- `CHANGELOG.md` — dated `1.0.0` entry.

## 3. README badges & links (verified)

- Badges point at `syriable/laravel-casework` Packagist and Actions —
  valid once the package is published.
- Internal links resolve: `CHANGELOG.md`, `CONTRIBUTING.md`,
  `LICENSE.md`, the fifteen `docs/guide/*` pages, and the
  `/security/policy` tab (backed by the new `SECURITY.md`).
- The `CaseFile` naming note and the guide index table are present.

## 4. Tagging checklist (execute at release)

1. Confirm `main` is green across the full CI matrix (unit/feature,
   integration DBs, PHPStan, Pint, coverage, docs).
2. Confirm `CHANGELOG.md` `1.0.0` date matches the tag date.
3. Tag and push:
   ```bash
   git tag -a v1.0.0 -m "v1.0.0"
   git push origin v1.0.0
   ```
4. Create the GitHub release from the tag; paste the `1.0.0` CHANGELOG
   section as the release notes.
5. Packagist: submit `https://github.com/syriable/laravel-casework`
   (or confirm the GitHub webhook auto-updates an existing listing).
6. **Post-tag smoke:** in a scratch Laravel app,
   `composer require syriable/laravel-casework`, `php artisan migrate`,
   and run the quickstart snippet — confirm a clean install resolves
   the tagged version and the flow works.

## 5. Release notes (1.0.0)

The first stable release of Laravel Trust & Safety: a complete,
UI-agnostic moderation platform — reporting, cases, decisions,
enforcement, appeals, and an append-only audit trail — as composable,
fully-audited domain operations with no UI. Extensible without forking
across thirteen documented extension points; concurrency-safe
transitions; works with bigint/ULID/UUID keys on Laravel 12+ / PHP
8.4+. Full capability list in `CHANGELOG.md`.

## 6. Definition of Done — Phase 17

- [x] Semver decision recorded (v1.0.0)
- [x] `composer.json` / Packagist metadata verified
- [x] Security policy, support/BC policy, CONTRIBUTING present
- [x] README badges/links valid; license/credits correct
- [x] `CHANGELOG.md` dated; release notes drafted
- [x] Tag + Packagist + post-tag smoke checklist written
- [ ] Project owner approval — **Gate G17 → v1.0.0 ships**

The tag itself is cut by the maintainer after G17 approval (step 4
above); this phase makes the repository release-ready.
