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

    it('country-za does not import the App namespace', function () {
        $packDir = base_path('packs/country-za/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-za/src directory not found');
        }

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            // Look for `use App\`, `App\\...::class`, or a leading backslash App\ reference.
            if (preg_match('/(?:^|[\s(;])(use\s+)?\\\\?App\\\\/m', $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'ZA pack must not import any App\\ namespace. Violations: ' . implode(', ', $violations)
        );
    });

    it('country-za does not reference other pack namespaces', function () {
        $packDir = base_path('packs/country-za/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-za/src directory not found');
        }

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            // Any Fynla\Packs\ reference that isn't the Za namespace is a leak.
            if (preg_match('/Fynla\\\\Packs\\\\(?!Za\\\\)/', $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'ZA pack must not reference other pack namespaces. Violations: ' . implode(', ', $violations)
        );
    });

    it('core/ does not contain SA-specific logic (outside docblocks)', function () {
        $coreDir = base_path('core/app/Core');

        if (!is_dir($coreDir)) {
            $this->markTestSkipped('core/app/Core directory not found');
        }

        // PRD § 8 intent: no SA-specific HARDCODED logic in core. Documentation
        // mentions of SARS / Section 11F as example context are acceptable —
        // they help future pack authors understand the contract. Actual
        // forbidden patterns: hardcoded SA amounts (R99,000, R40,000 exclusion,
        // etc.) and direct ZA rate constants appearing in executable statements.
        $forbiddenInCode = ['R99,000', 'R40,000', 'R3.5m', 'R350,000'];
        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($coreDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;

            // Strip PHPDoc blocks and // line comments before checking.
            $contents = file_get_contents($file->getPathname());
            $codeOnly = preg_replace('#/\*.*?\*/#s', '', $contents) ?? $contents;
            $codeOnly = preg_replace('#//[^\n]*#', '', $codeOnly) ?? $codeOnly;

            foreach ($forbiddenInCode as $literal) {
                if (str_contains($codeOnly, $literal)) {
                    $violations[] = str_replace(base_path() . '/', '', $file->getPathname()) . ' contains code literal "' . $literal . '"';
                }
            }
        }

        expect($violations)->toBeEmpty(
            'core/ must not hardcode SA-specific values. Violations: ' . implode('; ', $violations)
        );
    });

    it('ZaTaxEngine implements the core TaxEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Tax\ZaTaxEngine::class)) {
            $this->markTestSkipped('ZaTaxEngine not loaded');
        }

        expect(class_implements(\Fynla\Packs\Za\Tax\ZaTaxEngine::class))
            ->toContain(\Fynla\Core\Contracts\TaxEngine::class);
    });

    it('UkSavingsEngine implements the core SavingsEngine contract', function () {
        expect(class_implements(\App\Services\Savings\UkSavingsEngine::class))
            ->toContain(\Fynla\Core\Contracts\SavingsEngine::class);
    });

    it('ZaSavingsEngine implements the core SavingsEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Savings\ZaSavingsEngine::class)) {
            $this->markTestSkipped('ZaSavingsEngine not yet loaded (WS 1.2a in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Savings\ZaSavingsEngine::class))
            ->toContain(\Fynla\Core\Contracts\SavingsEngine::class);
    });
});
