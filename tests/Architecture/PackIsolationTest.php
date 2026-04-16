<?php

declare(strict_types=1);

describe('Pack Isolation', function () {
    it('country-gb does not reference other pack namespaces', function () {
        $packDir = base_path('packs/country-gb/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-gb/src directory not found');
        }

        $violations = [];
        $otherPackPattern = '/Fynla\\\\Packs\\\\(?!GB\\\\)/';  // Match any pack except GB

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            if (preg_match($otherPackPattern, $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'GB pack must not reference other pack namespaces. Violations: ' . implode(', ', $violations)
        );
    });

    it('country-xx-smoke does not reference other pack namespaces', function () {
        $packDir = base_path('packs/country-xx-smoke/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-xx-smoke/src directory not found');
        }

        $violations = [];
        $otherPackPattern = '/Fynla\\\\Packs\\\\(?!XXSmoke\\\\)/';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            if (preg_match($otherPackPattern, $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'Smoke pack must not reference other pack namespaces. Violations: ' . implode(', ', $violations)
        );
    });
});
