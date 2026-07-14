<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('case_evidence'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained($this->table('cases'))->restrictOnDelete();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->json('data')->nullable();
            $table->string('author_type')->nullable();
            $table->string('author_id', 36)->nullable();
            $table->string('origin', 16);
            // Immutable record: no updated_at (ADR-0003).
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('case_evidence'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
