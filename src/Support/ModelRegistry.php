<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Syriable\Casework\Exceptions\InvalidConfiguration;

/**
 * Model override resolution (X1, FR-901): all package code resolves model
 * classes through this registry so applications can substitute subclasses
 * via config('casework.models').
 */
final class ModelRegistry
{
    private const array DEFAULTS = [
        'report' => \Syriable\Casework\Reporting\Models\Report::class,
        'reason' => \Syriable\Casework\Reporting\Models\Reason::class,
        'case' => \Syriable\Casework\Cases\Models\CaseFile::class,
        'note' => \Syriable\Casework\Cases\Models\Note::class,
        'evidence' => \Syriable\Casework\Cases\Models\Evidence::class,
        'decision' => \Syriable\Casework\Cases\Models\Decision::class,
        'restriction' => \Syriable\Casework\Enforcement\Models\Restriction::class,
        'warning' => \Syriable\Casework\Enforcement\Models\Warning::class,
        'appeal' => \Syriable\Casework\Appeals\Models\Appeal::class,
        'audit_entry' => \Syriable\Casework\Audit\Models\AuditEntry::class,
    ];

    /**
     * The configured model class for a registry key.
     *
     * @return class-string
     */
    public static function classFor(string $key): string
    {
        $default = self::default($key);

        $configured = config("casework.models.{$key}", $default);

        return is_string($configured) && $configured !== '' ? $configured : $default;
    }

    /**
     * The shipped default class for a registry key.
     *
     * @return class-string
     */
    public static function default(string $key): string
    {
        return self::DEFAULTS[$key]
            ?? throw InvalidConfiguration::forKey("models.{$key}", 'is not a known model key');
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }
}
