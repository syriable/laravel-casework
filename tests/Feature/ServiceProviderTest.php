<?php

declare(strict_types=1);

use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\NullScopeResolver;
use Workbench\App\Models\Post;

it('boots with the shipped zero-config defaults', function (): void {
    // FR-951: the provider's boot validation ran during setUp without
    // throwing, and config is merged.
    expect(config('casework.table_prefix'))->toBe('casework_')
        ->and(config('casework.cases.strategy'))->toBe('threshold')
        ->and(config('casework.appeals.limit_per_target'))->toBe(1);
});

it('binds ScopeResolver to the null resolver as a singleton', function (): void {
    $resolver = app(ScopeResolver::class);

    expect($resolver)->toBeInstanceOf(NullScopeResolver::class)
        ->and(app(ScopeResolver::class))->toBe($resolver)
        ->and($resolver->scopesFor(new Post))->toBeNull()
        ->and($resolver->scopeOf(new Post))->toBeNull();
});

it('resolves model classes through the registry', function (): void {
    expect(ModelRegistry::classFor('report'))
        ->toBe(\Syriable\Casework\Reporting\Models\Report::class);

    config()->set('casework.models.report', 'App\\Custom\\Report');

    expect(ModelRegistry::classFor('report'))->toBe('App\\Custom\\Report');
});

it('applies the configured table prefix to package models', function (): void {
    expect((new \Syriable\Casework\Reporting\Models\Report)->getTable())->toBe('casework_reports')
        ->and((new \Syriable\Casework\Cases\Models\CaseFile)->getTable())->toBe('casework_cases');

    config()->set('casework.table_prefix', 'ts_');

    expect((new \Syriable\Casework\Audit\Models\AuditEntry)->getTable())->toBe('ts_audit_entries');
});
