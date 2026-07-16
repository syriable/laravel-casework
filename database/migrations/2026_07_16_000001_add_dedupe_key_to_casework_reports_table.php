<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Race-safe duplicate guard (invariant I-02, Phase 18 review). A nullable
 * unique key holds the (subject, reporter, reason) fingerprint while a
 * report is open; the database rejects a second open report for the same
 * fingerprint even when two requests race past the application pre-check.
 *
 * Nullability is load-bearing: system/anonymous reports and reports closed
 * as resolved/dismissed carry a NULL key, and every SQL engine the package
 * targets (MySQL, PostgreSQL, SQLite) permits many NULLs under a unique
 * index — so the constraint applies to exactly the open, attributable
 * reports the invariant covers, and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table('reports'), function (Blueprint $table): void {
            $table->string('dedupe_key', 64)->nullable();

            $table->unique('dedupe_key', $this->table('reports').'_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table($this->table('reports'), function (Blueprint $table): void {
            $table->dropUnique($this->table('reports').'_dedupe_unique');
            $table->dropColumn('dedupe_key');
        });
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
