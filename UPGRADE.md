# Upgrade Guide

Breaking changes land only in majors, and each major gets a section
here. Every entry follows one format:

> **What changed** / **Why** (ADR link) / **Before** / **After** /
> **Estimated effort**

Additive features in minor versions get [CHANGELOG](CHANGELOG.md)
entries only.

A note on stability boundaries (NFR-08): the contracts in
`Syriable\Casework\Contracts`, the facade surface, events, exceptions,
and config keys are the public API. Concrete action class names and
constructor signatures are *replaceable but not stable* — subclasses
decorating an action should call the parent rather than copy
internals; any action-internal change that could affect such
subclasses will be flagged here.

## v2

### Workflow extension is now transitions-only

**What changed** — `WorkflowDefinition::customStates()` is removed.
Applications may still add custom *transitions* between a lifecycle's
*existing* states (core or otherwise already declared), with the full
pipeline (authorization, guards, audit entry, the generic
`StateTransitioned` event) — but a custom transition can no longer
target a state name that didn't already exist.

**Why** — [ADR-0019](docs/adr/0019-narrow-workflow-extension-to-transitions.md).
The capability this removes — introducing a genuinely new named state
— required a boot-time graph-reachability engine
(`WorkflowDefinition::validate()` walking the transition graph to prove
every custom state was reachable and had a path back out) that was the
single most intricate piece of machinery in the package, protecting a
capability few applications used. Custom transitions between existing
states — the more common real-world customization (a guarded shortcut,
a "return to open" step) — remain fully supported and needed none of
that machinery.

**Before**

```php
class LegalCaseWorkflow extends CaseWorkflow
{
    protected function customStates(): array
    {
        return ['awaiting_legal'];
    }

    protected function customTransitions(): array
    {
        return [
            new TransitionDefinition('sendToLegal', ['under_investigation'], 'awaiting_legal'),
            new TransitionDefinition('legalCleared', ['awaiting_legal'], 'awaiting_decision'),
        ];
    }
}
```

**After** — model the extra step as application-owned data instead of
a new workflow state (a case note, a flag/column your own migration
adds), and use a custom transition only between states the shipped
workflow already declares:

```php
class CaseWorkflowWithSecondReview extends CaseWorkflow
{
    protected function customTransitions(): array
    {
        return [
            // A guarded shortcut between two existing states — no new
            // state name involved.
            new TransitionDefinition(
                'returnToOpen',
                ['under_investigation'],
                'open',
                [RequiresSecondOpinion::class],
            ),
        ];
    }
}
```

If your application does not override `customStates()` on any
`WorkflowDefinition` subclass, this upgrade requires no code changes.

**Estimated effort** — none for most applications; for those relying on
custom states, redesign the extra step as data rather than state
(typically under an hour, plus a migration if the data needs a column).

## v1

Initial release — nothing to upgrade from.
