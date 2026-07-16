# Laravel Trust & Safety (laravel-casework)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/syriable/laravel-casework.svg?style=flat-square)](https://packagist.org/packages/syriable/laravel-casework)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/syriable/laravel-casework/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/syriable/laravel-casework/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/syriable/laravel-casework/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/syriable/laravel-casework/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/syriable/laravel-casework.svg?style=flat-square)](https://packagist.org/packages/syriable/laravel-casework)

A complete, **UI-agnostic** moderation platform for Laravel: user
reports, moderation cases, decisions, enforcement (restrictions,
suspensions, warnings), appeals, and a tamper-evident audit trail —
as composable domain operations with no views, routes, or opinions
about your stack. You bring the UI; the package brings the workflow
integrity.

## Features

- **Reporting** — fluent report filing with reasons-as-data, duplicate
  guards, anonymous/system origins, and configurable case-opening
  strategies
- **Reporter reputation** — opt-in per-reporter scoring that reacts to
  dismissed/upheld reports, with an optional block threshold and
  per-reporter rate limiting to blunt report-bombing
- **Case management** — cases with assignment, investigation, notes,
  evidence, priorities, and state machine-managed lifecycles
- **Decisions** — atomic decisions that resolve reports and apply
  enforcement in one transaction, with supersession chains instead of
  edits
- **Enforcement** — typed, scoped, expiring restrictions with a
  single-query `isRestricted()` hot path honoring expiry in real time
- **Appeals** — appeal windows and limits, independent-reviewer
  enforcement, and atomic overturns that lift restrictions and record
  superseding decisions
- **Audit** — one append-only entry per domain action; no unaudited
  path exists, from your code or the package's own automation
- **Events & automation** — an after-commit event per action, a
  config-registered notifier hook, and intake/triage pipelines for
  ML scoring, auto-dismissal, and auto-assignment
- **Extensible without forking** — override models, rebind actions and
  guards, extend workflows (add-only, boot-validated), swap
  strategies and scope resolution

## Installation

```bash
composer require syriable/laravel-casework
php artisan migrate
```

Requires PHP 8.3+ and Laravel 12+.

Reports classify against reasons-as-data, so create at least one reason
before filing (idempotent, seeder-friendly):

```bash
php artisan casework:make-reason spam
```

Every config key has a working default. Publishing is optional:

```bash
php artisan vendor:publish --tag="casework-config"
php artisan vendor:publish --tag="casework-migrations"
```

See the [installation guide](docs/guide/installation.md) for key-type
notes (bigint/ULID/UUID all supported) and the morph-map
recommendation.

## Quickstart

```php
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Support\Outcome;

// Mark models: Reportable + InteractsWithReports,
//              Restrictable + InteractsWithRestrictions.

$report = Casework::report($post)
    ->by($user)
    ->because('spam')
    ->comment('Links to a phishing site')
    ->file();

$case = Casework::openCase($post)->bySystem()->open();

$decision = Casework::decide($case)
    ->by($moderator)
    ->outcome(Outcome::UPHOLD)
    ->withSuspension(days: 30)
    ->finalize();          // case + reports + restriction + audit, atomically

$user->isSuspended();      // one indexed query, expiry honored in real time

$appeal = Casework::appeal($decision)->by($user)->submit();
Casework::resolveAppeal($appeal)->by($reviewer)->overturn();
```

The [quickstart guide](docs/guide/quickstart.md) walks this end to
end.

> **Why `CaseFile`?** The case entity is named `CaseFile` because
> `case` is a reserved word in PHP — everything else (tables, config,
> docs) still says "case".

## Documentation

| Guide | Covers |
|---|---|
| [Installation](docs/guide/installation.md) | require, migrate, key types, morph map |
| [Quickstart](docs/guide/quickstart.md) | the full loop, end to end |
| [Reporting](docs/guide/reporting.md) | reports, reasons, duplicate rules |
| [Cases & decisions](docs/guide/cases-and-decisions.md) | case ops, notes, evidence, deciding |
| [Enforcement](docs/guide/enforcement.md) | restrictions, warnings, expiry, the hot path |
| [Appeals](docs/guide/appeals.md) | windows, limits, independence, overturns |
| [Audit](docs/guide/audit.md) | querying history, immutability, pruning |
| [Authorization](docs/guide/authorization.md) | policies, scopes, self-moderation |
| [Events](docs/guide/events.md) | catalog, after-commit guarantee, notifiers |
| [Automation](docs/guide/automation.md) | intake/triage pipelines |
| [Extending](docs/guide/extending.md) | all fourteen extension points |
| [Workflows](docs/guide/workflows.md) | the four state machines |
| [Configuration](docs/guide/configuration.md) | every key, defaults, validation |
| [Exceptions](docs/guide/exceptions.md) | what throws, when |
| [Testing your integration](docs/guide/testing-your-integration.md) | factories, fakes, assertions |

Architecture decision records live in [docs/adr/](docs/adr/).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [syriable](https://github.com/syriable)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
