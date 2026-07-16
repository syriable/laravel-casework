# ADR-0014 — Event Payload Policy

**Status:** Accepted
**Date:** 2026-07-14

## Context

Every meaningful occurrence must dispatch an event "carrying models
not arrays". Events are the package's primary integration surface;
listeners power notifications and automation. Payload shape is public
API, BC-governed.

## Problem

What exactly do event payloads contain, and what stability guarantees do they carry?

## Alternatives

1. **Live Eloquent models as `final readonly` classes with public typed properties.**
2. **Identifier-only payloads** (`int $reportId`) — listeners re-query.
3. **Dedicated DTOs per event** — decoupled snapshots.

## Decision

**Alternative 1.** Every catalog event is `final readonly`, with public typed properties
holding the live models involved plus scalar context (from/to states, reasons) and
`ActorRef` attribution. Rules:

- Properties are constructor-promoted, never computed lazily; events are inert data.
- `final`: event classes are not extension points — apps extend by listening, not
  subclassing (keeps the catalog authoritative).
- Payload changes after release are **additive only**; removing or retyping a property
  is breaking.
- Events carrying collections type them (`Collection<Restriction>`) and guarantee
  non-null (possibly empty) collections.

Id-only payloads (2) force N re-queries per listener and lose in-memory state (the
just-decided case before any listener mutation); DTOs (3) double every model's surface
and drift from the source — both rejected as DX-hostile or duplicative.

## Consequences

- **+** Listeners get full Eloquent power (relations, morphs) with zero re-query for
  sync listeners.
- **+** `readonly` + inert-data rule keeps events safe to pass through pipelines and
  notifiers.
- **−** Queued listeners re-fetch via `SerializesModels` — observed data may differ from
  dispatch-time data; documented (catalog security section) as inherent to Laravel
  queues, not package-specific.
- **−** BC surface grows with each property — bounded by the catalog's "one event per
  occurrence, none speculative" rule.
