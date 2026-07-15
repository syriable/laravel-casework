# Events

Every domain action dispatches a dedicated, `final`, past-tense event
after its transaction commits (ADR-0015) — listeners always observe
committed state. The full catalog with payloads lives in
[docs/events/catalog.md](../events/catalog.md).

## Listening

Package events are plain Laravel events:

```php
use Syriable\Casework\Enforcement\Events\RestrictionApplied;

Event::listen(RestrictionApplied::class, function (RestrictionApplied $event) {
    // $event->restriction, $event->by
});
```

Every state-transition event exposes `public string $from`, `string
$to`, and `ActorRef $by` (marked by the `StateTransitionEvent`
contract). Creation events (`ReportFiled`, `CaseOpened`,
`RestrictionApplied`, `WarningIssued`, `AppealSubmitted`) carry no
`$from` — creation is the implicit pseudo-state.

`States\Events\StateTransitioned` is dispatched **only** for
application-defined custom transitions (ADR-0013); core transitions
carry their dedicated classes.

## Events cannot veto

Events are facts about things that already happened — a listener
cannot cancel or modify the operation (ADR-0015). To influence an
operation *before* it commits, use a transition guard (X13) or an
intake/triage stage ([automation](automation.md)); to control who may
attempt it, use policies ([authorization](authorization.md)).

## The notifier hook

Instead of (or alongside) listeners, register single-entry notifiers
(FR-803):

```php
// config/casework.php
'notifiers' => [
    App\Moderation\SlackNotifier::class,
    App\Moderation\SubjectMailer::class,
],
```

```php
use Syriable\Casework\Contracts\Notifier;

class SlackNotifier implements Notifier
{
    public function notify(object $event): void
    {
        // decide internally which events matter; queue your own jobs
    }
}
```

Notifiers are resolved from the container and invoked in listed order
for **every** package event, after commit. They observe — they cannot
veto. Classes are validated at boot: a listed class that does not
implement `Notifier` fails with `InvalidConfiguration`.

The package itself never sends notifications.

## Queued listeners: serialization and re-fetch

Events carry live Eloquent models. A queued listener using
`SerializesModels` stores identifiers and **re-fetches on handle**, so
it sees the row's *current* state, not the state at dispatch time — a
report resolved in the meantime arrives resolved, and a deleted
subject yields a null morph. Handle both.

## Opaque texts may contain PII

Comments, statements, and rationales are opaque to the package but may
contain end-user personal data (NFR-09/10). If a listener ships event
payload contents into external systems (Slack messages, analytics,
tickets), your application owns that disclosure — send ids, not texts,
unless the destination is cleared for them.
