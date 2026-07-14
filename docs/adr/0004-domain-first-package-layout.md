# ADR-0004 — Domain-First Package Layout

**Status:** Proposed (Gate G4)
**Date:** 2026-07-14
**Phase:** 4 — Architecture

## Context

The roadmap (§4) mandates a domain-centered layout. The domain map defines five bounded
contexts. Laravel packages commonly use technical top-level directories (`Models/`,
`Events/`, `Actions/`), while larger domain-driven codebases group by concept.

## Problem

Should the source tree be organized by technical layer or by domain module — and how do
Laravel conventions survive the choice?

## Alternatives

1. **Technical top-level** (`src/Models`, `src/Events`, `src/Actions`, …) — classic
   Spatie-style; concept members scattered across directories.
2. **Domain-first modules** (`src/Reporting`, `src/Cases`, `src/Enforcement`,
   `src/Appeals`, `src/Audit`), each containing conventional `Models/`, `Actions/`,
   `Events/` subdirectories; shared code in `Support/`, `Contracts/`, `Concerns/`.
3. **Full modular monorepo** (separate composer packages per context) — maximal isolation.

## Decision

**Alternative 2: domain-first modules with conventional insides.** Top level mirrors the
approved bounded contexts; inside each module, directory names stay conventional Laravel
(`Models`, `Actions`, `Events`) so nothing feels foreign. Cross-cutting code is limited to
`Concerns/`, `Contracts/`, `Support/`, `States/`, `Policies/`, `Exceptions/`, `Commands/`.

Alternative 3 is rejected as over-engineering for a single-install package (roadmap:
never build a framework); Alternative 1 is rejected because it hides the architecture the
domain map just established and scatters each feature across six directories.

## Consequences

- **+** The source tree *is* the domain map — approved boundaries stay visible and
  reviewable (dependencies between modules are grep-able).
- **+** Feature work (e.g. appeals) touches one directory; module ownership maps cleanly
  to teams.
- **+** Inner directories remain idiomatic; per-module `Models/` keeps Eloquent
  discovery, factories, and IDE ergonomics unchanged.
- **−** Slightly deeper namespaces (`Casework\Enforcement\Actions\LiftRestriction`) —
  accepted; applications interact mainly via traits, the facade, and contracts (Phase 5).
- **−** Cross-module operations need explicit direction rules — codified in
  [overview §2](../architecture/overview.md): owning module's action orchestrates, calls
  other modules' actions, single transaction.
