<?php

declare(strict_types=1);
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Reporting\Reputation\DefaultReputationPolicy;

// Laravel Trust & Safety configuration. Every key is boot-validated:
// invalid values throw Syriable\Casework\Exceptions\InvalidConfiguration
// with the offending key. Full reference: docs/guide/configuration.md.
return [

    /*
     * Table prefix for all package tables. Applied at migration publish
     * and at runtime model resolution.
     */
    'table_prefix' => 'casework_',

    /*
     * Model overrides (X1, FR-901). Values must subclass the shipped
     * model. All package relations resolve through this map.
     */
    'models' => [
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
        'reporter_reputation' => ReporterReputation::class,
    ],

    'reporting' => [
        /*
         * Reject a report when the same reporter already has an open
         * report on the same subject for the same reason.
         */
        'allow_duplicates' => false,

        /*
         * Permit reports with origin "anonymous".
         */
        'allow_anonymous' => true,

        /*
         * Reporter reputation and rate limiting (extension point X14).
         * Off by default — enabling it does not by itself block or
         * limit anyone; see block_threshold and rate_limit below.
         */
        'reputation' => [
            /*
             * When true, a reporter's score adjusts whenever one of
             * their reports is dismissed or resolved by a decision.
             */
            'enabled' => false,

            /*
             * Score delta applied when a report is dismissed (found
             * unfounded) — used by the shipped DefaultReputationPolicy.
             */
            'dismissed_delta' => -1,

            /*
             * Score delta applied when a report is resolved by an
             * upheld or escalated decision.
             */
            'upheld_delta' => 1,

            /*
             * A reporter at or below this score cannot file new
             * reports; null = tracking only, nobody is ever blocked.
             */
            'block_threshold' => null,

            /*
             * Reports permitted per reporter within
             * rate_limit_window_minutes; null = no rate limiting.
             */
            'rate_limit' => null,
            'rate_limit_window_minutes' => 60,

            /*
             * The class deciding score deltas. Must implement
             * Contracts\ReputationPolicy.
             */
            'policy' => DefaultReputationPolicy::class,
        ],
    ],

    'cases' => [
        /*
         * When reports become or join cases:
         * 'always' | 'threshold' | 'manual' | a CaseStrategy class name.
         */
        'strategy' => 'threshold',

        /*
         * Open reports per subject that trigger a case when the
         * strategy is 'threshold'.
         */
        'threshold' => 3,

        /*
         * Ordered priority vocabulary and the default for new cases.
         */
        'priorities' => ['low', 'normal', 'high', 'urgent'],
        'default_priority' => 'normal',
    ],

    'decisions' => [
        /*
         * Additional outcome keys beyond dismiss / uphold / escalate.
         */
        'outcomes' => [],
    ],

    'enforcement' => [
        /*
         * Additional restriction types beyond 'suspension'.
         */
        'restriction_types' => [],
    ],

    'appeals' => [
        /*
         * Appeals permitted per decision/restriction.
         */
        'limit_per_target' => 1,

        /*
         * Days after the decision/restriction during which an appeal may
         * be submitted; null = no window.
         */
        'window_days' => 30,

        /*
         * The appeal reviewer must differ from the original decider or
         * issuer.
         */
        'require_independent_reviewer' => true,
    ],

    'authorization' => [
        /*
         * Actors cannot decide cases or review appeals concerning
         * themselves.
         */
        'prevent_self_moderation' => true,
    ],

    /*
     * Notifier classes invoked, in order, for every domain event after
     * its transaction commits. Must implement Contracts\Notifier.
     */
    'notifiers' => [],

    'pipelines' => [
        /*
         * ReportIntakeStage classes, in order.
         */
        'intake' => [],

        /*
         * CaseTriageStage classes, in order.
         */
        'triage' => [],
    ],

    'audit' => [
        /*
         * Retention in days for casework:prune-audit; null means the
         * command refuses to run — pruning is opt-in.
         */
        'prune_after_days' => null,
    ],
];
