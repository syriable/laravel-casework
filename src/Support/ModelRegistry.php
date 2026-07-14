<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;

/**
 * Model override resolution (X1, FR-901): all package code resolves model
 * classes through this registry so applications can substitute subclasses
 * via config('casework.models').
 */
final class ModelRegistry
{
    private const array DEFAULTS = [
        'report' => Report::class,
        'reason' => Reason::class,
        'case' => CaseFile::class,
        'note' => Note::class,
        'evidence' => Evidence::class,
        'decision' => Decision::class,
        'restriction' => Restriction::class,
        'warning' => Warning::class,
        'appeal' => Appeal::class,
        'audit_entry' => AuditEntry::class,
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
