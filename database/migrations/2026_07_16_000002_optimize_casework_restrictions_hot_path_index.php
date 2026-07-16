<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rework the FR-405 hot-path index (Phase 18 review). The original
 * ordering placed `type` before `state`/`expires_at`, so the type-less
 * calls — `isRestricted()` with no arguments and the `activeRestrictions`
 * relation — could only seek on the subject columns and then filter state
 * and expiry outside the index.
 *
 * The new ordering `(subject_type, subject_id, state, expires_at, type)`
 * fully serves the type-less lookups (subject + state equality, then the
 * expiry range), while keeping `type` in the index so type-qualified calls
 * such as `isSuspended()` still resolve without touching the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $index = $this->table('restrictions').'_hot_path';

        Schema::table($this->table('restrictions'), function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
        });

        Schema::table($this->table('restrictions'), function (Blueprint $table) use ($index): void {
            $table->index(['subject_type', 'subject_id', 'state', 'expires_at', 'type'], $index);
        });
    }

    public function down(): void
    {
        $index = $this->table('restrictions').'_hot_path';

        Schema::table($this->table('restrictions'), function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
        });

        Schema::table($this->table('restrictions'), function (Blueprint $table) use ($index): void {
            $table->index(['subject_type', 'subject_id', 'type', 'state', 'expires_at'], $index);
        });
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
