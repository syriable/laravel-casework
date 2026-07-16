<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Concerns\HasReporterReputation;
use Syriable\Casework\Concerns\InteractsWithReports;
use Syriable\Casework\Concerns\InteractsWithRestrictions;
use Syriable\Casework\Contracts\Reportable;
use Syriable\Casework\Contracts\Restrictable;
use Workbench\Database\Factories\PostFactory;

/**
 * Workbench fixture: a bigint-keyed subject model for package tests
 * (validates ADR-0010's universal morph columns). Also stands in as a
 * reporter in reputation tests — the package has no distinct "User"
 * concept; any model can act in any polymorphic role.
 */
class Post extends Model implements Reportable, Restrictable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasReporterReputation;
    use InteractsWithReports;
    use InteractsWithRestrictions;

    protected $table = 'posts';

    protected $guarded = [];

    protected static function newFactory(): PostFactory
    {
        return new PostFactory;
    }
}
