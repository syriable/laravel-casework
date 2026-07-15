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
