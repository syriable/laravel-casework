# Reporting

Any Eloquent model can be reported. Mark it with the `Reportable` contract and
the `InteractsWithReports` trait:

```php
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Concerns\InteractsWithReports;
use Syriable\Casework\Contracts\Reportable;

class Post extends Model implements Reportable
{
    use InteractsWithReports;
}
```

## Filing a report

```php
use Syriable\Casework\Facades\Casework;

$report = Casework::report($post)
    ->by($user)                            // or ->anonymously() or ->bySystem()
    ->because('spam')                      // reason key or Reason model — required
    ->comment('Links to a phishing site')  // optional, stored opaquely
    ->withMetadata(['url' => $url])        // optional structured context
    ->file();
```

`file()` throws:

| Exception | When |
|---|---|
| `DuplicateReport` | the same reporter already has an open report on this subject for this reason (disable via `casework.reporting.allow_duplicates`) |
| `UnknownReason` | the reason key does not exist, or the reason is deactivated |
| `AuthorizationException` | the actor fails the `file` policy ability, or anonymous reporting is disabled (`casework.reporting.allow_anonymous`) |
| `IncompleteBuilder` | no reporter origin or no reason was provided |
| `ReporterBlocked` | the reporter's reputation score is at or below `casework.reporting.reputation.block_threshold` |
| `ReportRateLimited` | the reporter already filed `casework.reporting.reputation.rate_limit` reports within the configured window |

Anonymous reports store no identity at all; system reports attribute automation
(both skip the duplicate guard — they carry no comparable reporter identity).

## Managing reasons

Reasons are plain Eloquent rows — runtime data, not configuration:

```php
use Syriable\Casework\Reporting\Models\Reason;

Reason::create(['key' => 'phishing', 'label' => 'Phishing', 'category' => 'fraud']);

$reason->deactivate();   // new reports may not use it; history keeps it
Reason::query()->active()->get();
```

## Working reports

```php
Casework::startReportReview($report, by: $moderator);   // pending → under_review
Casework::dismissReport($report, by: $moderator);       // unattached reports only
```

Reports attached to a case are resolved or dismissed by the case's decision,
never directly. Moderation abilities (`startReview`, `dismiss`, `resolve`,
`attachToCase`) are **denied by default** for model actors — register your own
policy for the `Report` model to grant them to your moderators. System-attributed
automation passes without policies.

## Querying

```php
use Syriable\Casework\Reporting\Models\Report;

Report::query()->pending()->forSubject($post)->get();
Report::query()->byReporter($user)->withReason('spam')->get();
Report::query()->fromSystem()->count();

$post->reports;            // all reports about the model
$post->openReports;        // not yet resolved/dismissed
$post->hasOpenReports();
```

## Events

Every operation dispatches its catalog event after its transaction commits —
a received event always means durable state: `ReportFiled`,
`ReportReviewStarted`, `ReportAttachedToCase`, `ReportDismissed`,
`ReportResolved`. Each writes the matching `report.*` audit entry.

## Reporter reputation

Off by default. Turn it on to track report-quality signal per
reporter — a score that moves as their reports are dismissed or
upheld — and, if you choose, to block or rate-limit reporters based on
it (extension point X14):

```php
// config/casework.php
'reporting' => [
    'reputation' => [
        'enabled' => true,          // start tracking scores
        'dismissed_delta' => -1,    // score change when a report is dismissed
        'upheld_delta' => 1,        // score change when a report is upheld/escalated
        'block_threshold' => -5,    // null = tracking only, nobody is blocked
        'rate_limit' => 10,         // reports per window; null = no limit
        'rate_limit_window_minutes' => 60,
    ],
],
```

Each gate is independent: enable tracking without blocking anyone, add
a `block_threshold` later once you trust the signal, and set a
`rate_limit` for report-bombing protection regardless of whether
tracking is on. Only model-origin reporters are scored, blocked, or
rate-limited — system and anonymous origins carry no identity to track,
the same boundary the duplicate-report guard uses.

Read the score from either side:

```php
use Syriable\Casework\Concerns\HasReporterReputation;

class User extends Authenticatable
{
    use HasReporterReputation;   // reputation(), reputationScore(), isBlockedFromReporting()
}

$user->reputationScore();          // 0 if no reports have been dismissed or upheld yet
$user->isBlockedFromReporting();
Casework::isReporterBlocked($user); // the same check for non-trait contexts
```

A moderator can correct a score by hand — audited exactly like the
automatic adjustment, and denied for model actors until you register a
policy for the `ReporterReputation` model:

```php
Casework::adjustReputation($user, delta: 5, reason: 'Verified helpful reporter', by: $moderator);
```

The scoring rule itself is swappable — bind your own class via
`casework.reporting.reputation.policy` to weight by reason severity,
account age, or anything else you track:

```php
use Syriable\Casework\Contracts\ReputationPolicy;

class SeverityWeightedReputationPolicy implements ReputationPolicy
{
    public function deltaForDismissal(Report $report): int { /* … */ }
    public function deltaForResolution(Report $report, Decision $decision): int { /* … */ }
}
```

Every score change writes a `reporter.reputation_adjusted` audit entry
and dispatches `ReporterReputationChanged`; crossing into the blocked
state additionally writes `reporter.blocked` and dispatches
`ReporterBlocked` — once, not on every subsequent adjustment that
leaves the reporter still blocked.
