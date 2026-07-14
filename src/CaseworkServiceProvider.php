<?php

namespace Syriable\Casework;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Casework\Commands\CaseworkCommand;

class CaseworkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-casework')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_casework_table')
            ->hasCommand(CaseworkCommand::class);
    }
}
