<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use InvalidArgumentException;

/**
 * At-retirement compulsory annuitisation service.
 *
 * Three SA rules applied in precedence:
 *   1. Two-Pot retirement bucket ALWAYS annuitises, regardless of total.
 *   2. Provident-pre-2021 balance is 100% commutable to PCLS for members
 *      55+ on 1 March 2021 (spec § 9.1). Adds to the lump-sum total.
 *   3. If the commutable subset (vested + provident_pre2021) is under
 *      the R165k de minimis threshold, it may be fully commuted.
 *      Otherwise: 1/3 PCLS + 2/3 annuity on vested; provident-pre-2021
 *      goes 100% to PCLS regardless.
 *
 * Retirement bucket is never included in the de minimis calculation.
 */
class ZaCompulsoryAnnuitisationService
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    /**
     * @return array{
     *     pcls_minor: int,
     *     compulsory_annuity_minor: int,
     *     de_minimis_applied: bool,
     *     de_minimis_threshold_minor: int
     * }
     */
    public function apportion(
        int $vestedMinor,
        int $providentVestedPre2021Minor,
        int $retirementMinor,
        string $taxYear,
    ): array {
        if ($vestedMinor < 0 || $providentVestedPre2021Minor < 0 || $retirementMinor < 0) {
            throw new InvalidArgumentException('Bucket balances cannot be negative.');
        }

        $deMinimis = (int) $this->config->get(
            $taxYear,
            'annuity.de_minimis_threshold_minor',
            16_500_000,
        );

        $commutableBase = $vestedMinor + $providentVestedPre2021Minor;
        $deMinimisApplied = $commutableBase > 0 && $commutableBase <= $deMinimis;

        if ($deMinimisApplied) {
            $pcls = $commutableBase;
            $compAnnuity = $retirementMinor;
        } else {
            $vestedPcls = intdiv($vestedMinor, 3);
            $vestedAnnuity = $vestedMinor - $vestedPcls;

            $pcls = $vestedPcls + $providentVestedPre2021Minor;
            $compAnnuity = $vestedAnnuity + $retirementMinor;
        }

        return [
            'pcls_minor' => $pcls,
            'compulsory_annuity_minor' => $compAnnuity,
            'de_minimis_applied' => $deMinimisApplied,
            'de_minimis_threshold_minor' => $deMinimis,
        ];
    }
}
