# ADR-0017 — Final vs Open Class Policy

**Status:** Proposed (Gate G9)
**Date:** 2026-07-14
**Phase:** 9 — Extension System

## Context

Every non-final class is an implicit extension contract: subclass behavior must survive
upgrades, multiplying the BC surface (NFR-08). Every final class is a wall: needs must be
met elsewhere. The package promises extension without forking (vision pillar 7) *and*
long-term maintainability.

## Problem

Which package classes may applications subclass, and which are sealed?

## Alternatives

1. **Kind-based policy** — openness decided per class *kind*, uniformly.
2. **Everything open** — no `final` anywhere (maximal flexibility, maximal BC surface).
3. **Everything final** — extension only via contracts (pure composition).

## Decision

**Alternative 1: a uniform kind-based policy.**

| Kind | Policy | Rationale |
|---|---|---|
| Eloquent models | **Open** (non-final, designed for subclassing) | X1 model overrides are a core requirement (FR-901); protected surface = relations, scopes, casts |
| Action classes | **Open**, replace via container rebind (X11) | Decoration/extension of operations is a stated need; class name is the binding key |
| Guard classes | **Open**, rebind individually (X13) | Single-guard customization without touching workflows |
| Workflow definitions | **Open** within ADR-0013 bounds | FR-903 |
| Event classes | **Final** (ADR-0014) | Catalog authority; extension = listening |
| Value objects | **Final** | Invariant carriers; open sets are string-backed by design, not by subclassing |
| Builders (pending operations) | **Final** | Their semantics belong to the actions they feed (ADR-0009); customize the action, not the sugar |
| Workflow engine | **Final** (internal) | ADR-0012 — internals must stay swappable by *us* without BC breaks |
| Exceptions | **Final**; catch via `CaseworkException` / class | Stable catch surfaces; new failure modes are new classes (ADR-0006) |
| Audit Recorder | **Final, not rebindable** | I-04 unforgeability from the extension surface (T10) |
| Service provider, facade, commands | **Final** | Integration edge; configuration is the customization point |

Everything-open (2) is rejected — it converts every internal refactor into a potential
break; everything-final (3) is rejected — it fails FR-901/903/904 and forces interface
ceremony onto simple needs.

## Consequences

- **+** Openness is predictable by kind — no per-class debate, review enforces one table.
- **+** BC surface is consciously bounded: models/actions/guards/definitions are the
  documented subclass points, everything else evolves freely behind contracts and events.
- **−** `final` occasionally frustrates unforeseen needs — the designed answer is a
  feature request or contract addition, not a fork; documented in extending.md.
- PHPStan rule (Phase 11/14): `final` enforced by default, non-final only for the kinds
  listed open.
