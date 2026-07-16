# ADR-0016 — Extension Binding Strategy

**Status:** Accepted
**Date:** 2026-07-14

## Context

FR-904 requires all replaceable behavior bound in the container against contracts. But
some extension input is data-like (model class map, outcome/type lists, pipeline stage
lists) where a published config file is the Laravel-native declaration point, and some is
behavior-like (scope resolution, strategies, actions, guards) where the container is.
Extension authors need one predictable rule.

## Problem

Which extension points are configured via `config/casework.php`, which via container
bindings — and does the package adopt a manager/driver pattern?

## Alternatives

1. **Hybrid with a bright-line rule** — *config declares what, container resolves how*:
   config holds class names, selections, and lists; the container instantiates
   everything, and behavior contracts are rebindable directly.
2. **Container-only** — all extension via service provider bindings.
3. **Config-only** — every swap is a config key.
4. **Manager/driver pattern** — `Casework::extend('strategy', …)` Laravel-manager style.

## Decision

**Alternative 1.** The rule, applied to every extension point:

- **Config** (declaration): model overrides map, extra outcomes, extra restriction
  types, case-strategy selection + parameters, notifier list, intake/triage stage lists.
  Every class named in config is resolved through the container (so constructor injection
  and app bindings work) and validated at boot (implements the required contract).
- **Container** (behavior): `ScopeResolver`, `CaseStrategy` implementations,
  `WorkflowDefinition`s, action classes, guard classes — rebind or decorate directly in a
  service provider.
- **No manager/driver layer** (4): it duplicates what config + container already do and
  adds a registration API to BC-govern — rejected as over-engineering. Container-only (2)
  hides simple choices (an outcome list) behind code; config-only (3) can't express
  decoration or constructor injection.

## Consequences

- **+** One sentence answers "where do I plug in?": data/list → config; behavior →
  container. Both are day-one Laravel skills.
- **+** Boot validation has a single sweep: read config, resolve, type-check, fail fast.
- **+** No bespoke registration API to document, test, and freeze.
- **−** Two places to look instead of one — mitigated by the rule's mechanical nature and
  the extending.md inventory table naming the mechanism per point.
- **−** Config arrays of class strings aren't refactor-safe — accepted, standard Laravel
  practice (`::class` constants mitigate).
