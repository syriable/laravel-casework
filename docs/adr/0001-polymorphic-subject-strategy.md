# ADR-0001 — Polymorphic Subject Strategy

**Status:** Proposed (Gate G3)
**Date:** 2026-07-14
**Phase:** 3 — Domain Modeling

## Context

Reports, cases, restrictions, warnings, appeals, and audit entries must all target "any
Eloquent model" (FR-101, FR-401, FR-501, FR-701). The package cannot know application
model classes in advance, and the same subject columns must serve queries like "all
reports for this post" and "does this user have an active suspension" efficiently
(FR-108, FR-405, NFR-04).

## Problem

How should the package reference arbitrary application models in its own tables,
uniformly across all contexts?

## Alternatives

1. **Standard Eloquent `morphTo` relations** — `subject_type` + `subject_id` columns,
   native `morphTo`/`morphMany` relations, optional morph map.
2. **Subject registry table** — a `subjects` table mapping (type, id) → internal key;
   package tables foreign-key the registry.
3. **Application-provided subject key** — apps implement a contract converting models to
   opaque string keys; package stores only the key.

## Decision

**Alternative 1: standard Eloquent `morphTo`.** Every polymorphic reference in the
package uses conventional `{name}_type` / `{name}_id` column pairs and native Eloquent
relations. Documentation MUST recommend an enforced morph map
(`Relation::enforceMorphMap`) as a best practice, and the package MUST work with and
without one. Composite indexes on `({name}_type, {name}_id)` are mandatory (finalized in
Phase 6). Column sizing follows the identifier ADR to be made in Phase 6 (NFR-12).

## Consequences

- **+** Idiomatic Laravel: zero learning curve, eager loading, `whereMorphedTo`, factory
  support all work natively (vision pillar "Laravel Native").
- **+** No extra join (vs. registry) on the hot enforcement-check path (NFR-04).
- **+** One uniform pattern across all six polymorphic references.
- **−** Morph type strings couple stored data to class names unless a morph map is used —
  mitigated by documentation and upgrade-guide guidance.
- **−** No database-level referential integrity to subject rows — accepted; standard for
  Laravel polymorphism, and audit/history must survive subject deletion anyway (FR-155
  analog: history outlives its targets).
