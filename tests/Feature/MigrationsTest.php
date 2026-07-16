<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates all ten package tables with the configured prefix', function (): void {
    foreach ([
        'casework_reasons', 'casework_reports', 'casework_cases',
        'casework_case_notes', 'casework_case_evidence', 'casework_decisions',
        'casework_restrictions', 'casework_warnings', 'casework_appeals',
        'casework_audit_entries',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('omits updated_at on immutable tables', function (): void {
    // ADR-0003/0011: decisions, notes, evidence, and audit entries are
    // immutable — no updated_at column exists to drift.
    foreach ([
        'casework_decisions', 'casework_case_notes',
        'casework_case_evidence', 'casework_audit_entries',
    ] as $table) {
        expect(Schema::hasColumn($table, 'created_at'))->toBeTrue()
            ->and(Schema::hasColumn($table, 'updated_at'))->toBeFalse("{$table} must not have updated_at");
    }
});

it('rolls back cleanly', function (): void {
    // Invoke the migration objects directly: artisan rollback's batch
    // bookkeeping is not transaction-safe on MariaDB (implicit-commit
    // DDL inside RefreshDatabase's wrapper) and flakes; down()/up()
    // correctness is what this test owns.
    $files = glob(__DIR__.'/../../database/migrations/*.php') ?: [];
    expect($files)->toHaveCount(12);

    foreach (array_reverse($files) as $file) {
        (require $file)->down();
    }

    expect(Schema::hasTable('casework_reports'))->toBeFalse()
        ->and(Schema::hasTable('casework_audit_entries'))->toBeFalse();

    foreach ($files as $file) {
        (require $file)->up();
    }

    expect(Schema::hasTable('casework_reports'))->toBeTrue()
        ->and(Schema::hasTable('casework_audit_entries'))->toBeTrue();
});
