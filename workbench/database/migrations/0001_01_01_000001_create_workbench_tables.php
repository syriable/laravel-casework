<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only tables: one bigint-keyed and one ULID-keyed subject model
// (testing strategy §2, ADR-0010).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->default('Untitled');
            $table->timestamps();
        });

        Schema::create('ulid_posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title')->default('Untitled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('ulid_posts');
    }
};
