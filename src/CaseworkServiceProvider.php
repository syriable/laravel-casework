<?php

declare(strict_types=1);

namespace Syriable\Casework;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Support\ConfigurationValidator;
use Syriable\Casework\Support\NullScopeResolver;

/**
 * The package's only Laravel bootstrapping point (architecture §3).
 * Registers no routes, views, broadcast channels, or scheduled tasks —
 * the package is UI-agnostic by design (NFR-01).
 */
final class CaseworkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('casework')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ScopeResolver::class, NullScopeResolver::class);
    }

    public function packageBooted(): void
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('casework');

        (new ConfigurationValidator)->validate($config);
    }
}
