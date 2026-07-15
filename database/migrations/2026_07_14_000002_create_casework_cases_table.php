<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('cases'), function (Blueprint $table): void {
            $table->id();
            // string(36) morph ids accept bigint, UUID, and ULID keys (ADR-0010).
            $table->string('subject_type');
            $table->string('subject_id', 36);
            $table->string('state', 32);
            $table->string('priority', 64);
            $table->string('assignee_type')->nullable();
            $table->string('assignee_id', 36)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], $this->table('cases').'_subject_idx');
            $table->index(['assignee_type', 'assignee_id', 'state'], $this->table('cases').'_assignee_idx');
            $table->index(['state', 'priority'], $this->table('cases').'_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('cases'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
