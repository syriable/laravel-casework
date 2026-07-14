<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('audit_entries'), function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 36)->nullable();
            $table->string('origin', 16);
            $table->string('action', 64);
            $table->string('auditable_type');
            $table->string('auditable_id', 36);
            $table->json('payload')->nullable();
            // Append-only: no updated_at (ADR-0011).
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('audit_entries'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
