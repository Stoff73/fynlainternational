<?php

declare(strict_types=1);

describe('No Float Money', function () {
    it('no new files use float for money-named variables', function () {
        // This test scans for obvious float usage on money columns
        // It's a heuristic — not exhaustive — but catches common violations

        $dirs = [
            base_path('core/app/Core'),
            base_path('packs'),
        ];

        // R-14a allow-list — float-money signatures pinned by ADR-005
        // int-minor money refactor. Each entry is "<relative_path>:<signature_substring>".
        // R-14a closes these; new entries must carry an R-14a comment.
        $allowed = [
            // R-8: RetirementAgent relocated with private buildLowerTargetScenario
            // helper that takes float $newTargetIncome (display + arithmetic with
            // other float-money values). Int-minor refactor in R-14a.
            'packs/country-gb/src/Agents/RetirementAgent.php:buildLowerTargetScenario',
        ];

        $violations = [];
        $moneyPattern = '/(amount|balance|value|price|cost|salary|income|premium|fee|payment|contribution|benefit|liability|asset|total|net|gross|tax_amount)/i';

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;
                $path = $file->getPathname();
                $contents = file_get_contents($path);
                $lines = explode("\n", $contents);

                foreach ($lines as $lineNum => $line) {
                    // Skip comments
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                        continue;
                    }

                    // Check for float type hints on money-like parameters
                    if (preg_match('/function\s+(\w+)\s*\([^)]*float\s+\$\w*(' . 'amount|balance|value|price|cost|salary|income|premium|fee|payment' . ')/i', $line, $m)) {
                        $relPath = str_replace(base_path() . '/', '', $path);
                        $methodName = $m[1] ?? '';
                        $key = "{$relPath}:{$methodName}";
                        if (in_array($key, $allowed, true)) {
                            continue;
                        }
                        $violations[] = "{$relPath}:" . ($lineNum + 1) . ": {$trimmed}";
                    }
                }
            }
        }

        expect($violations)->toBeEmpty(
            "Float type hints found on money-named parameters in new code:\n" . implode("\n", $violations)
        );
    });
});
