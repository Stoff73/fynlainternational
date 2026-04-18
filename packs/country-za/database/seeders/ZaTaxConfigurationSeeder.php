<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Database\Seeders;

use Database\Seeders\ZaJurisdictionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the SA jurisdiction, the 2026/27 tax_years row, and the full
 * set of za_tax_configurations rows.
 *
 * All values in minor units (cents). Rates stored as basis points
 * (e.g. 36% = 3600 bps). Brackets carry accumulated_base_minor so the
 * engine's arithmetic matches SARS published tables exactly without
 * rounding drift.
 *
 * WARNING: these values reflect the best available interpretation of
 * the SARS 2026/27 Budget as of April 2026. Production rollout must
 * cross-check every row against the live SARS tables published on
 * https://www.sars.gov.za/tax-rates/ before being considered authoritative.
 *
 * Idempotent — uses updateOrInsert keyed on (tax_year, key_path).
 */
class ZaTaxConfigurationSeeder extends Seeder
{
    private const TAX_YEAR = '2026/27';
    private const EFFECTIVE_FROM = '2026-03-01';

    public function run(): void
    {
        // 1. Prerequisite — jurisdiction row.
        $this->call(ZaJurisdictionSeeder::class);

        $zaId = DB::table('jurisdictions')->where('code', 'ZA')->value('id');
        if ($zaId === null) {
            throw new \RuntimeException('ZA jurisdiction failed to seed');
        }

        // 2. tax_years row (calendar_type = tax_year per ADR-006).
        DB::table('tax_years')->updateOrInsert(
            ['jurisdiction_id' => $zaId, 'label' => self::TAX_YEAR],
            [
                'starts_on' => '2026-03-01',
                'ends_on' => '2027-02-28',
                'calendar_type' => 'tax_year',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // 3. za_tax_configurations — one row per leaf key.
        foreach ($this->rows() as [$key, $valueCents, $notes]) {
            DB::table('za_tax_configurations')->updateOrInsert(
                ['tax_year' => self::TAX_YEAR, 'key_path' => $key],
                [
                    'value_cents' => $valueCents,
                    'effective_from' => self::EFFECTIVE_FROM,
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function rows(): array
    {
        return array_merge(
            $this->incomeTaxRows(),
            $this->rebateRows(),
            $this->thresholdRows(),
            $this->cgtRows(),
            $this->dwtRows(),
            $this->interestRows(),
            $this->medicalRows(),
            $this->section11fRows(),
            $this->retirementLumpSumRows(),
            $this->estateDutyRows(),
            $this->donationsRows(),
            $this->tfsaRows(),
            $this->endowmentRows(),
            $this->exchangeControlRows(),
            $this->retirementRows(),
            $this->annuityRows(),
            $this->reg28Rows(),
        );
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function incomeTaxRows(): array
    {
        // SARS 2026/27 brackets — seven bands. `lower` is the threshold
        // ABOVE which the bracket rate applies (matches SARS's "R125,599 +
        // 36% × (income − R530,200)" convention). The open-ended top
        // bracket simply omits its `upper` row — ZaTaxConfigService's
        // nested reconstitution treats the absent key as null.
        // Accumulated bases stored so the engine matches SARS published
        // tables exactly without rounding drift.
        $brackets = [
            ['lower' => 0,               'upper' => 245_100_00,    'rate_bps' => 1800, 'base' => 0],
            ['lower' => 245_100_00,      'upper' => 383_100_00,    'rate_bps' => 2600, 'base' => 4_411_800],
            ['lower' => 383_100_00,      'upper' => 530_200_00,    'rate_bps' => 3100, 'base' => 7_999_800],
            ['lower' => 530_200_00,      'upper' => 695_800_00,    'rate_bps' => 3600, 'base' => 12_559_900],
            ['lower' => 695_800_00,      'upper' => 882_400_00,    'rate_bps' => 3900, 'base' => 18_521_500],
            ['lower' => 882_400_00,      'upper' => 1_867_900_00,  'rate_bps' => 4100, 'base' => 25_798_900],
            ['lower' => 1_867_900_00,    'upper' => null,          'rate_bps' => 4500, 'base' => 66_204_400],
        ];

        $rows = [];
        foreach ($brackets as $i => $b) {
            $rows[] = ["income_tax.brackets.{$i}.lower", $b['lower'], null];
            if ($b['upper'] !== null) {
                $rows[] = ["income_tax.brackets.{$i}.upper", $b['upper'], null];
            }
            $rows[] = ["income_tax.brackets.{$i}.rate_bps", $b['rate_bps'], null];
            $rows[] = ["income_tax.brackets.{$i}.accumulated_base_minor", $b['base'], null];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function rebateRows(): array
    {
        return [
            ['rebates.primary_minor', 1_782_000, 'R17,820 primary rebate'],
            ['rebates.secondary_minor', 957_000, 'R9,570 secondary rebate (age 65-74)'],
            ['rebates.tertiary_minor', 314_500, 'R3,145 tertiary rebate (age 75+)'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function thresholdRows(): array
    {
        return [
            ['income_tax.thresholds.under_65_minor', 9_900_000, 'Primary rebate / 18% = R99,000'],
            ['income_tax.thresholds.age_65_74_minor', 14_821_700, '(primary + secondary) / 18% = R148,217'],
            ['income_tax.thresholds.age_75_plus_minor', 16_568_900, '(primary + secondary + tertiary) / 18% = R165,689'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function cgtRows(): array
    {
        return [
            ['cgt.individual_inclusion_bps', 4000, '40% individual inclusion rate'],
            ['cgt.endowment_wrapper_rate_bps', 3000, '30% flat wrapper rate for endowment disposals'],
            ['cgt.annual_exclusion_minor', 4_000_000, 'R40,000 annual exclusion'],
            ['cgt.death_exclusion_minor', 30_000_000, 'R300,000 death exclusion'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function dwtRows(): array
    {
        return [
            ['dwt.local_rate_bps', 2000, '20% local dividend withholding tax'],
            ['dwt.foreign_effective_cap_bps', 2000, 's10B 25/45 × max marginal = 20% effective cap'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function interestRows(): array
    {
        return [
            ['interest.exemption_under_65_minor', 2_380_000, 'R23,800 interest exemption (under 65)'],
            ['interest.exemption_65_plus_minor', 3_450_000, 'R34,500 interest exemption (65+)'],
        ];
    }

    /**
     * TFSA — Tax-Free Savings Account (section 12T). WS 1.2a.
     *
     * 2026/27 figures from National Treasury / SARS:
     *   annual contribution cap R46,000 (from 1 March 2026)
     *   lifetime cap R500,000 (unchanged)
     *   40% flat penalty on excess contributions (Schedule 2 s12T(7))
     *
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function tfsaRows(): array
    {
        return [
            ['tfsa.annual_limit_minor', 4_600_000, 'R46,000 annual TFSA contribution cap'],
            ['tfsa.lifetime_limit_minor', 50_000_000, 'R500,000 lifetime TFSA contribution cap'],
            ['tfsa.over_contribution_penalty_bps', 4_000, '40% penalty rate on excess TFSA contributions (basis points)'],
        ];
    }

    /**
     * Endowment (section 29A) — WS 1.2a seeds the two income-tax / period
     * rows that WS 1.3 (Investment) will consume. The CGT rate for endowment
     * wrappers stays under cgt.endowment_wrapper_rate_bps (already in
     * cgtRows()) — do NOT duplicate under endowment.cgt_rate_bps.
     *
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function endowmentRows(): array
    {
        return [
            ['endowment.income_tax_rate_bps', 3_000, '30% effective income tax rate inside endowment wrapper'],
            ['endowment.restriction_period_years', 5, 'Section 29A 5-year restriction window'],
        ];
    }

    /**
     * Exchange control allowances (WS 1.3b).
     *
     * Per-person per-calendar-year, resetting on 1 January. Amounts in
     * cents (R2,000,000 = 200,000,000 cents). Sources: SARS / SARB
     * Exchange Control Regulations, 2026 update doubling SDA from R1m
     * to R2m.
     *
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function exchangeControlRows(): array
    {
        return [
            ['excon.sda_annual_limit_minor', 200_000_000, 'Single Discretionary Allowance — R2,000,000 per calendar year'],
            ['excon.fia_annual_limit_minor', 1_000_000_000, 'Foreign Investment Allowance — R10,000,000 per calendar year (requires AIT)'],
            ['excon.sarb_special_approval_threshold_minor', 1_200_000_000, 'Combined SDA+FIA above this triggers SARB special approval'],
        ];
    }

    /**
     * Retirement — Two-Pot system constants (WS 1.4a).
     *
     * Two-Pot effective date: 1 September 2024 (hardcoded in service).
     * Savings-Pot withdrawal minimum: R2,000 per SARS Regulation.
     * Split: 1/3 Savings / 2/3 Retirement (bps rounded; the service uses
     * integer division to preserve the cent regardless).
     *
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function retirementRows(): array
    {
        return [
            ['retirement.savings_pot_minimum_withdrawal_minor', 200_000, 'R2,000 minimum single Savings-Pot withdrawal'],
            ['retirement.savings_pot_split_bps', 3_333, 'Two-Pot 1/3 Savings share (33.33%)'],
            ['retirement.retirement_pot_split_bps', 6_667, 'Two-Pot 2/3 Retirement share (66.67%)'],
        ];
    }

    /**
     * Annuity mechanics (WS 1.4b).
     *   - Living annuity drawdown band 2.5%–17.5% of capital (Reg 39)
     *   - Compulsory annuitisation de minimis: R165,000 commutable
     *     subset allows full commutation
     *
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function annuityRows(): array
    {
        return [
            ['annuity.living.drawdown_min_bps', 250, '2.5% minimum living annuity drawdown'],
            ['annuity.living.drawdown_max_bps', 1_750, '17.5% maximum living annuity drawdown'],
            ['annuity.de_minimis_threshold_minor', 16_500_000, 'R165,000 full-commutation threshold at retirement'],
        ];
    }

    /**
     * Regulation 28 asset-class limits (WS 1.4c). Pre-retirement funds
     * only — living annuities are exempt (spec § 9.4).
     *
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function reg28Rows(): array
    {
        return [
            ['reg28.offshore_max_bps', 4_500, '45% max offshore (all foreign including Africa)'],
            ['reg28.equity_max_bps', 7_500, '75% max equities (local + foreign combined)'],
            ['reg28.property_max_bps', 2_500, '25% max property'],
            ['reg28.private_equity_max_bps', 1_500, '15% max private equity'],
            ['reg28.commodities_max_bps', 1_000, '10% max commodities (incl. gold)'],
            ['reg28.hedge_funds_max_bps', 1_000, '10% max hedge funds'],
            ['reg28.other_max_bps', 250, '2.5% max other/alternative assets'],
            ['reg28.single_entity_max_bps', 2_500, '25% max exposure to a single entity'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function medicalRows(): array
    {
        return [
            ['medical.main_plus_first_monthly_minor', 37_600, 'R376/month for main member + first dependant'],
            ['medical.additional_monthly_minor', 25_400, 'R254/month for each additional dependant'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function section11fRows(): array
    {
        return [
            ['section_11f.percentage_cap_bps', 2750, '27.5% of greater of remuneration/taxable income'],
            ['section_11f.absolute_cap_minor', 35_000_000, 'R350,000 annual absolute cap'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function retirementLumpSumRows(): array
    {
        // SARS retirement lump-sum table (applied cumulatively since 1 Oct 2007).
        // 0 / 18 / 27 / 36 above R550k / R770k / R1.155m.
        $retirement = [
            ['lower' => 0,            'upper' => 55_000_000,   'rate_bps' => 0,    'base' => 0],
            ['lower' => 55_000_000,   'upper' => 77_000_000,   'rate_bps' => 1800, 'base' => 0],
            ['lower' => 77_000_000,   'upper' => 115_500_000,  'rate_bps' => 2700, 'base' => 3_960_000],
            ['lower' => 115_500_000,  'upper' => null,         'rate_bps' => 3600, 'base' => 14_355_000],
        ];

        // SARS withdrawal lump-sum table. 0 / 18 / 27 / 36 above R27,500 / R726k / R1.089m.
        $withdrawal = [
            ['lower' => 0,            'upper' => 2_750_000,    'rate_bps' => 0,    'base' => 0],
            ['lower' => 2_750_000,    'upper' => 72_600_000,   'rate_bps' => 1800, 'base' => 0],
            ['lower' => 72_600_000,   'upper' => 108_900_000,  'rate_bps' => 2700, 'base' => 12_573_000],
            ['lower' => 108_900_000,  'upper' => null,         'rate_bps' => 3600, 'base' => 22_374_000],
        ];

        $rows = [];
        foreach ($retirement as $i => $row) {
            $rows[] = ["retirement.lump_sum.retirement_table.{$i}.lower", $row['lower'], null];
            if ($row['upper'] !== null) {
                $rows[] = ["retirement.lump_sum.retirement_table.{$i}.upper", $row['upper'], null];
            }
            $rows[] = ["retirement.lump_sum.retirement_table.{$i}.rate_bps", $row['rate_bps'], null];
            $rows[] = ["retirement.lump_sum.retirement_table.{$i}.accumulated_base_minor", $row['base'], null];
        }
        foreach ($withdrawal as $i => $row) {
            $rows[] = ["retirement.lump_sum.withdrawal_table.{$i}.lower", $row['lower'], null];
            if ($row['upper'] !== null) {
                $rows[] = ["retirement.lump_sum.withdrawal_table.{$i}.upper", $row['upper'], null];
            }
            $rows[] = ["retirement.lump_sum.withdrawal_table.{$i}.rate_bps", $row['rate_bps'], null];
            $rows[] = ["retirement.lump_sum.withdrawal_table.{$i}.accumulated_base_minor", $row['base'], null];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function estateDutyRows(): array
    {
        return [
            ['estate_duty.abatement_minor', 350_000_000, 'R3.5m spousal abatement'],
            ['estate_duty.lower_rate_bps', 2000, '20% below R30m dutiable'],
            ['estate_duty.higher_rate_bps', 2500, '25% above R30m dutiable'],
            ['estate_duty.higher_rate_threshold_minor', 3_000_000_000, 'R30m threshold'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: ?string}>
     */
    private function donationsRows(): array
    {
        return [
            ['donations.annual_exemption_minor', 10_000_000, 'R100,000 annual exemption'],
            ['donations.lower_rate_bps', 2000, '20% below R30m cumulative (since 2018-03-01)'],
            ['donations.higher_rate_bps', 2500, '25% above R30m cumulative'],
            ['donations.higher_rate_threshold_minor', 3_000_000_000, 'R30m cumulative threshold'],
        ];
    }
}
