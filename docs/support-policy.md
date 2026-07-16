# Support & Backward-Compatibility Policy

## Versioning

`laravel-casework` follows [Semantic Versioning](https://semver.org).

- **Patch** (`1.0.x`) — bug fixes, no API change.
- **Minor** (`1.x.0`) — additive features. New contracts, events,
  facade methods, exceptions, or config keys may be **added**; the
  frozen manifest (`docs/api/frozen-api-1.0.md`) is appended to in the
  same release.
- **Major** (`x.0.0`) — anything that removes or changes existing
  public API. Every such change carries a superseding ADR and an
  `UPGRADE.md` section.

## What is public API

The frozen surface in `docs/api/frozen-api-1.0.md`: contracts, facade
operations, event classes, exceptions, the participation traits, value
objects/helpers, config keys, and artisan commands. `ApiSurfaceTest`
enforces it — drift cannot land without a deliberate manifest edit.

**Not** public API (replaceable but not stable, ADR-0017 §BC scope):
concrete action class names and constructor signatures, pending-builder
internals, the workflow engine internals, and model non-relation
internals. Decorating subclasses should call the parent, not copy
internals; action-internal changes that could affect subclasses are
flagged in `UPGRADE.md`.

## Supported versions

Security and bug fixes target the latest minor of the current major.
See [SECURITY.md](../SECURITY.md) for vulnerability reporting.

## Laravel & PHP support

The package supports the actively-maintained Laravel releases it is
tested against (currently Laravel 12 and 13) on PHP 8.4+. Dropping a
Laravel or PHP version that is itself out of support is a **minor**
release, not a major — consistent with the wider Laravel ecosystem.
