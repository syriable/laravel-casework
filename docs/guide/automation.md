# Automation

Two pipeline-style intercept points let applications automate
moderation without forking: **intake** (as a report is filed) and
**triage** (as a case opens). Stages are declared in config, resolved
from the container, and run in listed order:

```php
// config/casework.php
'pipelines' => [
    'intake' => [
        App\Moderation\DismissBannedReporters::class,
        App\Moderation\ScoreWithMl::class,
    ],
    'triage' => [
        App\Moderation\AutoAssignByScope::class,
    ],
],
```

Both lists are validated at boot: entries must exist and implement
their stage contract, or the application fails fast with
`InvalidConfiguration`.

## Intake stages

```php
use Syriable\Casework\Contracts\ReportIntakeStage;
use Syriable\Casework\Reporting\ReportIntake;

class ScoreWithMl implements ReportIntakeStage
{
    public function handle(ReportIntake $intake, Closure $next): ReportIntake
    {
        $intake->metadata['spam_score'] = $this->model->score($intake->comment);

        if ($intake->metadata['spam_score'] > 0.99) {
            $intake->forceCase();          // override the configured strategy
        }

        return $next($intake);
    }
}
```

Stages run inside `FileReport` — after its guards (duplicates,
anonymous policy, reason validation), before persistence. The
`ReportIntake` context offers:

| Call | Effect |
|---|---|
| `$intake->metadata` / `$intake->comment` | mutable, persisted on the report |
| `$intake->forceCase()` | open/join a case regardless of the strategy |
| `$intake->suppressCase()` | skip case creation regardless of the strategy |
| `$intake->autoDismiss()` | persist the report, then dismiss it immediately with System attribution — filed *and* dismissed both land in the audit trail; no case opens |
| throw a domain exception | refuse intake entirely — nothing is persisted |
| return without calling `$next` | short-circuit: later stages never run |

The report's identity — subject, reporter, reason — is fixed before
the pipeline runs.

## Triage stages

```php
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\CaseTriageStage;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Support\ActorRef;

class AutoAssignByScope implements CaseTriageStage
{
    public function handle(CaseFile $case, Closure $next): CaseFile
    {
        Casework::assignCase($case, $this->pickModerator($case), ActorRef::system());

        return $next($case);
    }
}
```

Triage stages run when `CaseOpened` commits. They act **through
package operations** — assign, escalate, note, even decide — as the
System actor, so everything they do receives the full
authorize → guard → transact → audit → event treatment: an automated
suspension is audited exactly like a human one. Stages cannot write
around the pipeline; there is no unaudited path.

Stages can act, but events cannot veto (ADR-0015): if you need to
*prevent* an operation rather than react to it, this pipeline (or a
transition guard) is the place — not a listener.

## Stages are privileged code

Intake and triage stages run with System authority inside the
package's units of work (extending spec §4). Treat the two config
lists like route middleware: code-review every class on them. The
package only ever instantiates stages from config/container — never
from request input — so the only way privileged code runs is that you
listed it.
