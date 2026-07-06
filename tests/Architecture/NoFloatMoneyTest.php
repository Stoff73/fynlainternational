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
            // R-9f: AssetLocationController relocated with private
            // calculateIncomeTaxRate(float $income) helper used to derive
            // a marginal rate for asset-location placement scoring. Int-minor
            // refactor in R-14a.
            'packs/country-gb/src/Http/Controllers/Investment/AssetLocationController.php:calculateIncomeTaxRate',
            // R-17 batch 1: MonteCarloEngine lifted from app/Services/Shared
            // into core (jurisdiction-neutral simulation base extended by the
            // pack's MonteCarloSimulator). Its float signatures are simulation
            // values, pre-existing ADR-005 int-minor debt carried with the
            // move — not new float-money code. Int-minor refactor pending.
            'core/app/Core/Services/MonteCarloEngine.php:applyScheduledInjection',
            'core/app/Core/Services/MonteCarloEngine.php:calculateGoalProbability',
            // R-17 batch 2: AssumptionsService relocated from app/Services/Settings
            // with its pre-existing float OCF helper. Carried ADR-005 debt, not
            // new float-money code. Int-minor refactor pending.
            'packs/country-gb/src/Settings/AssumptionsService.php:calculateHoldingsWeightedOcf',
            // R-17 batch 3: the 19 Investment services relocated wholesale from
            // app/Services/Investment — the R-14a float-money deferral set
            // (51 pre-existing float signatures). File-level "<path>:*" pins
            // carry that debt with the move; do NOT add new files here without
            // an ADR-005 justification. Int-minor refactor closes these.
            'packs/country-gb/src/Investment/AssetLocation/AssetLocationOptimizer.php:*',
            'packs/country-gb/src/Investment/ContributionOptimizer.php:*',
            'packs/country-gb/src/Investment/DividendTaxCalculator.php:*',
            'packs/country-gb/src/Investment/FeeAnalyzer.php:*',
            'packs/country-gb/src/Investment/Fees/OCFImpactCalculator.php:*',
            'packs/country-gb/src/Investment/Fees/PlatformComparator.php:*',
            'packs/country-gb/src/Investment/Goals/GoalProbabilityCalculator.php:*',
            'packs/country-gb/src/Investment/Goals/GoalProgressAnalyzer.php:*',
            'packs/country-gb/src/Investment/Goals/ShortfallAnalyzer.php:*',
            'packs/country-gb/src/Investment/InvestmentProjectionService.php:*',
            'packs/country-gb/src/Investment/ModelPortfolio/AssetAllocationOptimizer.php:*',
            'packs/country-gb/src/Investment/Performance/PerformanceAttributionAnalyzer.php:*',
            'packs/country-gb/src/Investment/PortfolioAnalyzer.php:*',
            'packs/country-gb/src/Investment/Recommendation/LifeEventAssessmentService.php:*',
            'packs/country-gb/src/Investment/Recommendation/UserContextBuilder.php:*',
            'packs/country-gb/src/Investment/Tax/BedAndISACalculator.php:*',
            'packs/country-gb/src/Investment/Tax/ISAAllowanceOptimizer.php:*',
            'packs/country-gb/src/Investment/Tax/TaxOptimizationAnalyzer.php:*',
            'packs/country-gb/src/Investment/TaxEfficiencyCalculator.php:*',
            // R-17 batch 4: the 8 Retirement services relocated wholesale from
            // app/Services/Retirement — R-14a float-money deferral set. Same
            // carried-debt file-level pins as batch 3. Int-minor refactor closes.
            'packs/country-gb/src/Retirement/AnnualAllowanceChecker.php:*',
            'packs/country-gb/src/Retirement/DecumulationPlanner.php:*',
            'packs/country-gb/src/Retirement/PensionContributionOptimizer.php:*',
            'packs/country-gb/src/Retirement/PensionProjector.php:*',
            'packs/country-gb/src/Retirement/RetirementIncomeService.php:*',
            'packs/country-gb/src/Retirement/RetirementProjectionService.php:*',
            'packs/country-gb/src/Retirement/RetirementStrategyService.php:*',
            'packs/country-gb/src/Retirement/SalarySacrificeAnalyzer.php:*',
        ];

        $violations = [];
        $moneyPattern = '/(amount|balance|value|price|cost|salary|income|premium|fee|payment|contribution|benefit|liability|asset|total|net|gross|tax_amount)/i';

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
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
                    if (preg_match('/function\s+(\w+)\s*\([^)]*float\s+\$\w*('.'amount|balance|value|price|cost|salary|income|premium|fee|payment'.')/i', $line, $m)) {
                        $relPath = str_replace(base_path().'/', '', $path);
                        $methodName = $m[1] ?? '';
                        $key = "{$relPath}:{$methodName}";
                        if (in_array($key, $allowed, true) || in_array("{$relPath}:*", $allowed, true)) {
                            continue;
                        }
                        $violations[] = "{$relPath}:".($lineNum + 1).": {$trimmed}";
                    }
                }
            }
        }

        expect($violations)->toBeEmpty(
            "Float type hints found on money-named parameters in new code:\n".implode("\n", $violations)
        );
    });
});
