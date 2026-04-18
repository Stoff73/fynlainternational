<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaReg28Monitor;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const REG28_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->monitor = app(ZaReg28Monitor::class);
});

describe('check', function () {
    it('returns compliant for an allocation within all limits', function () {
        $r = $this->monitor->check([
            'offshore' => 30.0,
            'equity' => 60.0,
            'property' => 5.0,
            'private_equity' => 2.0,
            'commodities' => 1.0,
            'hedge_funds' => 1.0,
            'other' => 1.0,
            'single_entity' => 15.0,
        ], REG28_TAX_YEAR);

        expect($r['compliant'])->toBeTrue();
        expect($r['breaches'])->toBe([]);
    });

    it('flags offshore breach at 46% (limit 45%)', function () {
        $r = $this->monitor->check([
            'offshore' => 46.0,
            'equity' => 60.0,
        ], REG28_TAX_YEAR);

        expect($r['compliant'])->toBeFalse();
        expect($r['breaches'])->toContain('offshore');
        expect($r['per_class']['offshore']['compliant'])->toBeFalse();
        expect($r['per_class']['offshore']['limit_pct'])->toBe(45.0);
    });

    it('flags multiple breaches in one call', function () {
        $r = $this->monitor->check([
            'offshore' => 50.0,
            'equity' => 80.0,
            'property' => 30.0,
        ], REG28_TAX_YEAR);

        expect($r['compliant'])->toBeFalse();
        expect($r['breaches'])->toHaveCount(3);
        expect($r['breaches'])->toContain('offshore', 'equity', 'property');
    });

    it('treats missing classes as zero (compliant)', function () {
        $r = $this->monitor->check(['offshore' => 10.0], REG28_TAX_YEAR);

        expect($r['compliant'])->toBeTrue();
        expect($r['per_class']['equity']['actual_pct'])->toBe(0.0);
    });

    it('flags single-entity breach at 26% (limit 25%)', function () {
        $r = $this->monitor->check(['single_entity' => 26.0], REG28_TAX_YEAR);

        expect($r['compliant'])->toBeFalse();
        expect($r['breaches'])->toContain('single_entity');
    });

    it('accepts the exact limit (boundary compliance)', function () {
        $r = $this->monitor->check([
            'offshore' => 45.0,
            'equity' => 75.0,
            'property' => 25.0,
        ], REG28_TAX_YEAR);

        expect($r['compliant'])->toBeTrue();
    });

    it('rejects negative allocation values', function () {
        expect(fn () => $this->monitor->check(['offshore' => -1.0], REG28_TAX_YEAR))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('snapshot', function () {
    it('persists a compliant snapshot', function () {
        $userClass = '\\' . 'App' . '\\Models\\User';
        $user = $userClass::factory()->create();

        $snap = $this->monitor->snapshot(
            userId: $user->id,
            fundHoldingId: null,
            allocation: ['offshore' => 30.0, 'equity' => 60.0],
            asAtDate: '2026-04-10',
            taxYear: REG28_TAX_YEAR,
        );

        expect($snap->compliant)->toBeTrue();
        expect($snap->breaches)->toBe([]);
        expect($snap->offshore_compliant)->toBeTrue();
        // JSON round-trip may strip trailing .0 from integer-valued floats.
        expect((float) $snap->allocation['offshore'])->toBe(30.0);
    });

    it('persists a non-compliant snapshot with breach list', function () {
        $userClass = '\\' . 'App' . '\\Models\\User';
        $user = $userClass::factory()->create();

        $snap = $this->monitor->snapshot(
            $user->id, null,
            ['offshore' => 50.0, 'equity' => 80.0],
            '2026-04-10',
            REG28_TAX_YEAR,
        );

        expect($snap->compliant)->toBeFalse();
        expect($snap->breaches)->toContain('offshore', 'equity');
        expect($snap->offshore_compliant)->toBeFalse();
        expect($snap->equity_compliant)->toBeFalse();
    });
});
