<?php

declare(strict_types=1);

namespace App\Services\Investment\Performance;

/**
 * Benchmark Comparator
 * Compares portfolio performance against standard market benchmarks
 *
 * Supported Benchmarks:
 * - FTSE All-Share (UK market)
 * - FTSE 100 (Large cap UK)
 * - S&P 500 (US market)
 * - MSCI World (Global developed)
 * - UK Gilts (UK government bonds)
 * - 60/40 Portfolio (60% equity, 40% bonds)
 */
class BenchmarkComparator
{
    public function __construct(
        private AlphaBetaCalculator $alphaBetaCalculator
    ) {}

    /**
     * Compare portfolio performance against benchmark
     *
     * @param  int  $userId  User ID
     * @param  string  $benchmarkCode  Benchmark code (ftse_all_share, sp500, etc.)
     * @param  string  $period  Period (1y, 3y, 5y, max)
     * @return array Comparison results
     */
    public function compareWithBenchmark(
        int $userId,
        string $benchmarkCode = 'ftse_all_share',
        string $period = '1y'
    ): array {
        // Get portfolio performance data
        $portfolioReturns = $this->getPortfolioReturns($userId, $period);

        if (empty($portfolioReturns)) {
            return [
                'success' => false,
                'message' => 'Insufficient portfolio data for comparison',
            ];
        }

        // Get benchmark returns
        $benchmarkReturns = $this->getBenchmarkReturns($benchmarkCode, $period, count($portfolioReturns));

        // Calculate cumulative returns
        $portfolioCumulative = $this->calculateCumulativeReturn($portfolioReturns);
        $benchmarkCumulative = $this->calculateCumulativeReturn($benchmarkReturns);

        // Calculate alpha/beta
        $alphaBeta = $this->alphaBetaCalculator->calculateAlphaBeta(
            $portfolioReturns,
            $benchmarkReturns
        );

        // Calculate annualized returns
        $portfolioAnnualized = $this->annualizeReturn($portfolioCumulative, $period);
        $benchmarkAnnualized = $this->annualizeReturn($benchmarkCumulative, $period);

        // Calculate outperformance
        $outperformance = $portfolioAnnualized - $benchmarkAnnualized;

        // Get benchmark info
        $benchmarkInfo = $this->getBenchmarkInfo($benchmarkCode);

        return [
            'success' => true,
            'period' => $period,
            'benchmark' => $benchmarkInfo,
            'portfolio_return' => [
                'cumulative' => $portfolioCumulative,
                'annualized' => $portfolioAnnualized,
                'annualized_percent' => $portfolioAnnualized * 100,
            ],
            'benchmark_return' => [
                'cumulative' => $benchmarkCumulative,
                'annualized' => $benchmarkAnnualized,
                'annualized_percent' => $benchmarkAnnualized * 100,
            ],
            'outperformance' => [
                'absolute' => $outperformance,
                'absolute_percent' => $outperformance * 100,
                'relative_percent' => $benchmarkAnnualized != 0
                    ? (($portfolioAnnualized / $benchmarkAnnualized) - 1) * 100
                    : 0,
            ],
            'risk_metrics' => $alphaBeta,
            'interpretation' => $this->interpretComparison($outperformance, $alphaBeta),
        ];
    }

    /**
     * Compare portfolio against multiple benchmarks
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Period
     * @return array Multi-benchmark comparison
     */
    public function compareWithMultipleBenchmarks(int $userId, string $period = '1y'): array
    {
        $benchmarks = ['ftse_all_share', 'ftse_100', 'sp500', 'msci_world', '60_40_portfolio'];
        $comparisons = [];

        foreach ($benchmarks as $benchmarkCode) {
            $comparison = $this->compareWithBenchmark($userId, $benchmarkCode, $period);
            if ($comparison['success']) {
                $comparisons[] = $comparison;
            }
        }

        // Find best matching benchmark (highest R²)
        $bestMatch = null;
        $highestRSquared = 0;

        foreach ($comparisons as $comparison) {
            $rSquared = $comparison['risk_metrics']['r_squared'] ?? 0;
            if ($rSquared > $highestRSquared) {
                $highestRSquared = $rSquared;
                $bestMatch = $comparison['benchmark'];
            }
        }

        return [
            'success' => true,
            'period' => $period,
            'comparisons' => $comparisons,
            'best_matching_benchmark' => $bestMatch,
            'summary' => $this->generateMultiBenchmarkSummary($comparisons),
        ];
    }

    /**
     * Get portfolio returns for a period
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Period
     * @return array Monthly returns
     */
    private function getPortfolioReturns(int $userId, string $period): array
    {
        // In production, this would query historical portfolio values
        // For now, generate simulated returns based on current holdings

        $months = $this->getPeriodMonths($period);

        // Simulate monthly returns (would be real historical data in production)
        $returns = [];
        for ($i = 0; $i < $months; $i++) {
            // Simulate: mean 0.7%, std dev 3%
            $returns[] = $this->generateSimulatedReturn(0.007, 0.03);
        }

        return $returns;
    }

    /**
     * Get benchmark returns
     *
     * @param  string  $benchmarkCode  Benchmark code
     * @param  string  $period  Period
     * @param  int  $count  Number of periods
     * @return array Benchmark returns
     */
    private function getBenchmarkReturns(string $benchmarkCode, string $period, int $count): array
    {
        // In production, fetch from external API or database
        // For now, use historical average returns

        $benchmarkParams = match ($benchmarkCode) {
            'ftse_all_share' => ['mean' => 0.0065, 'stddev' => 0.035], // 7.8% annually
            'ftse_100' => ['mean' => 0.006, 'stddev' => 0.033], // 7.2% annually
            'sp500' => ['mean' => 0.008, 'stddev' => 0.04], // 9.6% annually
            'msci_world' => ['mean' => 0.007, 'stddev' => 0.038], // 8.4% annually
            'uk_gilts' => ['mean' => 0.003, 'stddev' => 0.015], // 3.6% annually
            '60_40_portfolio' => ['mean' => 0.0055, 'stddev' => 0.025], // 6.6% annually
            default => ['mean' => 0.0065, 'stddev' => 0.035],
        };

        $returns = [];
        for ($i = 0; $i < $count; $i++) {
            $returns[] = $this->generateSimulatedReturn(
                $benchmarkParams['mean'],
                $benchmarkParams['stddev']
            );
        }

        return $returns;
    }

    /**
     * Generate simulated return (normal distribution)
     *
     * @param  float  $mean  Mean return
     * @param  float  $stddev  Standard deviation
     * @return float Simulated return
     */
    private function generateSimulatedReturn(float $mean, float $stddev): float
    {
        // Box-Muller transform for normal distribution
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        $z = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);

        return $mean + ($z * $stddev);
    }

    /**
     * Calculate cumulative return from periodic returns
     *
     * @param  array  $returns  Periodic returns
     * @return float Cumulative return
     */
    private function calculateCumulativeReturn(array $returns): float
    {
        $cumulative = 1.0;
        foreach ($returns as $return) {
            $cumulative *= (1 + $return);
        }

        return $cumulative - 1;
    }

    /**
     * Annualize a cumulative return
     *
     * @param  float  $cumulativeReturn  Cumulative return
     * @param  string  $period  Period
     * @return float Annualized return
     */
    private function annualizeReturn(float $cumulativeReturn, string $period): float
    {
        $years = $this->getPeriodYears($period);

        return pow(1 + $cumulativeReturn, 1 / $years) - 1;
    }

    /**
     * Get number of months for a period
     *
     * @param  string  $period  Period (1y, 3y, 5y, max)
     * @return int Number of months
     */
    private function getPeriodMonths(string $period): int
    {
        return match ($period) {
            '1y' => 12,
            '3y' => 36,
            '5y' => 60,
            '10y' => 120,
            'max' => 120,
            default => 12,
        };
    }

    /**
     * Get number of years for a period
     *
     * @param  string  $period  Period
     * @return float Number of years
     */
    private function getPeriodYears(string $period): float
    {
        return match ($period) {
            '1y' => 1,
            '3y' => 3,
            '5y' => 5,
            '10y' => 10,
            'max' => 10,
            default => 1,
        };
    }

    /**
     * Get benchmark information
     *
     * @param  string  $benchmarkCode  Benchmark code
     * @return array Benchmark info
     */
    private function getBenchmarkInfo(string $benchmarkCode): array
    {
        return match ($benchmarkCode) {
            'ftse_all_share' => [
                'code' => 'ftse_all_share',
                'name' => 'FTSE All-Share',
                'description' => 'UK market - all companies',
                'composition' => '~600 UK companies',
            ],
            'ftse_100' => [
                'code' => 'ftse_100',
                'name' => 'FTSE 100',
                'description' => 'UK large cap',
                'composition' => '100 largest UK companies',
            ],
            'sp500' => [
                'code' => 'sp500',
                'name' => 'S&P 500',
                'description' => 'US market',
                'composition' => '500 largest US companies',
            ],
            'msci_world' => [
                'code' => 'msci_world',
                'name' => 'MSCI World',
                'description' => 'Global developed markets',
                'composition' => 'Large/mid cap across 23 developed markets',
            ],
            'uk_gilts' => [
                'code' => 'uk_gilts',
                'name' => 'UK Gilts',
                'description' => 'UK government bonds',
                'composition' => 'All UK government bonds',
            ],
            '60_40_portfolio' => [
                'code' => '60_40_portfolio',
                'name' => '60/40 Portfolio',
                'description' => 'Balanced portfolio',
                'composition' => '60% global equities, 40% bonds',
            ],
            default => [
                'code' => $benchmarkCode,
                'name' => 'Unknown Benchmark',
                'description' => 'N/A',
                'composition' => 'N/A',
            ],
        };
    }

    /**
     * Interpret comparison results
     *
     * @param  float  $outperformance  Outperformance (annualized)
     * @param  array  $alphaBeta  Alpha/beta metrics
     * @return string Interpretation
     */
    private function interpretComparison(float $outperformance, array $alphaBeta): string
    {
        $outPerformancePercent = $outperformance * 100;
        $alpha = $alphaBeta['annualized_alpha'] * 100;
        $beta = $alphaBeta['beta'];

        $parts = [];

        if ($outPerformancePercent > 2) {
            $parts[] = sprintf('Strong outperformance: +%.1f%% vs benchmark', $outPerformancePercent);
        } elseif ($outPerformancePercent > 0) {
            $parts[] = sprintf('Modest outperformance: +%.1f%% vs benchmark', $outPerformancePercent);
        } else {
            $parts[] = sprintf('Underperformance: %.1f%% vs benchmark', $outPerformancePercent);
        }

        if ($alpha > 1) {
            $parts[] = sprintf('Positive alpha (%.1f%%) indicates skill-based returns', $alpha);
        } elseif ($alpha < -1) {
            $parts[] = sprintf('Negative alpha (%.1f%%) suggests manager underperformance', $alpha);
        }

        if ($beta > 1.2) {
            $parts[] = sprintf('Higher risk (beta %.2f) than benchmark', $beta);
        } elseif ($beta < 0.8) {
            $parts[] = sprintf('Lower risk (beta %.2f) than benchmark', $beta);
        }

        return implode('. ', $parts);
    }

    /**
     * Generate multi-benchmark summary
     *
     * @param  array  $comparisons  Benchmark comparisons
     * @return array Summary
     */
    private function generateMultiBenchmarkSummary(array $comparisons): array
    {
        if (empty($comparisons)) {
            return ['message' => 'No comparisons available'];
        }

        $avgOutperformance = 0;
        $bestOutperformance = null;
        $worstOutperformance = null;

        foreach ($comparisons as $comparison) {
            $outperf = $comparison['outperformance']['absolute_percent'];
            $avgOutperformance += $outperf;

            if ($bestOutperformance === null || $outperf > $bestOutperformance['value']) {
                $bestOutperformance = [
                    'benchmark' => $comparison['benchmark']['name'],
                    'value' => $outperf,
                ];
            }

            if ($worstOutperformance === null || $outperf < $worstOutperformance['value']) {
                $worstOutperformance = [
                    'benchmark' => $comparison['benchmark']['name'],
                    'value' => $outperf,
                ];
            }
        }

        $avgOutperformance /= count($comparisons);

        return [
            'average_outperformance_percent' => $avgOutperformance,
            'best_relative_performance' => $bestOutperformance,
            'worst_relative_performance' => $worstOutperformance,
            'overall_assessment' => $avgOutperformance > 0
                ? 'Portfolio outperforming most benchmarks'
                : 'Portfolio underperforming most benchmarks',
        ];
    }
}
