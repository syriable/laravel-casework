<?php

declare(strict_types=1);

namespace Syriable\Casework;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Commands\PruneAuditCommand;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\WorkflowDefinition;
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
            ->hasConfigFile()
            ->hasCommand(PruneAuditCommand::class);
    }

    /** @var list<class-string<WorkflowDefinition>> */
    private const array WORKFLOWS = [
        ReportWorkflow::class,
        CaseWorkflow::class,
        RestrictionWorkflow::class,
        AppealWorkflow::class,
    ];

    public function packageRegistered(): void
    {
        $this->app->singleton(ScopeResolver::class, NullScopeResolver::class);

        // Applications extend a lifecycle by rebinding its definition to a
        // subclass (ADR-0013); validation below runs on whatever is bound.
        foreach (self::WORKFLOWS as $workflow) {
            $this->app->singleton($workflow);
        }
    }

    public function packageBooted(): void
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('casework');

        (new ConfigurationValidator)->validate($config);

        foreach (self::WORKFLOWS as $workflow) {
            $this->app->make($workflow)->validate();
        }

        // Migrations read the table prefix from config at run time, so the
        // published copies honor the application's prefix (FR-954).
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'casework-migrations');
    }
}
