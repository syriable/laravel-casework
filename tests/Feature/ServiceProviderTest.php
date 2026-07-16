<?php

declare(strict_types=1);

use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Reporting\Models\Report;
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
        ->toBe(Report::class);

    config()->set('casework.models.report', 'App\\Custom\\Report');

    expect(ModelRegistry::classFor('report'))->toBe('App\\Custom\\Report');
});

it('applies the configured table prefix to every package model', function (): void {
    // Schema table names, resolved through the registry.
    $expected = [
        'report' => 'reports',
        'reason' => 'reasons',
        'case' => 'cases',
        'note' => 'case_notes',
        'evidence' => 'case_evidence',
        'decision' => 'decisions',
        'restriction' => 'restrictions',
        'warning' => 'warnings',
        'appeal' => 'appeals',
        'audit_entry' => 'audit_entries',
        'reporter_reputation' => 'reporter_reputations',
    ];

    expect(ModelRegistry::keys())->toBe(array_keys($expected));

    foreach ($expected as $key => $suffix) {
        $class = ModelRegistry::classFor($key);

        expect((new $class)->getTable())->toBe('casework_'.$suffix);
    }

    config()->set('casework.table_prefix', 'ts_');

    expect((new AuditEntry)->getTable())->toBe('ts_audit_entries')
        ->and((new Report)->getTable())->toBe('ts_reports')
        ->and((new CaseFile)->getTable())->toBe('ts_cases');
});
