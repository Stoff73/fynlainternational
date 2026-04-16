<?php

declare(strict_types=1);

describe('No Hardcoded Legal Copy', function () {
    it('core files do not contain jurisdiction-specific regulatory terms', function () {
        $coreDir = base_path('core/app/Core');

        if (!is_dir($coreDir)) {
            $this->markTestSkipped('core/app/Core directory not found');
        }

        // Regulatory terms that should only appear in pack code
        $bannedTerms = [
            'FCA',           // UK Financial Conduct Authority
            'FSCA',          // SA Financial Sector Conduct Authority
            'HMRC',          // UK tax authority
            'SARS',          // SA tax authority
            'FAIS',          // SA Financial Advisory and Intermediary Services Act
            'ISA',           // UK Individual Savings Account
            'SIPP',          // UK Self-Invested Personal Pension
            'TFSA',          // SA Tax-Free Savings Account
        ];

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($coreDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());
            $relPath = str_replace(base_path() . '/', '', $file->getPathname());

            foreach ($bannedTerms as $term) {
                // Match as whole word (not part of a larger word)
                if (preg_match('/\b' . preg_quote($term, '/') . '\b/', $contents)) {
                    $violations[] = "{$relPath}: contains '{$term}'";
                }
            }
        }

        expect($violations)->toBeEmpty(
            "Core files contain jurisdiction-specific terms that belong in packs:\n" . implode("\n", $violations)
        );
    });
});
