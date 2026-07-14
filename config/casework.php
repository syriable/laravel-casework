<?php

declare(strict_types=1);

// Laravel Trust & Safety configuration. Every key is boot-validated:
// invalid values throw Syriable\Casework\Exceptions\InvalidConfiguration
// with the offending key. Full reference: docs/configuration.md.
return [

    /*
     * Table prefix for all package tables. Applied at migration publish
     * and at runtime model resolution. (FR-952, FR-954)
     */
    'table_prefix' => 'casework_',

    /*
     * Model overrides (X1, FR-901). Values must subclass the shipped
     * model. All package relations resolve through this map.
     */
    'models' => [
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
    ],

    'reporting' => [
        /*
         * Reject a report when the same reporter already has an open
         * report on the same subject for the same reason. (FR-105)
         */
        'allow_duplicates' => false,

        /*
         * Permit reports with origin "anonymous". (FR-103)
         */
        'allow_anonymous' => true,
    ],

    'cases' => [
        /*
         * When reports become or join cases (FR-205):
         * 'always' | 'threshold' | 'manual' | a CaseStrategy class name.
         */
        'strategy' => 'threshold',

        /*
         * Open reports per subject that trigger a case when the
         * strategy is 'threshold'.
         */
        'threshold' => 3,

        /*
         * Ordered priority vocabulary and the default for new cases. (FR-204)
         */
        'priorities' => ['low', 'normal', 'high', 'urgent'],
        'default_priority' => 'normal',
    ],

    'decisions' => [
        /*
         * Additional outcome keys beyond dismiss / uphold / escalate. (FR-302)
         */
        'outcomes' => [],
    ],

    'enforcement' => [
        /*
         * Additional restriction types beyond 'suspension'. (FR-402, FR-407)
         */
        'restriction_types' => [],
    ],

    'appeals' => [
        /*
         * Appeals permitted per decision/restriction. (FR-503)
         */
        'limit_per_target' => 1,

        /*
         * Days after the decision/restriction during which an appeal may
         * be submitted; null = no window. (FR-506)
         */
        'window_days' => 30,

        /*
         * The appeal reviewer must differ from the original decider or
         * issuer. (FR-505)
         */
        'require_independent_reviewer' => true,
    ],

    'authorization' => [
        /*
         * Actors cannot decide cases or review appeals concerning
         * themselves. (FR-604)
         */
        'prevent_self_moderation' => true,
    ],

    /*
     * Notifier classes invoked, in order, for every domain event after
     * its transaction commits. Must implement Contracts\Notifier. (FR-803)
     */
    'notifiers' => [],

    'pipelines' => [
        /*
         * ReportIntakeStage classes, in order. (FR-804)
         */
        'intake' => [],

        /*
         * CaseTriageStage classes, in order. (FR-804)
         */
        'triage' => [],
    ],

    'audit' => [
        /*
         * Retention in days for casework:prune-audit; null means the
         * command refuses to run — pruning is opt-in. (FR-705)
         */
        'prune_after_days' => null,
    ],
];
