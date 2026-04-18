<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * Savings-Pot withdrawal simulator.
 *
 * Two-Pot savings-component withdrawals are taxed at the member's
 * marginal rate on top of current-year income. The simulator composes
 * ZaTaxEngine::calculateIncomeTaxForAge(income) and
 * calculateIncomeTaxForAge(income + withdrawal) and returns the delta.
 *
 * Enforces the R2,000 minimum per SARS Regulation. Does NOT enforce
 * the "one withdrawal per tax year" rule — that requires a frequency
 * ledger outside the scope of WS 1.4a (deferred to WS 1.4d).
 */
class ZaSavingsPotWithdrawalSimulator
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     tax_delta_minor: int,
     *     net_received_minor: int,
     *     marginal_rate: float,
     *     crosses_bracket: bool
     * }
     */
    public function simulate(
        int $withdrawalMinor,
        int $currentYearIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($withdrawalMinor < 0 || $currentYearIncomeMinor < 0 || $age < 0) {
            throw new InvalidArgumentException('Simulator inputs cannot be negative.');
        }

        $minimum = (int) $this->config->get($taxYear, 'retirement.savings_pot_minimum_withdrawal_minor', 0);
        if ($withdrawalMinor < $minimum) {
            $minRand = intdiv($minimum, 100);
            throw new InvalidArgumentException(
                "Withdrawal {$withdrawalMinor} cents is below R{$minRand} minimum.",
            );
        }

        $baseline = $this->taxEngine->calculateIncomeTaxForAge(
            $currentYearIncomeMinor,
            $taxYear,
            $age,
        );
        $withWithdrawal = $this->taxEngine->calculateIncomeTaxForAge(
            $currentYearIncomeMinor + $withdrawalMinor,
            $taxYear,
            $age,
        );

        $taxDelta = max(0, $withWithdrawal['tax_due'] - $baseline['tax_due']);
        $crossesBracket = $baseline['breakdown']['bracket_index'] !== $withWithdrawal['breakdown']['bracket_index'];

        return [
            'tax_delta_minor' => $taxDelta,
            'net_received_minor' => $withdrawalMinor - $taxDelta,
            'marginal_rate' => (float) $withWithdrawal['marginal_rate'],
            'crosses_bracket' => $crossesBracket,
        ];
    }
}
