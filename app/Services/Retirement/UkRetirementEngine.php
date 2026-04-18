<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\RetirementEngine;

/**
 * UK-side RetirementEngine implementation.
 *
 * Exposes UK pension-allowance values behind the contract. Full UK
 * pension-tax composition lives in existing services
 * (RetirementAgent, PensionProjector, AnnualAllowanceChecker etc.) and
 * is not duplicated here; callers that need deep UK behaviour resolve
 * those classes directly.
 *
 * State pension age follows UK SPA phasing: 66 for those born 1955–1960,
 * 67 for 1961–1977, 68 for 1978+ (Pensions Act 2014).
 */
class UkRetirementEngine implements RetirementEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {
    }

    public function calculatePensionTaxRelief(int $contributionMinor, int $incomeMinor, string $taxYear): array
    {
        return [
            'relief_amount' => 0,
            'relief_rate' => 0.0,
            'net_cost' => $contributionMinor,
            'method' => 'uk_stub',
        ];
    }

    public function getAnnualAllowance(string $taxYear): int
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $allowancePounds = (int) ($pension['annual_allowance'] ?? 60_000);

        return $allowancePounds * 100;
    }

    public function getLifetimeAllowance(string $taxYear): ?int
    {
        // LTA abolished from 2024/25. Null signals "no lifetime limit".
        return null;
    }

    public function getStatePensionAge(string $dateOfBirth, string $gender): int
    {
        $birthYear = (int) substr($dateOfBirth, 0, 4);

        return match (true) {
            $birthYear >= 1978 => 68,
            $birthYear >= 1961 => 67,
            default => 66,
        };
    }

    public function projectPensionGrowth(array $params): array
    {
        $current = (int) ($params['current_value'] ?? 0);
        $annual = (int) ($params['annual_contribution'] ?? 0);
        $rate = (float) ($params['growth_rate'] ?? 0.05);
        $years = (int) ($params['years'] ?? 0);

        $value = $current;
        $yearByYear = [];

        for ($y = 1; $y <= $years; $y++) {
            $value = (int) round(($value + $annual) * (1 + $rate));
            $yearByYear[] = ['year' => $y, 'value' => $value];
        }

        return [
            'projected_value' => $value,
            'total_contributions' => $current + ($annual * $years),
            'total_growth' => max(0, $value - $current - ($annual * $years)),
            'year_by_year' => $yearByYear,
        ];
    }
}
