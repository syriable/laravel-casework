# ADR-0020 — Reporter Reputation and Rate Limiting

**Status:** Accepted
**Date:** 2026-07-16

## Context

The reporting subsystem had no defense against a reporter who
repeatedly files unfounded or malicious reports, nor against a
coordinated group report-bombing a subject to force a case open
through the `threshold` case strategy. Both are standard trust & safety
problems the package's own domain otherwise covers well.

## Problem

How should the package track report-quality signal per reporter, and
use it to protect the reporting pipeline, without compromising the
existing invariants (single audit write-path, config-driven behavior,
contract-based extensibility)?

## Alternatives

1. **A reputation score + rate limiter, built the same way as every
   other domain concept**: a model, an action as the single write-path,
   audited, eventful, contract-based for the scoring rule, config-gated.
2. **Rate limiting only, no scoring** — simpler, but does nothing about
   a reporter who spaces out bad reports to stay under any rate limit.
3. **Delegate entirely to the application** — no package involvement;
   apps build their own reporter-quality tracking on top of the audit
   trail.

## Decision

**Alternative 1.** Concretely:

- `ReporterReputation` (domain model, one row per reporter) carries a
  materialized `score`, the same "current state + audit history"
  pattern as every other package entity — not a value recomputed from
  the audit trail on every read.
- `AdjustReporterReputation` is the **single write-path** (mirroring
  `Recorder`'s role for audit): every score change, automatic or
  manual, goes through it, so there is exactly one place that writes
  the audit entry (`reporter.reputation_adjusted`) and dispatches
  `ReporterReputationChanged` (and `ReporterBlocked` on the threshold
  transition).
- `AdjustReputationOnReportOutcome` is a **reactive listener** on
  `ReportDismissed`/`ReportResolved` — the same shape as
  `RunTriagePipeline` for `CaseOpened`: it observes committed state
  after the triggering transaction, and its own effect is its own
  audited unit of work, attributed to the System actor.
- `Contracts\ReputationPolicy` (extension point X14) decides the score
  delta for a dismissal or a resolution. The shipped
  `DefaultReputationPolicy` reads two fixed deltas from config; an
  application needing something richer (reason severity, account age,
  report volume) binds its own class — the same shape as `CaseStrategy`
  (X7).
- Three independent, nullable gates, each off by default:
  `reporting.reputation.enabled` (tracking), `block_threshold`
  (blocking), `rate_limit` (rate limiting). Tracking can run for a
  while, generating real signal, before an application turns on
  enforcement — and turning on tracking never surprises an existing
  installation with newly-blocked users, because nothing blocks until
  a threshold is explicitly set.
- The block and rate-limit checks live in `FileReport`, as two more
  guards alongside the existing anonymous and duplicate-report guards
  — same place, same shape, same "model reporters only" scope (System
  and anonymous origins carry no identity to score).

Alternative 2 (rate limiting only) is rejected: it leaves the
"death by a thousand cuts" reporter — one bad report every few hours,
forever — completely unaddressed, which is the more realistic abuse
pattern. Alternative 3 (no package involvement) is rejected for the
same reason the rest of the domain isn't left to applications: every
application doing trust & safety needs this, and leaving it out forces
every adopter to duplicate audited-write-path plumbing the package
already has.

## Consequences

- **+** A reporter's trustworthiness is tracked with the same audit
  and event guarantees as every other domain action — no unaudited
  path, no bypassable check.
- **+** Nothing changes for an application that doesn't opt in: all
  three gates default to off/null.
- **+** The scoring rule is swappable without forking (X14), matching
  the extension philosophy used everywhere else in the package.
- **−** A reporter's score is a simple accumulator, not a decayed or
  time-windowed measure — an application wanting decay implements it in
  a custom `ReputationPolicy` (e.g. reading the reporter's report
  history and computing a weighted delta) rather than getting it free.
- **−** Manual adjustment (`Casework::adjustReputation()`) needs its
  own Gate policy (`ReporterReputationPolicy`), denied for model actors
  by default — one more policy for applications to register when they
  want moderators to hand-adjust scores, consistent with every other
  administrative action in the package.
