<?php

declare(strict_types=1);

/**
 * Per-Pack Navigation Architecture Tests (R-12).
 *
 * Each country pack ships a `resources/js/navigation.js` returning its
 * sidebar manifest. Core's frontend must not host any per-jurisdiction
 * module catalogue (the previous `MODULES_BY_JURISDICTION` constant) —
 * navigation data lives only in the pack that owns it.
 */

$projectRoot = realpath(__DIR__.'/../../');

it('does not reference MODULES_BY_JURISDICTION anywhere in the frontend', function () use ($projectRoot) {
    $scanDirs = [
        $projectRoot.'/resources/js',
        $projectRoot.'/packs/country-gb/resources/js',
        $projectRoot.'/packs/country-za/resources/js',
    ];

    $violations = [];

    foreach ($scanDirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $ext = $file->getExtension();
            if (! in_array($ext, ['js', 'vue', 'ts'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (str_contains($content, 'MODULES_BY_JURISDICTION')) {
                $violations[] = str_replace($projectRoot.'/', '', $file->getPathname());
            }
        }
    }

    expect($violations)->toBe(
        [],
        'MODULES_BY_JURISDICTION is forbidden after R-12 — sidebar navigation '.
        'must come from each pack\'s navigation.js manifest. Found in: '.
        implode(', ', $violations)
    );
});

it('GB pack ships a navigation manifest', function () use ($projectRoot) {
    $manifest = $projectRoot.'/packs/country-gb/resources/js/navigation.js';
    expect(file_exists($manifest))->toBeTrue();

    $content = file_get_contents($manifest);
    expect($content)->toContain('export default function navigation');
});

it('ZA pack ships a navigation manifest', function () use ($projectRoot) {
    $manifest = $projectRoot.'/packs/country-za/resources/js/navigation.js';
    expect(file_exists($manifest))->toBeTrue();

    $content = file_get_contents($manifest);
    expect($content)->toContain('export default function navigation');
});
