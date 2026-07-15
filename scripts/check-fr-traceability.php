<?php

declare(strict_types=1);

/*
 * FR-traceability check (roadmap review criterion; milestone M10).
 *
 * Every MUST/SHOULD functional requirement id declared in
 * docs/requirements.md must be referenced at least once in src/,
 * tests/, or docs/ outside the requirements file itself — proving the
 * requirement has an implementation/documentation anchor.
 *
 * Usage: php scripts/check-fr-traceability.php
 */

$root = dirname(__DIR__);

$requirements = file_get_contents("{$root}/docs/requirements.md");

if ($requirements === false) {
    fwrite(STDERR, "docs/requirements.md not found.\n");
    exit(1);
}

// Table rows like: | FR-101 | ... | M | ... | — trace M (MUST) and S (SHOULD).
preg_match_all('/^\|\s*(FR-\d+)\s*\|.*?\|\s*(M|S)\s*\|/m', $requirements, $matches);

$ids = array_values(array_unique($matches[1]));

if ($ids === []) {
    fwrite(STDERR, "No FR ids parsed from docs/requirements.md — check the table format.\n");
    exit(1);
}

$haystack = '';

$paths = ["{$root}/src", "{$root}/tests", "{$root}/config", "{$root}/database"];
$docs = glob("{$root}/docs/**/*.md") ?: [];
$topDocs = glob("{$root}/docs/*.md") ?: [];

foreach ([...$docs, ...$topDocs] as $doc) {
    if (! str_ends_with($doc, 'requirements.md')) {
        $haystack .= file_get_contents($doc)."\n";
    }
}

foreach ($paths as $path) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && in_array($file->getExtension(), ['php', 'md'], true)) {
            $haystack .= file_get_contents($file->getPathname())."\n";
        }
    }
}

$untraced = array_values(array_filter($ids, fn (string $id) => ! str_contains($haystack, $id)));

if ($untraced !== []) {
    fwrite(STDERR, "Requirements with no trace outside docs/requirements.md:\n");

    foreach ($untraced as $id) {
        fwrite(STDERR, "  - {$id}\n");
    }

    exit(1);
}

$count = count($ids);

echo "FR traceability: all {$count} MUST/SHOULD requirements are anchored.\n";
exit(0);
