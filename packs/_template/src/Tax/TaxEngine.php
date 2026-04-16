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
    public function calculateCGT(int $gainMinor, string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: TaxEngine::calculateCGT');
    }

    /**
     * {@inheritDoc}
     */
    public function getPersonalAllowance(string $taxYear): int
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
