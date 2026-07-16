# ADR-0009 — Pending-Operation Builders for Multi-Option Operations

**Status:** Accepted
**Date:** 2026-07-14

## Context

Several domain operations take one or two required inputs plus several optional aspects:
filing a report (reporter origin, comment, metadata), applying a restriction (duration,
scope, rationale), deciding a case (outcome, rationale, N enforcement actions), submitting
an appeal (statement). Actions (ADR-0005) implement these operations; the question is the
*calling* surface.

## Problem

How do developers invoke multi-option operations without positional-argument soup, while
simple operations stay one-liners?

## Alternatives

1. **Fluent pending-operation builders** ending in an explicit verb
   (`Casework::report($post)->by($user)->because('spam')->file()`).
2. **Named-argument methods only**
   (`Casework::fileReport(subject: $post, reporter: $user, reason: 'spam', …)`).
3. **Array options** (`Casework::fileReport($post, ['reporter' => $user, …])`).

## Decision

**Hybrid, with a bright-line rule:**

- Operations with **≥3 optional aspects or compound sub-objects** use a *pending-operation
  builder*: an immutable intent object built fluently, executed only by its terminal verb
  (`file()`, `open()`, `finalize()`, `apply()`, `issue()`, `submit()`). Builders validate
  at the terminal call, delegate to the action, and are themselves part of the public API.
  Applies to: report filing, case opening, deciding, restricting/suspending/warning,
  appeal submission and resolution.
- Everything else is a **single facade method with named arguments** (≤2 aspects beyond
  the target): `assignCase($case, to: $m, by: $lead)`, `lift($restriction, by: $m,
  reason: …)`, `dismissReport(…)`, `note(…)`.

Array options (3) are rejected: untyped, unautocompletable, and undiscoverable. Pure named
arguments (2) are rejected for the compound cases — a decision carrying three enforcement
actions is unreadable as one call.

## Consequences

- **+** Reads like Laravel (query builder, mail pending objects, Http client); IDE
  autocompletion documents each operation's options.
- **+** Terminal-verb execution keeps intent explicit — nothing happens until `file()`;
  half-built intents are inert and test-friendly.
- **+** The bright-line rule prevents API drift into "everything is a builder".
- **−** Builders are additional public classes to maintain and BC-govern — bounded: one
  per compound operation, enumerated in the [public API manifest](../api/frozen-api-1.0.md).
- **−** Two invocation styles exist — mitigated by the rule being mechanical and
  documented in the API spec header.
