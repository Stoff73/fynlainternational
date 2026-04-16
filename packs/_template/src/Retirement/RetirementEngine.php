<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Retirement;

use Fynla\Core\Contracts\RetirementEngine as RetirementEngineContract;

/**
 * Implements Fynla\Core\Contracts\RetirementEngine
 *
 * Country-specific retirement and pension calculations including contribution
 * limits, tax relief, decumulation strategies, and state pension entitlements.
 *
 * TODO: Implement all methods from the RetirementEngine contract.
 */
class RetirementEngine implements RetirementEngineContract
{
    /**
     * {@inheritDoc}
     */
    public function calculatePensionTaxRelief(int $contributionMinor, int $incomeMinor, string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: RetirementEngine::calculatePensionTaxRelief');
    }

    /**
     * {@inheritDoc}
     */
    public function getAnnualAllowance(string $taxYear): int
    {
        throw new \RuntimeException('Not implemented: RetirementEngine::getAnnualAllowance');
    }

    /**
     * {@inheritDoc}
     */
    public function getLifetimeAllowance(string $taxYear): ?int
    {
        throw new \RuntimeException('Not implemented: RetirementEngine::getLifetimeAllowance');
    }

    /**
     * {@inheritDoc}
     */
    public function getStatePensionAge(string $dateOfBirth, string $gender): int
    {
        throw new \RuntimeException('Not implemented: RetirementEngine::getStatePensionAge');
    }

    /**
     * {@inheritDoc}
     */
    public function projectPensionGrowth(array $params): array
    {
        throw new \RuntimeException('Not implemented: RetirementEngine::projectPensionGrowth');
    }
}
