<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);

    // Flush the request-scoped config cache so seed changes are visible.
    app(ZaTaxConfigService::class)->forget();

    $this->engine = app(ZaTaxEngine::class);
});

describe('FR-M3 income tax — bracket boundaries', function () {
    it('computes zero tax below the rebate-implied threshold (under 65)', function () {
        $r = $this->engine->calculateIncomeTaxForAge(9_000_000, TAX_YEAR, 30); // R90,000
        expect($r['tax_due'])->toBe(0);
        expect($r['marginal_rate'])->toBe(18.0);
    });

    it('matches SARS scenario 1 — R688,000 at age 45 → R164,587 tax', function () {
        // PRD § 4 scenario 1. R125,599 + 36% × (R688,000 − R530,200) = R182,407
        // minus R17,820 primary rebate = R164,587 = 16,458,700 cents.
        $r = $this->engine->calculateIncomeTaxForAge(68_800_000, TAX_YEAR, 45);
        expect($r['tax_due'])->toBe(16_458_700);
        expect($r['marginal_rate'])->toBe(36.0);
        expect($r['breakdown']['bracket_index'])->toBe(3);
    });

    it('applies the age-65 secondary rebate', function () {
        // R200,000 at 30: R200k is in bracket 1 (@18%); tax before rebate
        // R36,000 – R17,820 primary = R18,180.
        $young = $this->engine->calculateIncomeTaxForAge(20_000_000, TAX_YEAR, 30);
        // Same income at 65: secondary rebate R9,570 kicks in → R36,000
        // – (17,820 + 9,570) = R8,610 tax.
        $old = $this->engine->calculateIncomeTaxForAge(20_000_000, TAX_YEAR, 65);

        expect($young['tax_due'])->toBe(1_818_000);
        expect($old['tax_due'])->toBe(861_000);
    });

    it('applies the age-75 tertiary rebate on top of secondary', function () {
        // R200,000 at 75: primary + secondary + tertiary = R17,820 + R9,570
        // + R3,145 = R30,535 rebate. Tax before rebate R36,000 → R5,465.
        $r = $this->engine->calculateIncomeTaxForAge(20_000_000, TAX_YEAR, 75);
        expect($r['tax_due'])->toBe(546_500);
    });

    it('reaches the top (45%) bracket for very high earners', function () {
        // R2m at age 45. Top bracket lower is R1,867,900, base R662,044,
        // marginal 45%. Tax before rebate = 662,044 + 45% × (2,000,000 −
        // 1,867,900) = 662,044 + 59,445 = R721,489 → − R17,820 = R703,669.
        $r = $this->engine->calculateIncomeTaxForAge(200_000_000, TAX_YEAR, 45);
        expect($r['tax_due'])->toBe(70_366_900);
        expect($r['marginal_rate'])->toBe(45.0);
        expect($r['breakdown']['bracket_index'])->toBe(6);
    });

    it('returns zero for zero or negative income', function () {
        $zero = $this->engine->calculateIncomeTaxForAge(0, TAX_YEAR, 30);
        $negative = $this->engine->calculateIncomeTaxForAge(-1_000_000, TAX_YEAR, 30);

        expect($zero['tax_due'])->toBe(0);
        expect($negative['tax_due'])->toBe(0);
    });
});

describe('FR-M3 CGT — individual vs endowment wrapper', function () {
    it('applies the 30% wrapper rate for endowments', function () {
        // PRD § 4 scenario 5. R200,000 gain in endowment → flat 30% = R60,000.
        $r = $this->engine->calculateCGT(20_000_000, TAX_YEAR, ['wrapper' => 'endowment']);

        expect($r['tax_due'])->toBe(6_000_000);
        expect($r['breakdown']['wrapper'])->toBe('endowment');
        expect($r['breakdown']['rate_bps'])->toBe(3000);
        expect($r['exemption_used'])->toBe(0);
    });

    it('applies the 40% individual inclusion with annual exclusion', function () {
        // R100,000 gain, annual exclusion R40,000 → taxable gain R60,000.
        // 40% inclusion → R24,000 included. At marginal 36% → R8,640.
        $r = $this->engine->calculateCGT(10_000_000, TAX_YEAR, ['marginal_rate' => 3600]);

        expect($r['exemption_used'])->toBe(4_000_000);
        expect($r['taxable_gain'])->toBe(2_400_000);  // 40% of (R100k-R40k)
        expect($r['tax_due'])->toBe(864_000);         // 36% of R24,000
    });

    it('uses the R300,000 death exclusion when on_death is set', function () {
        // R500,000 gain on death → exclusion R300,000 → net R200,000 →
        // 40% inclusion = R80,000 → at marginal 41% = R32,800.
        $r = $this->engine->calculateCGT(50_000_000, TAX_YEAR, [
            'on_death' => true,
            'marginal_rate' => 4100,
        ]);

        expect($r['exemption_used'])->toBe(30_000_000);
        expect($r['taxable_gain'])->toBe(8_000_000);
        expect($r['tax_due'])->toBe(3_280_000);
    });

    it('derives the marginal rate from annual income when supplied', function () {
        // R50,000 gain. Annual income R1,000,000 → bracket 6 (41%).
        // Taxable: (50k - 40k) × 40% = R4,000 → 41% = R1,640.
        $r = $this->engine->calculateCGT(5_000_000, TAX_YEAR, [
            'annual_income_minor' => 100_000_000,
            'age' => 45,
        ]);

        expect($r['tax_due'])->toBe(164_000);
        expect($r['breakdown']['rate_bps'])->toBe(4100);
    });
});

describe('FR-M3 retirement lump sums — cumulative', function () {
    it('matches SARS scenario 2 — R1m total cumulative retirement table', function () {
        // PRD § 4 scenario 2. R600k current + R400k prior. Cumulative table
        // tax on R1m = (0% × R550k) + (18% × R220k) + (27% × R230k)
        // = R39,600 + R62,100 = R101,700. Prior tax on withdrawal of
        // R400k treated with retirement table = (0% × R400k) = R0.
        // (The PRD conflates "prior withdrawal" with retirement table for
        // the demo — real Workstream 1.4 supplies the correct prior tax via
        // its own ledger. This test follows the PRD's retirement-only path.)
        $r = $this->engine->calculateLumpSumTax(
            amountMinor: 60_000_000,
            taxYear: TAX_YEAR,
            priorCumulativeMinor: 40_000_000,
            tableType: 'retirement',
        );

        expect($r['cumulative_tax_minor'])->toBe(10_170_000);
        expect($r['prior_tax_minor'])->toBe(0);  // R400k < R550k free band
        expect($r['tax_due_minor'])->toBe(10_170_000);
        expect($r['table_applied'])->toBe('retirement');
    });

    it('charges nothing on a first retirement lump sum up to R550,000', function () {
        $r = $this->engine->calculateLumpSumTax(55_000_000, TAX_YEAR, 0, 'retirement');
        expect($r['tax_due_minor'])->toBe(0);
    });

    it('uses the withdrawal table when tableType is withdrawal', function () {
        // R100,000 withdrawal. 0% × R27,500 + 18% × R72,500 = R13,050.
        $r = $this->engine->calculateLumpSumTax(10_000_000, TAX_YEAR, 0, 'withdrawal');
        expect($r['tax_due_minor'])->toBe(1_305_000);
        expect($r['table_applied'])->toBe('withdrawal');
    });

    it('nets prior-paid tax out correctly across cumulative', function () {
        // Prior cumulative R500k (already in 18% band), current R400k,
        // retirement table.
        // Total R900k in bracket 3: R39,600 + 27% × (900,000 - 770,000)
        // = R39,600 + R35,100 = R74,700.
        // Prior R500k: bracket 2: 18% × (500,000 - 550,000) = negative,
        // so bracket 1 (R0). Prior tax = R0.
        // So tax due = R74,700 - R0 = R74,700.
        $r = $this->engine->calculateLumpSumTax(40_000_000, TAX_YEAR, 50_000_000, 'retirement');
        expect($r['cumulative_tax_minor'])->toBe(7_470_000);
        expect($r['prior_tax_minor'])->toBe(0);
        expect($r['tax_due_minor'])->toBe(7_470_000);
    });
});

describe('FR-M3 Section 11F — retirement-fund deduction', function () {
    it('caps deduction at R350,000 absolute', function () {
        // Contributing R500,000 with no prior carry-forward.
        // Deductible = min(R500,000, R350,000) = R350,000.
        // Carry-forward out = R500,000 - R350,000 = R150,000.
        $r = $this->engine->calculateRetirementDeduction(50_000_000, TAX_YEAR, 0);

        expect($r['deductible_minor'])->toBe(35_000_000);
        expect($r['carry_forward_minor'])->toBe(15_000_000);
        expect($r['cap_applied_minor'])->toBe(35_000_000);
    });

    it('absorbs prior carry-forward toward this year\'s deduction', function () {
        // R100,000 contributed + R200,000 carry-forward = R300,000 available
        // → all deductible (below cap). Carry-forward out = R0.
        $r = $this->engine->calculateRetirementDeduction(10_000_000, TAX_YEAR, 20_000_000);

        expect($r['deductible_minor'])->toBe(30_000_000);
        expect($r['carry_forward_minor'])->toBe(0);
    });

    it('carries excess forward when combined total exceeds cap', function () {
        // R300,000 current + R200,000 carry-forward = R500,000 → cap R350k
        // → carry-forward out R150,000.
        $r = $this->engine->calculateRetirementDeduction(30_000_000, TAX_YEAR, 20_000_000);

        expect($r['deductible_minor'])->toBe(35_000_000);
        expect($r['carry_forward_minor'])->toBe(15_000_000);
    });
});

describe('FR-M3 DWT — local vs foreign', function () {
    it('withholds 20% on local dividends', function () {
        // R10,000 local dividend → R2,000 DWT.
        expect($this->engine->calculateDividendsWithholdingTax(1_000_000, TAX_YEAR, 'local'))
            ->toBe(200_000);
    });

    it('applies the 20% effective cap on foreign dividends', function () {
        expect($this->engine->calculateDividendsWithholdingTax(1_000_000, TAX_YEAR, 'foreign'))
            ->toBe(200_000);
    });

    it('rejects unsupported dividend sources', function () {
        expect(fn () => $this->engine->calculateDividendsWithholdingTax(1_000_000, TAX_YEAR, 'magic'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('FR-M3 medical credits', function () {
    it('pays the annual R4,512 credit for a single member + first dependant', function () {
        // R376/month × 2 × 12 = R9,024 for main + 1 dependant.
        expect($this->engine->calculateMedicalCredits(2, 0, TAX_YEAR))
            ->toBe(902_400);
    });

    it('adds R254/month per additional dependant', function () {
        // Main + 1 + 2 additional = R376×2×12 + R254×2×12 = R9,024 + R6,096 = R15,120.
        expect($this->engine->calculateMedicalCredits(2, 2, TAX_YEAR))
            ->toBe(1_512_000);
    });

    it('returns zero for no members', function () {
        expect($this->engine->calculateMedicalCredits(0, 0, TAX_YEAR))->toBe(0);
    });
});

describe('FR-M3 personal allowance — rebate-implied thresholds', function () {
    it('returns R99,000 under 65', function () {
        expect($this->engine->getPersonalAllowance(TAX_YEAR, 30))->toBe(9_900_000);
        // Age omitted defaults to the under-65 threshold.
        expect($this->engine->getPersonalAllowance(TAX_YEAR))->toBe(9_900_000);
    });

    it('returns R148,217 for 65-74', function () {
        expect($this->engine->getPersonalAllowance(TAX_YEAR, 67))->toBe(14_821_700);
    });

    it('returns R165,689 for 75+', function () {
        expect($this->engine->getPersonalAllowance(TAX_YEAR, 80))->toBe(16_568_900);
    });
});

describe('FR-M3 estate duty — spousal portability', function () {
    it('matches SARS scenario 4 first death — R5m dutiable → R300,000 duty', function () {
        // PRD § 4 scenario 4. R5m - R3.5m abatement = R1.5m @ 20% = R300,000.
        $r = $this->engine->calculateEstateDuty(500_000_000, TAX_YEAR, [
            'prior_spousal_abatement_used_minor' => 0,
        ]);

        expect($r['tax_due_minor'])->toBe(30_000_000);
        expect($r['abatement_applied_minor'])->toBe(350_000_000);
    });

    it('matches SARS scenario 4 second death — R4m dutiable with predecessor fully consuming own abatement → R100,000', function () {
        // PRD § 4 scenario 4 (amended per Estate Duty Act): available
        // abatement on second death = own R3.5m + (R3.5m - R3.5m used) =
        // R3.5m + R0 = R3.5m. R4m - R3.5m = R500k @ 20% = R100,000.
        $r = $this->engine->calculateEstateDuty(400_000_000, TAX_YEAR, [
            'has_predeceased_spouse' => true,
            'prior_spousal_abatement_used_minor' => 350_000_000,
        ]);

        expect($r['abatement_applied_minor'])->toBe(350_000_000);
        expect($r['tax_due_minor'])->toBe(10_000_000);
    });

    it('gives full portable abatement to a surviving spouse whose predecessor used none', function () {
        // If the predecessor used R0 of their R3.5m, the survivor gets
        // R3.5m (own) + R3.5m (portable) = R7m. R4m - R7m = R0 dutiable.
        $r = $this->engine->calculateEstateDuty(400_000_000, TAX_YEAR, [
            'has_predeceased_spouse' => true,
            'prior_spousal_abatement_used_minor' => 0,
        ]);

        expect($r['tax_due_minor'])->toBe(0);
        expect($r['portability_used_minor'])->toBe(350_000_000);
    });

    it('applies the 25% band for estates above R30m', function () {
        // R50m dutiable - R3.5m = R46.5m. R30m @ 20% = R6m, R16.5m @ 25% = R4.125m.
        // Total = R10.125m.
        $r = $this->engine->calculateEstateDuty(5_000_000_000, TAX_YEAR);
        expect($r['tax_due_minor'])->toBe(1_012_500_000);
    });
});

describe('FR-M3 donations tax — R30m cumulative threshold', function () {
    it('applies no tax on the first R100,000 annual exemption', function () {
        $r = $this->engine->calculateDonationsTax(10_000_000, TAX_YEAR, 0);
        expect($r['tax_due_minor'])->toBe(0);
        expect($r['annual_exemption_used_minor'])->toBe(10_000_000);
    });

    it('applies 20% above the annual exemption', function () {
        // R200,000 donation, R100,000 exempt, R100,000 taxable @ 20% = R20,000.
        $r = $this->engine->calculateDonationsTax(20_000_000, TAX_YEAR, 0);
        expect($r['tax_due_minor'])->toBe(2_000_000);
    });

    it('crosses into the 25% band above R30m cumulative', function () {
        // Prior cumulative R29m. Current R2m donation, R100,000 exempt → R1.9m taxable.
        // First R1m at 20% = R200,000, remaining R900k at 25% = R225,000.
        // Total = R425,000.
        $r = $this->engine->calculateDonationsTax(
            amountMinor: 200_000_000,
            taxYear: TAX_YEAR,
            cumulativeSince2018_03_01Minor: 2_900_000_000,
        );

        expect($r['tax_due_minor'])->toBe(42_500_000);
    });
});

describe('FR-M3 annual exemptions — sanity', function () {
    it('returns the expected exemptions map', function () {
        $e = $this->engine->getAnnualExemptions(TAX_YEAR);

        expect($e)->toHaveKey('cgt_annual_exclusion');
        expect($e['cgt_annual_exclusion'])->toBe(4_000_000);
        expect($e['cgt_death_exclusion'])->toBe(30_000_000);
        expect($e['interest_exemption_under_65'])->toBe(2_380_000);
        expect($e['interest_exemption_65_plus'])->toBe(3_450_000);
        expect($e['donations_annual_exemption'])->toBe(10_000_000);
    });
});

describe('Two-Pot savings-pot withdrawal (marginal-rate path, NOT withdrawal table)', function () {
    it('computes tax delta on a savings-pot withdrawal via income tax', function () {
        // PRD § 4 scenario 3. R450,000 baseline income → hypothetical
        // R500,000 with withdrawal. Delta is the marginal tax on R50,000.
        // R450k in bracket 3 (31%). R500k still in bracket 3.
        $baseline = $this->engine->calculateIncomeTaxForAge(45_000_000, TAX_YEAR, 40);
        $withWithdrawal = $this->engine->calculateIncomeTaxForAge(50_000_000, TAX_YEAR, 40);

        $delta = $withWithdrawal['tax_due'] - $baseline['tax_due'];

        // R50,000 × 31% (bracket 3 rate) = R15,500 = 1,550,000 cents.
        expect($delta)->toBe(1_550_000);
    });
});
