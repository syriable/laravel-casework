<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('appeals'), function (Blueprint $table): void {
            $table->id();
            // Targets a Decision or a Restriction (FR-501).
            $table->string('appealed_type');
            $table->string('appealed_id', 36);
            $table->string('appellant_type')->nullable();
            $table->string('appellant_id', 36)->nullable();
            $table->string('origin', 16);
            $table->text('statement')->nullable();
            $table->string('state', 32)->index();
            $table->string('reviewer_type')->nullable();
            $table->string('reviewer_id', 36)->nullable();
            $table->foreignId('resulting_decision_id')->nullable()->constrained($this->table('decisions'))->restrictOnDelete();
            $table->timestamps();

            $table->index(['appealed_type', 'appealed_id'], $this->table('appeals').'_target_idx');
            $table->index(['reviewer_type', 'reviewer_id', 'state'], $this->table('appeals').'_reviewer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('appeals'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
