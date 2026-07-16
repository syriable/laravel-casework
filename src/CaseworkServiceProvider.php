<?php

declare(strict_types=1);

namespace Syriable\Casework;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Events\CaseOpened;
use Syriable\Casework\Cases\Listeners\RunTriagePipeline;
use Syriable\Casework\Commands\ExpireRestrictionsCommand;
use Syriable\Casework\Commands\MakeReasonCommand;
use Syriable\Casework\Commands\PruneAuditCommand;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\Policies\AppealPolicy;
use Syriable\Casework\Policies\CasePolicy;
use Syriable\Casework\Policies\ReporterReputationPolicy;
use Syriable\Casework\Policies\ReportPolicy;
use Syriable\Casework\Policies\RestrictionPolicy;
use Syriable\Casework\Policies\WarningPolicy;
use Syriable\Casework\Reporting\Events\ReportDismissed;
use Syriable\Casework\Reporting\Events\ReportResolved;
use Syriable\Casework\Reporting\Listeners\AdjustReputationOnReportOutcome;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\WorkflowDefinition;
use Syriable\Casework\Support\ConfigurationValidator;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\NotifierDispatcher;
use Syriable\Casework\Support\NullScopeResolver;

/**
 * The package's only Laravel bootstrapping point (architecture §3).
 * Registers no routes, views, broadcast channels, or scheduled tasks —
 * the package is UI-agnostic by design.
 */
final class CaseworkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('casework')
            ->hasConfigFile()
            ->hasCommand(PruneAuditCommand::class)
            ->hasCommand(ExpireRestrictionsCommand::class)
            ->hasCommand(MakeReasonCommand::class);
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

        // Applications extend a lifecycle's transitions by rebinding its
        // definition to a subclass (ADR-0019); validation below runs on
        // whatever is bound.
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

        // Default policies register early; an application registering its
        // own policy later overrides them.
        Gate::policy(ModelRegistry::classFor('report'), ReportPolicy::class);
        Gate::policy(ModelRegistry::classFor('case'), CasePolicy::class);
        Gate::policy(ModelRegistry::classFor('restriction'), RestrictionPolicy::class);
        Gate::policy(ModelRegistry::classFor('warning'), WarningPolicy::class);
        Gate::policy(ModelRegistry::classFor('appeal'), AppealPolicy::class);
        Gate::policy(ModelRegistry::classFor('reporter_reputation'), ReporterReputationPolicy::class);

        // The notifier loop (X8): every package event, in listed order,
        // after commit. The triage pipeline (X10) runs when a case
        // opens; the reputation listener (X14) runs on every report
        // outcome but no-ops unless reputation tracking is enabled.
        Event::listen('Syriable\\Casework\\*', NotifierDispatcher::class);
        Event::listen(CaseOpened::class, RunTriagePipeline::class);
        Event::listen([ReportDismissed::class, ReportResolved::class], AdjustReputationOnReportOutcome::class);

        // Migrations read the table prefix from config at run time, so the
        // published copies honor the application's prefix.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'casework-migrations');
    }
}
