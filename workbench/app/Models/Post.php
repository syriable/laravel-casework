<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Workbench fixture: a bigint-keyed subject model for package tests
 * (testing strategy §2 — validates ADR-0010's universal morph columns).
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $guarded = [];
}
