<?php

declare(strict_types=1);

use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('ZaPackServiceProvider — FR-M7', function () {
    it('registers ZA with the core PackRegistry', function () {
        /** @var PackRegistry $registry */
        $registry = app(PackRegistry::class);

        expect($registry->isEnabled('za'))->toBeTrue();
        expect($registry->byCountryCode('za')->code)->toBe('za');
        expect($registry->byCountryCode('za')->currency)->toBe('ZAR');
        expect($registry->byCountryCode('za')->tablePrefix)->toBe('za_');
    });

    it('binds pack.za.tax to ZaTaxEngine', function () {
        expect(app('pack.za.tax'))->toBeInstanceOf(ZaTaxEngine::class);
    });

    it('registers savings container bindings (WS 1.2a)', function () {
        expect(app('pack.za.savings'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Savings\ZaSavingsEngine::class)
            ->toBeInstanceOf(\Fynla\Core\Contracts\SavingsEngine::class);
        expect(app('pack.za.tfsa.tracker'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Savings\ZaTfsaContributionTracker::class);
        expect(app('pack.za.savings.emergency_fund'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator::class);
    });

    it('registers investment container bindings (WS 1.3a)', function () {
        expect(app('pack.za.investment'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Investment\ZaInvestmentEngine::class)
            ->toBeInstanceOf(\Fynla\Core\Contracts\InvestmentEngine::class);
        expect(app('pack.za.investment.cgt'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Investment\ZaCgtCalculator::class);
        expect(app('pack.za.investment.lot_tracker'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Investment\ZaBaseCostTracker::class);
    });

    it('registers exchange control container bindings (WS 1.3b)', function () {
        expect(app('pack.za.exchange_control'))
            ->toBeInstanceOf(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class)
            ->toBeInstanceOf(\Fynla\Core\Contracts\ExchangeControl::class);
        expect(app('pack.za.exchange_control.ledger'))
            ->toBeInstanceOf(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger::class);
    });

    it('registers retirement container bindings (WS 1.4a)', function () {
        expect(app('pack.za.retirement'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaRetirementEngine::class)
            ->toBeInstanceOf(\Fynla\Core\Contracts\RetirementEngine::class);
        expect(app('pack.za.retirement.contribution_split'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaContributionSplitService::class);
        expect(app('pack.za.retirement.savings_pot_simulator'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator::class);
        expect(app('pack.za.retirement.buckets'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository::class);
    });

    it('registers annuity container bindings (WS 1.4b)', function () {
        expect(app('pack.za.retirement.living_annuity'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator::class);
        expect(app('pack.za.retirement.life_annuity'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator::class);
        expect(app('pack.za.retirement.compulsory_annuitisation'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService::class);
    });

    it('registers Reg 28 monitor binding (WS 1.4c)', function () {
        expect(app('pack.za.reg28.monitor'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaReg28Monitor::class);
    });

    it('registers protection engine binding (WS 1.5)', function () {
        expect(app('pack.za.protection'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Protection\ZaProtectionEngine::class)
            ->toBeInstanceOf(\Fynla\Core\Contracts\ProtectionEngine::class);
    });

    it('registers estate engine binding (WS 1.6)', function () {
        expect(app('pack.za.estate'))
            ->toBeInstanceOf(\Fynla\Packs\Za\Estate\ZaEstateEngine::class)
            ->toBeInstanceOf(\Fynla\Core\Contracts\EstateEngine::class);
    });
});

describe('ZaTaxConfigurationSeeder — FR-M9 idempotency', function () {
    it('does not duplicate rows on a double run', function () {
        $this->seed(ZaTaxConfigurationSeeder::class);
        $firstCount = DB::table('za_tax_configurations')->count();

        $this->seed(ZaTaxConfigurationSeeder::class);
        $secondCount = DB::table('za_tax_configurations')->count();

        expect($firstCount)->toBe($secondCount);
        expect($firstCount)->toBeGreaterThan(0);
    });

    it('upserts updates — changed values replace in place rather than duplicating', function () {
        $this->seed(ZaTaxConfigurationSeeder::class);
        $countBefore = DB::table('za_tax_configurations')->count();

        // Simulate a mid-year SARS correction: tweak one row, re-run seeder
        // (which would revert it), and assert the row count is unchanged.
        DB::table('za_tax_configurations')
            ->where('tax_year', '2026/27')
            ->where('key_path', 'rebates.primary_minor')
            ->update(['value_cents' => 999_999]);

        $this->seed(ZaTaxConfigurationSeeder::class);

        $countAfter = DB::table('za_tax_configurations')->count();
        $value = DB::table('za_tax_configurations')
            ->where('tax_year', '2026/27')
            ->where('key_path', 'rebates.primary_minor')
            ->value('value_cents');

        expect($countAfter)->toBe($countBefore);
        expect((int) $value)->toBe(1_782_000);  // re-seeded correct value
    });

    it('creates exactly one ZA jurisdiction and one 2026/27 tax_years row', function () {
        $this->seed(ZaTaxConfigurationSeeder::class);
        $this->seed(ZaTaxConfigurationSeeder::class);

        expect(DB::table('jurisdictions')->where('code', 'ZA')->count())->toBe(1);
        expect(DB::table('tax_years')->where('label', '2026/27')->count())->toBe(1);
    });

    it('ensures accumulated base values match the SARS rate-derived totals exactly', function () {
        // KPI from PRD § 3:
        //   abs(accumulated_base_cents − round(derived_from_rate_and_lower_bound)) == 0
        //   for every bracket row.
        $this->seed(ZaTaxConfigurationSeeder::class);

        $brackets = [];
        $rows = DB::table('za_tax_configurations')
            ->where('tax_year', '2026/27')
            ->where('key_path', 'like', 'income_tax.brackets.%')
            ->get();

        foreach ($rows as $r) {
            // key_path is "income_tax.brackets.{i}.{field}"
            $parts = explode('.', $r->key_path);
            $i = (int) $parts[2];
            $field = $parts[3];
            $brackets[$i][$field] = (int) $r->value_cents;
        }

        ksort($brackets);
        $derived = 0;
        $previousLower = 0;
        $previousRateBps = 0;

        foreach ($brackets as $i => $bracket) {
            if ($i === 0) {
                expect($bracket['accumulated_base_minor'])->toBe(0);
            } else {
                $previousUpper = $brackets[$i - 1]['upper'];
                $previousLower = $brackets[$i - 1]['lower'];
                $previousRateBps = $brackets[$i - 1]['rate_bps'];
                $derived += intdiv(($previousUpper - $previousLower) * $previousRateBps, 10_000);

                expect($bracket['accumulated_base_minor'])
                    ->toBe($derived, "bracket {$i} accumulated_base drift");
            }
        }
    });
});
