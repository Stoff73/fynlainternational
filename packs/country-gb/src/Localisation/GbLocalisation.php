<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Localisation;

use Fynla\Core\Contracts\Localisation;

/**
 * UK Localisation — GBP formatting, en_GB locale, DD/MM/YYYY dates,
 * terminology map translating generic terms to UK equivalents.
 */
class GbLocalisation implements Localisation
{
    public function currencyCode(): string
    {
        return 'GBP';
    }

    public function currencySymbol(): string
    {
        return '£';
    }

    public function locale(): string
    {
        return 'en_GB';
    }

    public function dateFormat(): string
    {
        return 'd/m/Y';
    }

    public function formatMoney(int $minorUnits): string
    {
        $sign = $minorUnits < 0 ? '-' : '';
        $absMinor = abs($minorUnits);
        $formatted = number_format($absMinor / 100, 2, '.', ',');

        return "{$sign}£{$formatted}";
    }

    public function getTerminology(): array
    {
        return [
            'retirement_wrapper' => 'Personal Pension',
            'tax_free_wrapper' => 'Individual Savings Account',
            'estate_tax' => 'Inheritance Tax',
            'routing_code' => 'Sort Code',
            'national_id' => 'National Insurance Number',
            'tax_authority' => 'HMRC',
            'pension_regulator' => 'The Pensions Regulator',
            'state_pension' => 'State Pension',
        ];
    }
}
