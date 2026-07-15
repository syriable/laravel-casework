<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Syriable\Casework\Contracts\Notifier;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Support\ConfigurationValidator;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Tests\Support\EscalatesTriageStage;
use Syriable\Casework\Tests\Support\TagsMetadataStage;

/**
 * Boot validation of every config key (FR-951/952, ADR-0016).
 *
 * @see docs/configuration.md
 */
function validConfig(): array
{
    /** @var array<string, mixed> */
    return require __DIR__.'/../../config/casework.php';
}

/** @param array<string, mixed> $overrides dot-keyed config overrides */
function validateConfigWith(array $overrides): void
{
    $config = validConfig();

    foreach ($overrides as $key => $value) {
        Arr::set($config, $key, $value);
    }

    (new ConfigurationValidator)->validate($config);
}

it('accepts the shipped defaults', function (): void {
    (new ConfigurationValidator)->validate(validConfig());
})->throwsNoExceptions();

it('rejects invalid values with the offending key', function (array $overrides, string $key): void {
    try {
        validateConfigWith($overrides);
    } catch (InvalidConfiguration $exception) {
        expect($exception->key)->toBe($key);

        return;
    }

    $this->fail("Expected InvalidConfiguration for [{$key}]");
})->with([
    'non-string prefix' => [['table_prefix' => 42], 'table_prefix'],
    'malformed prefix' => [['table_prefix' => 'bad-prefix!'], 'table_prefix'],
    'unknown model class' => [['models.report' => 'App\\Missing\\Report'], 'models.report'],
    'model not subclassing default' => [['models.reason' => Model::class], 'models.reason'],
    'non-bool duplicates' => [['reporting.allow_duplicates' => 'no'], 'reporting.allow_duplicates'],
    'non-bool anonymous' => [['reporting.allow_anonymous' => 1], 'reporting.allow_anonymous'],
    'unknown strategy' => [['cases.strategy' => 'sometimes'], 'cases.strategy'],
    'missing strategy class' => [['cases.strategy' => 'App\\Missing\\Strategy'], 'cases.strategy'],
    'zero threshold' => [['cases.threshold' => 0], 'cases.threshold'],
    'empty priorities' => [['cases.priorities' => []], 'cases.priorities'],
    'duplicate priorities' => [['cases.priorities' => ['low', 'low']], 'cases.priorities'],
    'default priority not in list' => [['cases.default_priority' => 'critical'], 'cases.default_priority'],
    'outcome colliding with shipped' => [['decisions.outcomes' => ['uphold']], 'decisions.outcomes'],
    'non-string outcome' => [['decisions.outcomes' => [1]], 'decisions.outcomes'],
    'restriction type colliding' => [['enforcement.restriction_types' => ['suspension']], 'enforcement.restriction_types'],
    'zero appeal limit' => [['appeals.limit_per_target' => 0], 'appeals.limit_per_target'],
    'negative appeal window' => [['appeals.window_days' => -1], 'appeals.window_days'],
    'non-bool independence' => [['appeals.require_independent_reviewer' => 'yes'], 'appeals.require_independent_reviewer'],
    'non-bool self-moderation' => [['authorization.prevent_self_moderation' => null], 'authorization.prevent_self_moderation'],
    'missing notifier class' => [['notifiers' => ['App\\Missing\\Notifier']], 'notifiers'],
    'non-notifier class' => [['notifiers' => [Model::class]], 'notifiers'],
    'missing intake stage' => [['pipelines.intake' => ['App\\Missing\\Stage']], 'pipelines.intake'],
    'missing triage stage' => [['pipelines.triage' => ['App\\Missing\\Stage']], 'pipelines.triage'],
    'non-implementing intake stage' => [['pipelines.intake' => [Model::class]], 'pipelines.intake'],
    'non-implementing triage stage' => [['pipelines.triage' => [Model::class]], 'pipelines.triage'],
    'triage stage in intake list' => [['pipelines.intake' => [EscalatesTriageStage::class]], 'pipelines.intake'],
    'intake stage in triage list' => [['pipelines.triage' => [TagsMetadataStage::class]], 'pipelines.triage'],
    'zero prune days' => [['audit.prune_after_days' => 0], 'audit.prune_after_days'],
    'non-array models' => [['models' => 'nope'], 'models'],
    'empty model class' => [['models.report' => ''], 'models.report'],
    'non-string notifier entry' => [['notifiers' => [42]], 'notifiers'],
    'non-list notifiers' => [['notifiers' => 'nope'], 'notifiers'],
    'non-list pipeline' => [['pipelines.intake' => 'nope'], 'pipelines.intake'],
    'non-string pipeline entry' => [['pipelines.triage' => [42]], 'pipelines.triage'],
]);

it('rejects a missing model key', function (): void {
    $config = validConfig();
    unset($config['models']['warning']);

    try {
        (new ConfigurationValidator)->validate($config);
    } catch (InvalidConfiguration $exception) {
        expect($exception->key)->toBe('models.warning');

        return;
    }

    $this->fail('Expected InvalidConfiguration for [models.warning]');
});

it('rejects an unknown model key', function (): void {
    try {
        validateConfigWith(['models.banhammer' => Model::class]);
    } catch (InvalidConfiguration $exception) {
        expect($exception->key)->toBe('models.banhammer');

        return;
    }

    $this->fail('Expected InvalidConfiguration for [models.banhammer]');
});

it('rejects an unknown registry key', function (): void {
    ModelRegistry::default('banhammer');
})->throws(InvalidConfiguration::class);

it('accepts a model override that subclasses the shipped model', function (): void {
    $subclass = new class extends Reason {};

    validateConfigWith(['models.reason' => $subclass::class]);
})->throwsNoExceptions();

it('accepts a valid notifier implementation', function (): void {
    $notifier = new class implements Notifier
    {
        public function notify(object $event): void {}
    };

    validateConfigWith(['notifiers' => [$notifier::class]]);
})->throwsNoExceptions();

it('accepts stage lists implementing their contracts', function (): void {
    validateConfigWith([
        'pipelines.intake' => [TagsMetadataStage::class],
        'pipelines.triage' => [EscalatesTriageStage::class],
    ]);
})->throwsNoExceptions();

it('accepts a null appeal window and custom open-set values', function (): void {
    validateConfigWith([
        'appeals.window_days' => null,
        'decisions.outcomes' => ['uphold_with_education'],
        'enforcement.restriction_types' => ['shadowban'],
    ]);
})->throwsNoExceptions();
