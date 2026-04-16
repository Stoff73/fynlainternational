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
                    if (preg_match('/function\s+\w+\s*\([^)]*float\s+\$\w*(' . 'amount|balance|value|price|cost|salary|income|premium|fee|payment' . ')/i', $line)) {
                        $relPath = str_replace(base_path() . '/', '', $path);
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
