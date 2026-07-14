<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Syriable\Casework\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in(__DIR__.'/Feature');
