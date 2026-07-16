<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('reporter_reputations'), function (Blueprint $table): void {
            $table->id();
            $table->string('reporter_type');
            $table->string('reporter_id', 36);
            $table->integer('score')->default(0);
            $table->timestamps();

            // One reputation row per reporter; also the hot-path lookup
            // FileReport's guard uses before persisting a new report.
            $table->unique(['reporter_type', 'reporter_id'], $this->table('reporter_reputations').'_reporter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('reporter_reputations'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('casework.table_prefix', 'casework_');

        return (is_string($prefix) ? $prefix : 'casework_').$suffix;
    }
};
