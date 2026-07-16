# ADR-0006 — Exception Strategy

**Status:** Accepted
**Date:** 2026-07-14

## Context

Domain operations can fail for domain reasons (invalid transition, duplicate report,
closed appeal window, immutability violation — invariants I-01…I-15) and for
authorization reasons. Applications need to catch package failures precisely
(one specific failure) or broadly (anything from the package), and render them in their
own UI/API — which the package must not assume.

## Problem

How are failures surfaced: exceptions vs. result objects, and with what hierarchy?

## Alternatives

1. **Marker interface + domain-named exceptions** — `CaseworkException` interface;
   concrete classes per failure with typed context properties; Laravel-native
   `AuthorizationException` for authz.
2. **Single package exception** with a code/enum discriminator.
3. **Result objects** — operations return success/failure values, never throw.

## Decision

**Alternative 1.** Every package-thrown exception implements the
`Syriable\Casework\Exceptions\CaseworkException` marker interface. Concrete exceptions
are named in the ubiquitous language (`InvalidTransition`, `DuplicateReport`,
`ImmutableRecord`, `AppealWindowClosed`, `AppealLimitReached`, `ReviewerNotIndependent`,
`UnknownReason`, …), extend the most fitting SPL/Illuminate base, and expose their context
(the models/states involved) as public readonly properties — no message parsing.
Authorization failures use Laravel's `AuthorizationException` unchanged, so `denies`/
`authorize` semantics match every other Laravel app.

Result objects (3) are rejected: they fight Laravel idiom, force checking discipline onto
every caller, and violate Simple > Clever. A single discriminated exception (2) is
rejected: it makes precise catching stringly-typed.

## Consequences

- **+** `catch (CaseworkException $e)` for broad handling; `catch (AppealWindowClosed $e)`
  for precise flows (e.g. rendering a "too late" page) — with typed data, no parsing.
- **+** Authorization behaves exactly like the host application's own policies.
- **+** Exceptions are part of the public API: documented per operation
  in the guides, BC-governed thereafter.
- **−** New failure modes in minor versions must extend existing catch surfaces
  compatibly (new subclasses are additive; changing a parent class is breaking) — noted
  in the [support policy](../support-policy.md).
