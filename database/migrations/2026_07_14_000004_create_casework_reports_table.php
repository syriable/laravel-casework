<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('reports'), function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->string('subject_id', 36);
            $table->string('reporter_type')->nullable();
            $table->string('reporter_id', 36)->nullable();
            $table->string('origin', 16);
            $table->foreignId('reason_id')->constrained($this->table('reasons'))->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->string('state', 32)->index();
            $table->foreignId('case_id')->nullable()->constrained($this->table('cases'))->restrictOnDelete();
            $table->foreignId('decision_id')->nullable()->constrained($this->table('decisions'))->restrictOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'state']);
            $table->index(['reporter_type', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('reports'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
