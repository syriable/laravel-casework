# ADR-0008 — Case Entity Class Naming

**Status:** Proposed (Gate G5)
**Date:** 2026-07-14
**Phase:** 5 — Public API

## Context

The central domain term is **Case** (glossary, Gate G2), but `case` is a PHP reserved
word — `class Case` is illegal. The model class needs a name that stays as close to the
ubiquitous language as possible, since model class names surface in application code,
morph types, factories, and policies.

## Problem

What is the Eloquent model class name for the Case entity, and its table name?

## Alternatives

1. **`CaseFile`** — the real-world trust & safety artifact ("case file"); matches the
   package name *casework*.
2. **`ModerationCase`** — descriptive, but redundant inside a moderation package and
   longer in every reference.
3. **`CaseRecord`** — generic suffix carrying no domain meaning.
4. **`Kase`/`CaseModel`** — misspellings/technical suffixes; rejected on sight (naming
   quality).

## Decision

**`CaseFile`**, in `Syriable\Casework\Cases\Models\CaseFile`. Table name
`casework_cases` (the table, unlike the class, may use the real word; all package tables
share the configurable `casework_` prefix — finalized in Phase 6). Relationship and
method names use the plain domain word wherever PHP allows: `$post->cases()`,
`Casework::openCase()`, `assignCase()`, `CaseOpened` event.

## Consequences

- **+** Reads naturally ("open a case file"), ties to the package identity, and is the
  shortest non-reserved faithful name.
- **+** The reserved-word compromise is confined to the class identifier; API methods,
  events, tables, and docs keep saying "case".
- **−** Developers may first guess `Case::query()` — mitigated by prominent README note
  and an IDE-friendly `@see` on the traits/facade.
