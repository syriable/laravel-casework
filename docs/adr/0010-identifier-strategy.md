# ADR-0010 — Identifier Strategy

**Status:** Proposed (Gate G6)
**Date:** 2026-07-14
**Phase:** 6 — Database Design

## Context

NFR-12 requires one consistent identifier decision. Package tables reference each other
(FKs) and reference *application* models polymorphically (ADR-0001). Applications key
their models with bigints, UUIDs, or ULIDs — the package cannot know which, and the
enforcement hot path (NFR-04) filters on morph id columns.

## Problem

What key type do package primary keys use, and what column type holds polymorphic ids
pointing at unknown application models?

## Alternatives

1. **bigint PKs + bigint morph ids** (Laravel `morphs()` default) — fastest, but breaks
   instantly for UUID/ULID-keyed apps unless they edit migrations.
2. **ULID PKs + string morph ids** — sortable, merge-friendly, but larger internal
   indexes and unfamiliar to most consumers for no requirement-backed gain.
3. **bigint PKs + `string(36)` morph ids** — internal efficiency where we control keys;
   universal compatibility where we don't.
4. **Configurable key type everywhere** — maximal flexibility, doubled test matrix and
   permanent complexity.

## Decision

**Alternative 3.** Package tables use bigint auto-increment primary keys and bigint FKs
between package tables. Every polymorphic `*_id` column (subject, reporter, actor,
assignee, issuer, appellant, reviewer, auditable) is `string(36)`, accepting bigint,
UUID, and ULID application keys with zero configuration.

Published migrations (FR-954) are the documented tightening point: applications with
all-bigint models MAY change morph ids to `unsignedBigInteger` before first run for
maximum index density; the package works identically either way (Eloquent casts ids to
string transparently in morph queries).

Alternative 4 is rejected as permanent complexity for a one-time concern (over-
engineering); Alternative 1 fails the zero-config requirement (FR-951) for a growing
share of Laravel apps; Alternative 2 buys nothing any requirement asks for.

## Consequences

- **+** `composer require` → migrate → works, for every key strategy (M1, FR-951).
- **+** Internal joins/FKs stay compact bigint; only morph columns pay the string cost.
- **−** Hot-path index entries are wider than pure-bigint — bounded by `string(36)` and
  reclaimable via the documented migration edit; the index remains covering either way
  (NFR-04 holds).
- **−** Two column styles in one schema — accepted: the rule is mechanical ("our rows:
  bigint; your rows: string(36)") and stated in the schema doc.
