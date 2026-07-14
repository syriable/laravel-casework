<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('warnings'), function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->string('subject_id', 36);
            $table->string('issuer_type')->nullable();
            $table->string('issuer_id', 36)->nullable();
            $table->string('origin', 16);
            $table->foreignId('decision_id')->nullable()->constrained($this->table('decisions'))->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('warnings'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
