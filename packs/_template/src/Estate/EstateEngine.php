<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Estate;

use Fynla\Core\Contracts\EstateEngine as EstateEngineContract;

/**
 * Implements Fynla\Core\Contracts\EstateEngine
 *
 * Country-specific estate planning calculations including inheritance tax,
 * estate duty, succession rules, and available reliefs/exemptions.
 *
 * TODO: Implement all methods from the EstateEngine contract.
 */
class EstateEngine implements EstateEngineContract
{
    /**
     * {@inheritDoc}
     */
    public function calculateEstateTax(array $estate, string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: EstateEngine::calculateEstateTax');
    }

    /**
     * {@inheritDoc}
     */
    public function getExemptions(string $taxYear): array
    {
        throw new \RuntimeException('Not implemented: EstateEngine::getExemptions');
    }

    /**
     * {@inheritDoc}
     */
    public function getReliefs(): array
    {
        throw new \RuntimeException('Not implemented: EstateEngine::getReliefs');
    }

    /**
     * {@inheritDoc}
     */
    public function calculateExecutorFees(int $estateValueMinor): int
    {
        throw new \RuntimeException('Not implemented: EstateEngine::calculateExecutorFees');
    }
}
