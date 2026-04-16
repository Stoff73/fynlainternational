<?php

declare(strict_types=1);

describe('Core Independence', function () {
    it('core files do not reference any pack namespace', function () {
        $coreDir = base_path('core/app/Core');

        if (!is_dir($coreDir)) {
            $this->markTestSkipped('core/app/Core directory not found');
        }

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($coreDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;

            $contents = file_get_contents($file->getPathname());

            if (preg_match('/Fynla\\\\Packs\\\\/', $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'Core files must not reference pack namespaces. Violations: ' . implode(', ', $violations)
        );
    });
});
