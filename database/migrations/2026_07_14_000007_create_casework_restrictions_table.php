<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('restrictions'), function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->string('subject_id', 36);
            $table->string('type', 64);
            $table->string('scope')->nullable();
            $table->string('issuer_type')->nullable();
            $table->string('issuer_id', 36)->nullable();
            $table->string('origin', 16);
            $table->foreignId('decision_id')->nullable()->constrained($this->table('decisions'))->restrictOnDelete();
            $table->string('state', 32);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->string('lifted_by_type')->nullable();
            $table->string('lifted_by_id', 36)->nullable();
            $table->text('lift_reason')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained($this->table('restrictions'))->restrictOnDelete();
            $table->text('rationale')->nullable();
            $table->timestamps();

            // The FR-405 hot path: subject + type + state + expiry in one index.
            $table->index(['subject_type', 'subject_id', 'type', 'state', 'expires_at'], $this->table('restrictions').'_hot_path');
            $table->index(['state', 'expires_at'], $this->table('restrictions').'_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('restrictions'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
