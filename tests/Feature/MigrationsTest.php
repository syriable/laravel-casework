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
    $this->artisan('migrate:rollback')->assertSuccessful();

    expect(Schema::hasTable('casework_reports'))->toBeFalse()
        ->and(Schema::hasTable('casework_audit_entries'))->toBeFalse();

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('casework_reports'))->toBeTrue();
});
