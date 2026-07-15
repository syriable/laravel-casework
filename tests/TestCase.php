<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\Casework\CaseworkServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            if (str_starts_with($modelName, 'Workbench\\')) {
                return 'Workbench\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }

            return 'Syriable\\Casework\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            CaseworkServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // The integration workflow overrides DB_CONNECTION to run the same
        // suite against MySQL / PostgreSQL / MariaDB (testing strategy §5).
        config()->set('database.default', env('DB_CONNECTION', 'testing'));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
