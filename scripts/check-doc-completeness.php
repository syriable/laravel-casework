<?php

declare(strict_types=1);

/*
 * Doc-completeness check (documentation plan §3, milestone M10).
 *
 * Every public class under src/ and every public method on the facade
 * root must be mentioned by name in at least one end-user guide file
 * (docs/guide/*.md) or the README. Mechanical enforcement of the M4
 * "every Phase 5 surface has a home" map.
 *
 * Usage: php scripts/check-doc-completeness.php
 */

$root = dirname(__DIR__);

$guides = glob("{$root}/docs/guide/*.md") ?: [];
$guides[] = "{$root}/README.md";

$haystack = '';

foreach ($guides as $guide) {
    $haystack .= file_get_contents($guide)."\n";
}

// Internal machinery documented at the architecture level, not in
// end-user guides. Everything else public must appear by name.
$exempt = [
    'CaseworkServiceProvider',      // Laravel wiring, not API
    'NotifierDispatcher',           // internal bridge for the notifier loop
    'RunTriagePipeline',            // internal listener
    'Recorder',                     // not swappable by design (I-04)
    'TransitionContext',            // guard-internal surface (architecture docs)
    'TransitionDefinition',         // covered via extending.md snippets by name
    'ModelRegistry',                // internal resolution
    'HasPrefixedTable',             // internal concern
    'PreventsMutation',             // internal concern
    'GuardsStateColumn',            // internal concern
    'AuthorizesActions',            // internal concern
    'GuardsReviewerIndependence',   // internal concern
    'PruneAuditCommand',            // documented by artisan signature (checked below)
    'ExpireRestrictionsCommand',    // documented by artisan signature (checked below)
];

$missing = [];

// Commands are documented by their artisan signatures, not class names.
foreach (['casework:prune-audit', 'casework:expire-restrictions'] as $signature) {
    if (! str_contains($haystack, $signature)) {
        $missing[] = $signature;
    }
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/src"));

foreach ($files as $file) {
    if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }

    $name = $file->getBasename('.php');

    // Concrete actions are replaceable-but-not-stable internals
    // (extension guarantee #3): their behavior is documented through
    // the facade operations, not by class name.
    if (str_contains($file->getPathname(), '/Actions/')) {
        continue;
    }

    if (in_array($name, $exempt, true)) {
        continue;
    }

    if (! str_contains($haystack, $name)) {
        $missing[] = $name;
    }
}

// The facade root: every public method must appear in a guide.
$facade = new ReflectionClass(requireClass($root, 'src/Casework.php', 'Syriable\\Casework\\Casework'));

foreach ($facade->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if (! str_contains($haystack, $method->getName())) {
        $missing[] = "Casework::{$method->getName()}()";
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Public surface missing from docs/guide/ (or README):\n");

    foreach ($missing as $name) {
        fwrite(STDERR, "  - {$name}\n");
    }

    exit(1);
}

echo "Doc completeness: every public class and facade method is documented.\n";
exit(0);

/**
 * @return class-string
 */
function requireClass(string $root, string $path, string $class): string
{
    if (! class_exists($class)) {
        require_once "{$root}/vendor/autoload.php";
    }

    /** @var class-string */
    return $class;
}
