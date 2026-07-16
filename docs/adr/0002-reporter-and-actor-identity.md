# ADR-0002 — Reporter & Actor Identity Strategy

**Status:** Accepted
**Date:** 2026-07-14

## Context

Reports come from authenticated models, the System, or anonymous sources. Every
other domain action (transitions, decisions, lifts, appeals) must be attributed to an
actor, where automation acts as the System. The package must not own or
assume a User model (non-goal W-04) and must not force applications to pollute their user
tables with sentinel rows.

## Problem

How are "who did this" and "who reported this" stored, uniformly, when the answer may be
an arbitrary Eloquent model, the System, or (for reports only) nobody?

## Alternatives

1. **Nullable `morphTo` + explicit origin discriminator** — `actor_type`/`actor_id`
   nullable, plus a non-null origin value (`model` / `system` / `anonymous`).
2. **Sentinel rows** — require applications to create special "System" and "Anonymous"
   users referenced like normal actors.
3. **Separate identity tables per origin** — distinct system/anonymous/actor reference
   tables joined per query.

## Consequences-driven Decision

**Alternative 1.** All actor attribution in the package uses a nullable polymorphic
reference plus an explicit origin:

- Origin `model` ⇔ the polymorphic reference is present (invariant I-01).
- Origin `system` ⇒ reference absent; the action came from automation/scheduling.
- Origin `anonymous` ⇒ reference absent; **valid only for report reporters** — every
  other domain action requires `model` or `system`.

The same pattern (one VO, one column convention) applies to reporters, case assignees,
deciders, issuers, lifters, appellants, reviewers, and audit actors. Assignee-type
references (case assignee, appeal reviewer) are origin-`model` only.

## Consequences

- **+** No sentinel rows in application tables; no assumptions about a User model (W-04).
- **+** Uniform: one pattern learned once, used in every context; queries like "all
  system-generated reports" are a simple origin filter.
- **+** Anonymous reporting requires no identity fabrication and stores no PII.
- **−** Two columns + discriminator instead of one FK; the origin/reference consistency
  invariant must be enforced in code and covered by tests.
- **−** "Anonymous" is non-attributable by design — applications needing pseudonymous
  tracking must use origin `model` with their own throwaway identity model.
