<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\UlidPostFactory;

/**
 * Workbench fixture: a ULID-keyed subject model for package tests
 * (testing strategy §2 — validates ADR-0010's universal morph columns).
 */
class UlidPost extends Model
{
    /** @use HasFactory<UlidPostFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'ulid_posts';

    protected $guarded = [];

    protected static function newFactory(): UlidPostFactory
    {
        return new UlidPostFactory;
    }
}
