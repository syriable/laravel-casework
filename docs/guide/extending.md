# Extending

Every extension point exists because a plausible application needs it;
nothing else is open. The governing rule (ADR-0016): **config declares
_what_, the container resolves _how_.** Rationale and the security
review live in the [extension specification](../extending.md).

| # | Point | How |
|---|---|---|
| X1 | Model overrides | subclass + `casework.models.*` |
| X2 | Custom reasons | `Reason::create` — data, not code |
| X3 | Custom outcomes | `casework.decisions.outcomes` |
| X4 | Custom restriction types | `casework.enforcement.restriction_types` |
| X5 | Workflow states/transitions | subclass the `WorkflowDefinition`, rebind |
| X6 | Scope resolution | bind `Contracts\ScopeResolver` |
| X7 | Case strategy | `casework.cases.strategy` class |
| X8 | Notifiers | `casework.notifiers` — see [events](events.md) |
| X9/X10 | Intake/triage automation | `casework.pipelines.*` — see [automation](automation.md) |
| X11 | Action replacement | container rebind |
| X12 | Policy overrides | `Gate::policy` — see [authorization](authorization.md) |
| X13 | Guard replacement | container rebind |

## Model overrides (X1)

Subclass the package model, point config at it:

```php
class Report extends \Syriable\Casework\Reporting\Models\Report
{
    public function scopeFlaggedAsSpam(Builder $query): void
    {
        $query->where('metadata->spam', true);
    }
}

// config/casework.php
'models' => ['report' => App\Models\Report::class, /* … */],
```

All package code — actions, relations, queries — resolves classes
through the model registry, so `$case->reports` yields your subclass.
Overrides must extend the shipped model; anything else fails at boot
with `InvalidConfiguration`.

**State mutators are unsupported.** State writes go through the
workflow engine regardless of model class; overriding the `state`
attribute's mutators/casts on a subclass is outside the supported
surface and invites drift between stored state and the machine.

## Workflow extension (X5)

Add states and transitions — never remove or rewire shipped ones
(ADR-0013: add-only, terminals closed, boot-validated):

```php
class LegalCaseWorkflow extends \Syriable\Casework\Cases\CaseWorkflow
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

// AppServiceProvider::register()
$this->app->singleton(CaseWorkflow::class, LegalCaseWorkflow::class);
```

Custom transitions dispatch the generic `StateTransitioned` event;
rule violations (orphan states, redirected core transitions, new
terminals) throw `InvalidWorkflow` at boot. Full rules:
[workflow docs](../workflows/overview.md).

## Action replacement (X11)

Actions are container-resolved concrete classes. Decorate by
subclassing and rebinding:

```php
class VipAwareFileReport extends \Syriable\Casework\Reporting\Actions\FileReport
{
    public function execute(Model $subject, ActorRef $by, Reason|string $reason,
        ?string $comment = null, array $metadata = []): Report
    {
        $metadata['vip'] = $this->vip->check($subject);

        return parent::execute($subject, $by, $reason, $comment, $metadata);
    }
}

$this->app->bind(FileReport::class, VipAwareFileReport::class);
```

Contracts are the stable public API (NFR-08); concrete action names
and constructor signatures are *replaceable but not stable* — call the
parent rather than copying internals, and check the upgrade guide on
majors.

## Guard replacement (X13)

Transition guards (`Contracts\TransitionGuard`) are resolved through
the container per check, so a single guard is rebindable without
touching the workflow:

```php
$this->app->bind(CustomAppealWindowGuard::class, RegionAwareWindowGuard::class);
```

## Deliberately closed

Event classes (`final`), the workflow engine, value objects
(`ActorRef`, `Origin`, `Outcome`, `RestrictionType`), the
pending-operation builders (`PendingReport`, `PendingCase`,
`PendingDecision`, `PendingRestriction`, `PendingWarning`,
`PendingAppeal`, `PendingAppealResolution`), exceptions, and audit
writing (the `Recorder` is not swappable — I-04 stays unforgeable
from the extension surface). If one of these blocks you, open an
issue instead of forking.
