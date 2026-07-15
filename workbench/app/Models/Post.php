<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Concerns\InteractsWithReports;
use Syriable\Casework\Concerns\InteractsWithRestrictions;
use Syriable\Casework\Contracts\Reportable;
use Syriable\Casework\Contracts\Restrictable;
use Workbench\Database\Factories\PostFactory;

/**
 * Workbench fixture: a bigint-keyed subject model for package tests
 * (testing strategy §2 — validates ADR-0010's universal morph columns).
 */
class Post extends Model implements Reportable, Restrictable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use InteractsWithReports;
    use InteractsWithRestrictions;

    protected $table = 'posts';

    protected $guarded = [];

    protected static function newFactory(): PostFactory
    {
        return new PostFactory;
    }
}
