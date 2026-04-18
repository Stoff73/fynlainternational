<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Localisation;

use Fynla\Core\Contracts\Localisation;

/**
 * SA Localisation — ZAR formatting, en_ZA locale, DD MMM YYYY dates,
 * terminology map translating generic terms to SA equivalents.
 */
class ZaLocalisation implements Localisation
{
    public function currencyCode(): string
    {
        return 'ZAR';
    }

    public function currencySymbol(): string
    {
        return 'R';
    }

    public function locale(): string
    {
        return 'en_ZA';
    }

    public function dateFormat(): string
    {
        return 'd M Y';
    }

    public function formatMoney(int $minorUnits): string
    {
        $rand = intdiv($minorUnits, 100);
        $cents = abs($minorUnits % 100);
        $sign = $minorUnits < 0 ? '-' : '';

        // SA convention: "R 1 234 567,89" — space thousands separator,
        // comma decimal. Using number_format with locale-inspired args.
        $formatted = number_format(abs($rand), 0, ',', ' ');

        return "{$sign}R {$formatted},{$this->padCents($cents)}";
    }

    public function getTerminology(): array
    {
        return [
            'retirement_wrapper' => 'Retirement Annuity',
            'tax_free_wrapper' => 'Tax-Free Savings Account',
            'estate_tax' => 'Estate Duty',
            'routing_code' => 'Branch Code',
            'national_id' => 'SA ID Number',
            'tax_authority' => 'SARS',
            'pension_regulator' => 'FSCA',
            'state_pension' => 'SASSA Old Age Grant',
        ];
    }

    private function padCents(int $cents): string
    {
        return str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }
}
