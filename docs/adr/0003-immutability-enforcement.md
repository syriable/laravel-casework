# ADR-0003 — Immutability Enforcement Strategy

**Status:** Accepted
**Date:** 2026-07-14

## Context

Decisions, investigation notes and evidence, and audit entries
are immutable: corrections and reversals are new records referencing the originals. The
package supports MySQL, PostgreSQL, MariaDB, and SQLite, and must remain a
plain Laravel package (no framework, no DBA requirements).

## Problem

Where and how is immutability enforced — database, model layer, or documentation only?

## Alternatives

1. **Model-layer enforcement** — the Eloquent models refuse `update`/`delete` (guarded in
   the model lifecycle, throwing a dedicated exception), and the package exposes no
   mutating API for these records.
2. **Database triggers / rules** — DB-level rejection of UPDATE/DELETE on immutable tables.
3. **Documentation only** — convention without enforcement.

## Decision

**Alternative 1: model-layer enforcement.** Immutable models (Decision, Note, Evidence,
AuditEntry) MUST throw a dedicated package exception on any update or delete attempt
through Eloquent, and no package API may mutate them. State-carrying records (Report,
Case, Restriction, Appeal) are *not* immutable, but their `state` may change **only**
through transitions (invariant I-03) — direct state assignment also throws.

Database triggers are rejected: not portable across the four supported databases without
per-vendor code, invisible to application developers, and hostile to testing.

## Consequences

- **+** Portable, testable, visible: immutability violations fail fast with a clear
  exception in any environment including SQLite tests.
- **+** Keeps the package free of vendor-specific SQL and migration complexity.
- **−** Not tamper-proof at the SQL layer: raw queries or DB consoles can still mutate
  rows. Accepted — database-level hardening is the application/DBA's domain; the
  documentation MUST state this boundary explicitly.
- Opt-in audit pruning is the single documented exception and operates via a
  dedicated command, never via model APIs.
