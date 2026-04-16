<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Investment;

use Fynla\Core\Contracts\InvestmentEngine as InvestmentEngineContract;

/**
 * Implements Fynla\Core\Contracts\InvestmentEngine
 *
 * Country-specific investment calculations including tax-advantaged wrappers,
 * allowances, platform rules, and regulatory constraints.
 *
 * TODO: Implement all methods from the InvestmentEngine contract.
 */
class InvestmentEngine implements InvestmentEngineContract
{
    /**
     * {@inheritDoc}
     */
    public function getTaxWrappers(): array
    {
        throw new \RuntimeException('Not implemented: InvestmentEngine::getTaxWrappers');
    }

    /**
     * {@inheritDoc}
     */
    public function getAnnualAllowances(string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: InvestmentEngine::getAnnualAllowances');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateInvestmentTax(array $params): array
    {
        throw new \RuntimeException('Not implemented: InvestmentEngine::calculateInvestmentTax');
    }

    /**
     * {@inheritDoc}
     */
    public function getAssetAllocationRules(): array
    {
        throw new \RuntimeException('Not implemented: InvestmentEngine::getAssetAllocationRules');
    }
}
