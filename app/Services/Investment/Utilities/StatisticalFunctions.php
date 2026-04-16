<?php

declare(strict_types=1);

namespace App\Services\Investment\Utilities;

/**
 * Statistical functions for portfolio analysis
 */
class StatisticalFunctions
{
    /**
     * Calculate mean (average) of an array
     *
     * @param  array  $values  Array of numeric values
     * @return float Mean value
     */
    public function mean(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * Calculate standard deviation
     *
     * @param  array  $values  Array of numeric values
     * @param  bool  $sample  Whether to use sample (n-1) or population (n) formula
     * @return float Standard deviation
     */
    public function standardDeviation(array $values, bool $sample = true): float
    {
        $variance = $this->variance($values, $sample);

        return sqrt($variance);
    }

    /**
     * Calculate variance
     *
     * @param  array  $values  Array of numeric values
     * @param  bool  $sample  Whether to use sample (n-1) or population (n) formula
     * @return float Variance
     */
    public function variance(array $values, bool $sample = true): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        $mean = $this->mean($values);
        $sumSquares = 0;

        foreach ($values as $value) {
            $sumSquares += pow($value - $mean, 2);
        }

        $divisor = $sample ? ($n - 1) : $n;

        return $divisor > 0 ? $sumSquares / $divisor : 0.0;
    }

    /**
     * Calculate covariance between two arrays
     *
     * @param  array  $x  First array
     * @param  array  $y  Second array
     * @param  bool  $sample  Whether to use sample formula
     * @return float Covariance
     */
    public function covariance(array $x, array $y, bool $sample = true): float
    {
        $n = count($x);
        if ($n !== count($y) || $n === 0) {
            return 0.0;
        }

        $meanX = $this->mean($x);
        $meanY = $this->mean($y);
        $sum = 0;

        for ($i = 0; $i < $n; $i++) {
            $sum += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        }

        $divisor = $sample ? ($n - 1) : $n;

        return $divisor > 0 ? $sum / $divisor : 0.0;
    }

    /**
     * Calculate correlation coefficient
     *
     * @param  array  $x  First array
     * @param  array  $y  Second array
     * @return float Correlation coefficient (-1 to 1)
     */
    public function correlation(array $x, array $y): float
    {
        $cov = $this->covariance($x, $y);
        $stdX = $this->standardDeviation($x);
        $stdY = $this->standardDeviation($y);

        if ($stdX == 0 || $stdY == 0) {
            return 0.0;
        }

        return $cov / ($stdX * $stdY);
    }

    /**
     * Calculate percentile
     *
     * @param  array  $values  Array of numeric values
     * @param  float  $percentile  Percentile (0-100)
     * @return float Percentile value
     */
    public function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower === $upper) {
            return $values[$lower];
        }

        $weight = $index - $lower;

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    /**
     * Calculate downside deviation (for Sortino ratio)
     * Only considers returns below target
     *
     * @param  array  $returns  Array of returns
     * @param  float  $target  Target return (usually 0 or risk-free rate)
     * @return float Downside deviation
     */
    public function downsideDeviation(array $returns, float $target = 0.0): float
    {
        $n = count($returns);
        if ($n === 0) {
            return 0.0;
        }

        $sumSquares = 0;
        $count = 0;

        foreach ($returns as $return) {
            if ($return < $target) {
                $sumSquares += pow($return - $target, 2);
                $count++;
            }
        }

        if ($count === 0) {
            return 0.0;
        }

        return sqrt($sumSquares / $count);
    }

    /**
     * Calculate simple linear regression (y = a + bx)
     *
     * @param  array  $x  Independent variable
     * @param  array  $y  Dependent variable
     * @return array ['slope' => b, 'intercept' => a, 'r_squared' => R²]
     */
    public function linearRegression(array $x, array $y): array
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            return ['slope' => 0.0, 'intercept' => 0.0, 'r_squared' => 0.0];
        }

        $meanX = $this->mean($x);
        $meanY = $this->mean($y);

        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x[$i] - $meanX) * ($y[$i] - $meanY);
            $denominator += pow($x[$i] - $meanX, 2);
        }

        if ($denominator == 0) {
            return ['slope' => 0.0, 'intercept' => 0.0, 'r_squared' => 0.0];
        }

        $slope = $numerator / $denominator;
        $intercept = $meanY - $slope * $meanX;

        // Calculate R²
        $ssRes = 0;
        $ssTot = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $x[$i];
            $ssRes += pow($y[$i] - $predicted, 2);
            $ssTot += pow($y[$i] - $meanY, 2);
        }

        $rSquared = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0.0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => max(0.0, min(1.0, $rSquared)), // Clamp between 0 and 1
        ];
    }

    /**
     * Calculate annualized return from cumulative return
     *
     * @param  float  $cumulativeReturn  Cumulative return (e.g., 0.50 for 50%)
     * @param  int  $periods  Number of periods
     * @param  int  $periodsPerYear  Periods per year (12 for monthly, 252 for daily)
     * @return float Annualized return
     */
    public function annualizeReturn(float $cumulativeReturn, int $periods, int $periodsPerYear = 12): float
    {
        if ($periods === 0) {
            return 0.0;
        }

        $years = $periods / $periodsPerYear;

        return pow(1 + $cumulativeReturn, 1 / $years) - 1;
    }

    /**
     * Calculate annualized volatility
     *
     * @param  float  $periodVolatility  Volatility for single period
     * @param  int  $periodsPerYear  Periods per year (12 for monthly, 252 for daily)
     * @return float Annualized volatility
     */
    public function annualizeVolatility(float $periodVolatility, int $periodsPerYear = 12): float
    {
        return $periodVolatility * sqrt($periodsPerYear);
    }
}
