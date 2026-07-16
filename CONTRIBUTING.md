# Contributing

Thanks for considering a contribution to Laravel Trust & Safety
(`laravel-casework`). This package is architecture-first: decisions are
recorded before code, and contributions are reviewed against that
record.

## Ground rules

- **Match the ADRs.** Significant design lives in `docs/adr/`. A change
  that contradicts an accepted ADR needs a *superseding* ADR in the
  same PR — don't work around a decision silently.
- **The public API is frozen** (`docs/api/frozen-api-1.0.md`). Adding,
  removing, or renaming anything in the frozen surface fails
  `ApiSurfaceTest` by design. Additions are minor-version features and
  update the manifest in the same PR; removals/renames are majors and
  need an ADR + an `UPGRADE.md` entry.
- **No UI.** The package is UI-agnostic (NFR-01): no routes, views,
  controllers, or Blade.
- **Everything is audited.** New domain operations go through an action
  with the full pipeline (authorize → guard → transact → transition →
  audit → events) and record exactly one audit entry.

## Workflow

1. Open an issue describing the change before large work, so we can
   agree on the approach (and whether an ADR is needed).
2. Branch from `main`.
3. Make the change **with tests and docs** — every guide example must
   run verbatim; every public symbol must be documented (the doc CI
   scripts enforce this).
4. Run the full gate locally (below) before pushing.
5. Open a PR. It's reviewed against the ADRs and the frozen API.

## Local checks

```bash
composer test                                   # Pest
vendor/bin/phpstan analyse                       # level 9, empty baseline
vendor/bin/pint                                  # code style
POKIO_ASYNC=false vendor/bin/pest --type-coverage --min=95
php scripts/check-doc-completeness.php
```

CI additionally runs the unit/feature matrix and the integration
suite against MySQL, PostgreSQL, and MariaDB.

## Coding standards

- PHP 8.4+, `declare(strict_types=1)`, typed properties and signatures.
- Follow the existing layout (`docs/adr/0004-domain-first-package-layout.md`).
- Pint (Laravel preset) is the formatter; PHPStan level 9 is the floor.

## Reporting security issues

Please **do not** open public issues for vulnerabilities — see
[SECURITY.md](SECURITY.md).
