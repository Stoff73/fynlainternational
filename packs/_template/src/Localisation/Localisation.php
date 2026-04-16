<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Localisation;

use Fynla\Core\Contracts\Localisation as LocalisationContract;

/**
 * Implements Fynla\Core\Contracts\Localisation
 *
 * Country-specific localisation including date formats, number formats,
 * currency formatting, terminology, and regulatory naming conventions.
 *
 * TODO: Implement all methods from the Localisation contract.
 */
class Localisation implements LocalisationContract
{
    /**
     * {@inheritDoc}
     */
    public function currencyCode(): string
    {
        throw new \RuntimeException('Not implemented: Localisation::currencyCode');
    }

    /**
     * {@inheritDoc}
     */
    public function currencySymbol(): string
    {
        throw new \RuntimeException('Not implemented: Localisation::currencySymbol');
    }

    /**
     * {@inheritDoc}
     */
    public function locale(): string
    {
        throw new \RuntimeException('Not implemented: Localisation::locale');
    }

    /**
     * {@inheritDoc}
     */
    public function dateFormat(): string
    {
        throw new \RuntimeException('Not implemented: Localisation::dateFormat');
    }

    /**
     * {@inheritDoc}
     */
    public function formatMoney(int $minorUnits): string
    {
        throw new \RuntimeException('Not implemented: Localisation::formatMoney');
    }

    /**
     * {@inheritDoc}
     */
    public function getTerminology(): array
    {
        throw new \RuntimeException('Not implemented: Localisation::getTerminology');
    }
}
