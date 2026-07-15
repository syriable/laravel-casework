# Installation

Requires PHP 8.4+ and Laravel 12+.

```bash
composer require syriable/laravel-casework
php artisan migrate
```

That's it — the package loads its migrations itself, and every config
key has a working default (the zero-config guarantee, FR-951): after
`migrate`, the full report-to-appeal flow works.

## Publishing (optional)

```bash
php artisan vendor:publish --tag="casework-config"
php artisan vendor:publish --tag="casework-migrations"
```

Publish the config to change the table prefix, override models, or
register automation. Publish the migrations only if you need to edit
schema (e.g. extra indexes) — published copies read the table prefix
from config at run time, so set `casework.table_prefix` *before*
migrating.

## Key types and morph columns

The ten package tables use `bigint` auto-increment primary keys. Every
polymorphic reference (subjects, reporters, actors, …) stores its id
as `string(36)`, so your related models may use **bigint, ULID, or
UUID keys** — no schema change needed (ADR-0010). If your app uses
keys longer than 36 characters, widen those columns in published
migrations before migrating; tightening (e.g. to `bigint` when you
know all participants use integer keys) is equally yours to do there.

## Use a morph map

Package tables store your models' morph class strings. An enforced
morph map keeps those rows stable across refactors and is strongly
recommended (ADR-0001):

```php
use Illuminate\Database\Eloquent\Relations\Relation;

// AppServiceProvider::boot()
Relation::enforceMorphMap([
    'user' => \App\Models\User::class,
    'post' => \App\Models\Post::class,
]);
```

Renaming a model class without a morph map strands existing rows —
with one, the stored alias never changes.

## Next

Continue with the [quickstart](quickstart.md), or jump straight to
[configuration](configuration.md).
