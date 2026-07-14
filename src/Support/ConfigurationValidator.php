<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Illuminate\Support\Arr;
use Syriable\Casework\Contracts\Notifier;
use Syriable\Casework\Exceptions\InvalidConfiguration;

/**
 * Boot-time validation of config/casework.php (ADR-0016): every class name
 * and enum-like value fails fast with a descriptive exception at boot,
 * never at runtime.
 */
final class ConfigurationValidator
{
    private const array MODEL_KEYS = [
        'report', 'reason', 'case', 'note', 'evidence',
        'decision', 'restriction', 'warning', 'appeal', 'audit_entry',
    ];

    private const array CASE_STRATEGIES = ['always', 'threshold', 'manual'];

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidConfiguration
     */
    public function validate(array $config): void
    {
        $this->validateTablePrefix($config);
        $this->validateModels($config);
        $this->validateBoolean($config, 'reporting.allow_duplicates');
        $this->validateBoolean($config, 'reporting.allow_anonymous');
        $this->validateCases($config);
        $this->validateOpenSet($config, 'decisions.outcomes', Outcome::shipped());
        $this->validateOpenSet($config, 'enforcement.restriction_types', RestrictionType::shipped());
        $this->validateAppeals($config);
        $this->validateBoolean($config, 'authorization.prevent_self_moderation');
        $this->validateNotifiers($config);
        $this->validateClassList($config, 'pipelines.intake');
        $this->validateClassList($config, 'pipelines.triage');
        $this->validateNullableDays($config, 'audit.prune_after_days');
    }

    /** @param array<string, mixed> $config */
    private function validateTablePrefix(array $config): void
    {
        $prefix = Arr::get($config, 'table_prefix');

        if (! is_string($prefix) || preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1) {
            throw InvalidConfiguration::forKey(
                'table_prefix',
                'must be a string containing only letters, digits, and underscores',
            );
        }
    }

    /** @param array<string, mixed> $config */
    private function validateModels(array $config): void
    {
        $models = Arr::get($config, 'models');

        if (! is_array($models)) {
            throw InvalidConfiguration::forKey('models', 'must be an array');
        }

        foreach (self::MODEL_KEYS as $key) {
            if (! array_key_exists($key, $models)) {
                throw InvalidConfiguration::forKey("models.{$key}", 'is missing');
            }
        }

        foreach ($models as $key => $class) {
            if (! in_array($key, self::MODEL_KEYS, true)) {
                throw InvalidConfiguration::forKey("models.{$key}", 'is not a known model key');
            }

            if (! is_string($class) || $class === '') {
                throw InvalidConfiguration::forKey("models.{$key}", 'must be a class name string');
            }

            // Shipped defaults are trusted as-is; overrides must exist and
            // subclass the shipped model (X1). The subclass rule is enforced
            // here only when the shipped class itself exists (models land in
            // milestone M2 — until then only existence is checkable).
            $default = ModelRegistry::default($key);

            if ($class === $default) {
                continue;
            }

            if (! class_exists($class)) {
                throw InvalidConfiguration::forKey("models.{$key}", "class {$class} does not exist");
            }

            if (class_exists($default) && ! is_subclass_of($class, $default)) {
                throw InvalidConfiguration::forKey(
                    "models.{$key}",
                    "{$class} must extend {$default}",
                );
            }
        }
    }

    /** @param array<string, mixed> $config */
    private function validateCases(array $config): void
    {
        $strategy = Arr::get($config, 'cases.strategy');

        $isNamed = is_string($strategy) && in_array($strategy, self::CASE_STRATEGIES, true);
        $isClass = is_string($strategy) && str_contains($strategy, '\\') && class_exists($strategy);

        if (! $isNamed && ! $isClass) {
            throw InvalidConfiguration::forKey(
                'cases.strategy',
                "must be one of 'always', 'threshold', 'manual', or an existing class name",
            );
        }

        $threshold = Arr::get($config, 'cases.threshold');

        if (! is_int($threshold) || $threshold < 1) {
            throw InvalidConfiguration::forKey('cases.threshold', 'must be an integer >= 1');
        }

        $priorities = Arr::get($config, 'cases.priorities');

        if (! is_array($priorities) || $priorities === [] || ! array_is_list($priorities)) {
            throw InvalidConfiguration::forKey('cases.priorities', 'must be a non-empty list');
        }

        foreach ($priorities as $priority) {
            if (! is_string($priority) || $priority === '') {
                throw InvalidConfiguration::forKey('cases.priorities', 'entries must be non-empty strings');
            }
        }

        if (count($priorities) !== count(array_unique($priorities))) {
            throw InvalidConfiguration::forKey('cases.priorities', 'entries must be unique');
        }

        $default = Arr::get($config, 'cases.default_priority');

        if (! in_array($default, $priorities, true)) {
            throw InvalidConfiguration::forKey(
                'cases.default_priority',
                'must be one of cases.priorities',
            );
        }
    }

    /**
     * Open-set extension lists (outcomes, restriction types): strings only,
     * unique, and never colliding with shipped values.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $shipped
     */
    private function validateOpenSet(array $config, string $key, array $shipped): void
    {
        $values = Arr::get($config, $key);

        if (! is_array($values) || ! array_is_list($values)) {
            throw InvalidConfiguration::forKey($key, 'must be a list');
        }

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw InvalidConfiguration::forKey($key, 'entries must be non-empty strings');
            }

            if (in_array($value, $shipped, true)) {
                throw InvalidConfiguration::forKey($key, "'{$value}' collides with a shipped value");
            }
        }

        if (count($values) !== count(array_unique($values))) {
            throw InvalidConfiguration::forKey($key, 'entries must be unique');
        }
    }

    /** @param array<string, mixed> $config */
    private function validateAppeals(array $config): void
    {
        $limit = Arr::get($config, 'appeals.limit_per_target');

        if (! is_int($limit) || $limit < 1) {
            throw InvalidConfiguration::forKey('appeals.limit_per_target', 'must be an integer >= 1');
        }

        $this->validateNullableDays($config, 'appeals.window_days');
        $this->validateBoolean($config, 'appeals.require_independent_reviewer');
    }

    /** @param array<string, mixed> $config */
    private function validateNotifiers(array $config): void
    {
        $notifiers = Arr::get($config, 'notifiers');

        if (! is_array($notifiers) || ! array_is_list($notifiers)) {
            throw InvalidConfiguration::forKey('notifiers', 'must be a list of class names');
        }

        foreach ($notifiers as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                $shown = is_string($class) ? $class : get_debug_type($class);

                throw InvalidConfiguration::forKey('notifiers', "class {$shown} does not exist");
            }

            if (! is_a($class, Notifier::class, true)) {
                throw InvalidConfiguration::forKey('notifiers', "{$class} must implement ".Notifier::class);
            }
        }
    }

    /**
     * Pipeline stage lists. Stage contracts arrive with their modules
     * (M5/M6); until then only existence is checkable.
     *
     * @param  array<string, mixed>  $config
     */
    private function validateClassList(array $config, string $key): void
    {
        $classes = Arr::get($config, $key);

        if (! is_array($classes) || ! array_is_list($classes)) {
            throw InvalidConfiguration::forKey($key, 'must be a list of class names');
        }

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                $shown = is_string($class) ? $class : get_debug_type($class);

                throw InvalidConfiguration::forKey($key, "class {$shown} does not exist");
            }
        }
    }

    /** @param array<string, mixed> $config */
    private function validateBoolean(array $config, string $key): void
    {
        if (! is_bool(Arr::get($config, $key))) {
            throw InvalidConfiguration::forKey($key, 'must be a boolean');
        }
    }

    /** @param array<string, mixed> $config */
    private function validateNullableDays(array $config, string $key): void
    {
        $value = Arr::get($config, $key);

        if ($value !== null && (! is_int($value) || $value < 1)) {
            throw InvalidConfiguration::forKey($key, 'must be null or an integer >= 1');
        }
    }
}
