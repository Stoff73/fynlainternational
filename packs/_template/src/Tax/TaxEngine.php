<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Tax;

use Fynla\Core\Contracts\TaxEngine as TaxEngineContract;

/**
 * Implements Fynla\Core\Contracts\TaxEngine
 *
 * Country-specific tax calculations including income tax, capital gains tax,
 * tax wrappers, allowances, and thresholds.
 *
 * TODO: Implement all methods from the TaxEngine contract.
 */
class TaxEngine implements TaxEngineContract
{
    /**
     * {@inheritDoc}
     */
    public function calculateIncomeTax(int $grossMinor, string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateIncomeTax');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateCGT(int $gainMinor, string $taxYear, array $options = []): array
    {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateCGT');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateLumpSumTax(
        int $amountMinor,
        string $taxYear,
        int $priorCumulativeMinor,
        string $tableType
    ): array {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateLumpSumTax');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateRetirementDeduction(
        int $grossMinor,
        string $taxYear,
        int $carryForwardMinor
    ): array {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateRetirementDeduction');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateDividendsWithholdingTax(
        int $amountMinor,
        string $taxYear,
        string $source
    ): int {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateDividendsWithholdingTax');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateMedicalCredits(
        int $mainPlusFirstDependant,
        int $additionalDependants,
        string $taxYear
    ): int {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateMedicalCredits');
    }

    /**
     * {@inheritDoc}
     */
    public function getPersonalAllowance(string $taxYear, ?int $age = null): int
    {
        throw new \RuntimeException('Not implemented: TaxEngine::getPersonalAllowance');
    }

    /**
     * {@inheritDoc}
     */
    public function getTaxBrackets(string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: TaxEngine::getTaxBrackets');
    }

    /**
     * {@inheritDoc}
     */
    public function getAnnualExemptions(string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: TaxEngine::getAnnualExemptions');
    }
}
