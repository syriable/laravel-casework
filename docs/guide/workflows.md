# Workflows

Four state machines govern the domain. States are stored as
strings, transitions are verbs, and state columns are write-protected:
only the workflow engine moves them — assigning `$report->state`
directly throws. (Stateful package models implement the `Stateful`
contract.)

Each lifecycle pairs a state enum with a container-bound definition:
`ReportState`/`ReportWorkflow`, `CaseState`/`CaseWorkflow`,
`RestrictionState`/`RestrictionWorkflow`,
`AppealState`/`AppealWorkflow`.

## Report

```mermaid
stateDiagram-v2
    [*] --> pending : file
    pending --> under_review : startReview
    pending --> attached_to_case : attachToCase
    under_review --> attached_to_case : attachToCase
    pending --> dismissed : dismiss
    under_review --> dismissed : dismiss
    pending --> resolved : resolve
    under_review --> resolved : resolve
    attached_to_case --> resolved : resolve
    attached_to_case --> dismissed : dismiss†
    resolved --> [*]
    dismissed --> [*]
```

† attached reports resolve or dismiss only through their case's
decision.

## Case

```mermaid
stateDiagram-v2
    [*] --> open : open
    open --> under_investigation : startInvestigation
    under_investigation --> awaiting_decision : submitForDecision
    open --> decided : decide
    under_investigation --> decided : decide
    awaiting_decision --> decided : decide
    decided --> closed : close
    closed --> [*]
```

Priority (escalation) is an attribute, not a state.

## Restriction

```mermaid
stateDiagram-v2
    [*] --> active : apply
    active --> expired : expire
    active --> lifted : lift
    active --> superseded : supersede
    expired --> [*]
    lifted --> [*]
    superseded --> [*]
```

A stored `active` past its `expires_at` already counts as inactive
everywhere — the expiry transition is bookkeeping
([enforcement](enforcement.md)).

## Appeal

```mermaid
stateDiagram-v2
    [*] --> submitted : submit
    submitted --> under_review : startReview
    submitted --> rejected : reject
    under_review --> upheld : uphold
    under_review --> overturned : overturn
    under_review --> rejected : reject
    upheld --> [*]
    overturned --> [*]
    rejected --> [*]
```

## Invalid transitions

Every transition attempt from a wrong state throws
`InvalidTransition`, carrying the record, transition name, and
from-state. Guards may veto an otherwise-legal transition by throwing.

## Custom transitions

Applications extend a lifecycle by subclassing its
`WorkflowDefinition` and rebinding it — add-only, boot-validated:
shipped states, transitions, and terminals can never be removed or
rewired, so package code keeps working (ADR-0019). A custom transition
connects two *existing* states (it cannot introduce a new one) and
gets the full pipeline: authorization, guards, an audit entry, and the
generic `StateTransitioned` event. The how-to lives in
[extending](extending.md#workflow-extension-x5).
