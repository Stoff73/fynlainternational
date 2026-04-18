# WS 1.4b — SA Annuity Mechanics Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ship the SA annuity mechanics backend — living annuity drawdown band (2.5%–17.5%), life annuity with Section 10C exemption, compulsory annuitisation rules (1/3 PCLS + 2/3 annuity with R165k de minimis), all composing existing `ZaTaxEngine`/`ZaSection10cTracker`/`ZaRetirementFundBucketRepository`. No UI.

**Architecture:**
- `ZaLivingAnnuityCalculator` — pure calculator. Given capital + drawdown %, validates 2.5–17.5% band, computes gross annual income, composes `ZaTaxEngine::calculateIncomeTaxForAge` for net-of-tax income at marginal rate.
- `ZaLifeAnnuityCalculator` — pure calculator. Given annual annuity income + non-deductible-contribution pool (from `ZaSection10cTracker`), computes Section 10C exemption slice + taxable slice + marginal tax.
- `ZaCompulsoryAnnuitisationService` — applies the at-retirement rules. Reads `ZaRetirementFundBucket` (vested + savings + retirement + provident_vested_pre2021), returns the allowed split: full commutation if total ≤ R165k, else 1/3 PCLS + 2/3 annuity on vested; retirement bucket MUST annuitise regardless; provident-pre-2021 portion 100% commutable.
- Three new `retirement.annuity.*` seeder rows: drawdown band lower (250 bps), upper (1750 bps), de minimis threshold (R165,000).
- Container keys: `pack.za.retirement.living_annuity`, `pack.za.retirement.life_annuity`, `pack.za.retirement.compulsory_annuitisation`.

**Tech Stack:** Laravel 10, PHP 8.2, Pest v2, MySQL 8.

**Out of scope:**
- Annuity purchase workflow (transfers from retirement fund to insurer)
- Beneficiary nomination ledger
- Cash-lump-sum-on-death composition (uses `calculateLumpSumTax` — future compose)
- Vue components (WS 1.4d)
- Actual annuity product catalogue / provider data

---

## File Structure

**New (ZA pack):**
- `packs/country-za/src/Retirement/ZaLivingAnnuityCalculator.php`
- `packs/country-za/src/Retirement/ZaLifeAnnuityCalculator.php`
- `packs/country-za/src/Retirement/ZaCompulsoryAnnuitisationService.php`
- `packs/country-za/tests/Unit/ZaLivingAnnuityCalculatorTest.php`
- `packs/country-za/tests/Unit/ZaLifeAnnuityCalculatorTest.php`
- `packs/country-za/tests/Unit/ZaCompulsoryAnnuitisationServiceTest.php`

**Modified:**
- `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php` — add annuity rows
- `packs/country-za/src/Providers/ZaPackServiceProvider.php` — 3 bindings
- `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php` — binding assertions

---

## Task 1: Seeder rows

- [ ] **Step 1: Add annuity method + wire it in**

In `ZaTaxConfigurationSeeder.php`, add after `retirementRows()`:

```php
/**
 * Annuity mechanics (WS 1.4b).
 *   - Living annuity drawdown band 2.5%–17.5% of capital (Regulation 39)
 *   - Compulsory annuitisation de minimis: R165,000 combined total at
 *     retirement allows full commutation
 *
 * @return array<int, array{0: string, 1: int, 2: ?string}>
 */
private function annuityRows(): array
{
    return [
        ['annuity.living.drawdown_min_bps', 250, '2.5% minimum living annuity drawdown'],
        ['annuity.living.drawdown_max_bps', 1750, '17.5% maximum living annuity drawdown'],
        ['annuity.de_minimis_threshold_minor', 16_500_000, 'R165,000 full-commutation threshold at retirement'],
    ];
}
```

And add `$this->annuityRows(),` to the `rows()` `array_merge`.

- [ ] **Step 2: Re-seed and verify**

```bash
php artisan db:seed --class="Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder" --force
php artisan tinker --execute="echo DB::table('za_tax_configurations')->where('key_path','like','annuity.%')->count();"
```

Expected: `3`.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php
git commit -m "feat(za-pack): seed annuity drawdown band + de minimis (WS 1.4b)"
```

---

## Task 2: ZaLivingAnnuityCalculator (tests + impl)

- [ ] **Step 1: Failing test**

Write `packs/country-za/tests/Unit/ZaLivingAnnuityCalculatorTest.php`:

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LIVING_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaLivingAnnuityCalculator::class);
});

it('computes gross and net drawdown for a 5% election on R2m capital', function () {
    // R2,000,000 capital @ 5% = R100,000 gross annuity.
    // At R100,000 sole income, age 65, applies secondary rebate.
    $r = $this->calc->calculate(
        capitalMinor: 200_000_000,
        drawdownRateBps: 500,  // 5.00%
        age: 65,
        taxYear: LIVING_TAX_YEAR,
    );

    expect($r['gross_annual_minor'])->toBe(10_000_000);
    expect($r['drawdown_rate_bps'])->toBe(500);
    expect($r['tax_due_minor'])->toBe(0);  // under 65+ threshold
    expect($r['net_annual_minor'])->toBe(10_000_000);
});

it('rejects drawdown below 2.5%', function () {
    expect(fn () => $this->calc->calculate(200_000_000, 200, 65, LIVING_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class, 'drawdown');
});

it('rejects drawdown above 17.5%', function () {
    expect(fn () => $this->calc->calculate(200_000_000, 1800, 65, LIVING_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class, 'drawdown');
});

it('accepts exact boundary values 2.5% and 17.5%', function () {
    $min = $this->calc->calculate(200_000_000, 250, 65, LIVING_TAX_YEAR);
    $max = $this->calc->calculate(200_000_000, 1750, 65, LIVING_TAX_YEAR);

    expect($min['gross_annual_minor'])->toBe(5_000_000);
    expect($max['gross_annual_minor'])->toBe(35_000_000);
});

it('composes marginal tax for a high-drawdown case', function () {
    // R3m capital @ 15% = R450,000 — well into bracket 3 (31% marginal).
    $r = $this->calc->calculate(300_000_000, 1500, 65, LIVING_TAX_YEAR);

    expect($r['gross_annual_minor'])->toBe(45_000_000);
    expect($r['tax_due_minor'])->toBeGreaterThan(0);
    expect($r['net_annual_minor'])->toBe($r['gross_annual_minor'] - $r['tax_due_minor']);
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaLivingAnnuityCalculatorTest.php
```

- [ ] **Step 3: Write the calculator**

`packs/country-za/src/Retirement/ZaLivingAnnuityCalculator.php`:

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * Living annuity drawdown calculator.
 *
 * Regulation 39 constrains living annuity drawdown to 2.5%–17.5% of
 * capital, elected once per policy anniversary. No Reg 28 restriction
 * applies inside a living annuity — the member may hold up to 100%
 * offshore if the LISP allows (spec § 9.4).
 *
 * Annuity income is taxed at the member's marginal rate. This
 * calculator assumes the drawdown is the member's ONLY taxable income
 * for tax-computation purposes; callers who know other income should
 * pre-aggregate and call ZaTaxEngine directly.
 */
class ZaLivingAnnuityCalculator
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     gross_annual_minor: int,
     *     tax_due_minor: int,
     *     net_annual_minor: int,
     *     drawdown_rate_bps: int,
     *     marginal_rate: float
     * }
     */
    public function calculate(
        int $capitalMinor,
        int $drawdownRateBps,
        int $age,
        string $taxYear,
    ): array {
        if ($capitalMinor < 0) {
            throw new InvalidArgumentException('Capital cannot be negative.');
        }

        $minBps = (int) $this->config->get($taxYear, 'annuity.living.drawdown_min_bps', 250);
        $maxBps = (int) $this->config->get($taxYear, 'annuity.living.drawdown_max_bps', 1750);

        if ($drawdownRateBps < $minBps || $drawdownRateBps > $maxBps) {
            throw new InvalidArgumentException(
                "Living annuity drawdown {$drawdownRateBps} bps outside the {$minBps}-{$maxBps} band.",
            );
        }

        $gross = intdiv($capitalMinor * $drawdownRateBps, 10_000);
        $taxResult = $this->taxEngine->calculateIncomeTaxForAge($gross, $taxYear, $age);

        return [
            'gross_annual_minor' => $gross,
            'tax_due_minor' => $taxResult['tax_due'],
            'net_annual_minor' => $gross - $taxResult['tax_due'],
            'drawdown_rate_bps' => $drawdownRateBps,
            'marginal_rate' => (float) $taxResult['marginal_rate'],
        ];
    }
}
```

- [ ] **Step 4: Run — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaLivingAnnuityCalculatorTest.php
```

Expected: 5 passing.

- [ ] **Step 5: Commit**

```bash
git add packs/country-za/src/Retirement/ZaLivingAnnuityCalculator.php \
        packs/country-za/tests/Unit/ZaLivingAnnuityCalculatorTest.php
git commit -m "feat(za-pack): ZaLivingAnnuityCalculator with 2.5-17.5% band (WS 1.4b)"
```

---

## Task 3: ZaLifeAnnuityCalculator (tests + impl)

- [ ] **Step 1: Failing test**

`packs/country-za/tests/Unit/ZaLifeAnnuityCalculatorTest.php`:

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LIFE_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaLifeAnnuityCalculator::class);
});

it('applies Section 10C exemption up to the non-deductible pool', function () {
    // R100,000 annuity income, R40,000 non-deductible pool (so R40k
    // is Section 10C exempt). Taxable = R60,000.
    $r = $this->calc->calculate(
        annualAnnuityMinor: 10_000_000,
        section10cPoolMinor: 4_000_000,
        age: 65,
        taxYear: LIFE_TAX_YEAR,
    );

    expect($r['section_10c_exempt_minor'])->toBe(4_000_000);
    expect($r['taxable_minor'])->toBe(6_000_000);
    expect($r['section_10c_remaining_pool_minor'])->toBe(0);
    expect($r['pool_exhausted'])->toBeTrue();
});

it('consumes only the annuity amount when pool exceeds annual income', function () {
    $r = $this->calc->calculate(10_000_000, 50_000_000, 65, LIFE_TAX_YEAR);

    expect($r['section_10c_exempt_minor'])->toBe(10_000_000);
    expect($r['taxable_minor'])->toBe(0);
    expect($r['section_10c_remaining_pool_minor'])->toBe(40_000_000);
    expect($r['pool_exhausted'])->toBeFalse();
    expect($r['tax_due_minor'])->toBe(0);
});

it('returns zero exempt when pool is zero', function () {
    $r = $this->calc->calculate(10_000_000, 0, 40, LIFE_TAX_YEAR);

    expect($r['section_10c_exempt_minor'])->toBe(0);
    expect($r['taxable_minor'])->toBe(10_000_000);
});

it('rejects negative inputs', function () {
    expect(fn () => $this->calc->calculate(-1, 0, 40, LIFE_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Implement**

`packs/country-za/src/Retirement/ZaLifeAnnuityCalculator.php`:

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * Life (guaranteed) annuity calculator.
 *
 * Annuity income is taxable at the member's marginal rate, with a
 * Section 10C exemption for the component attributable to non-deductible
 * contributions (spec § 9.5). The exempt slice is drawn from a running
 * "non-deductible pool" that the caller maintains via ZaSection10cTracker
 * across years.
 *
 * This calculator is stateless — callers fetch the pool, call calculate,
 * and persist the remaining pool. See the integration test for the
 * composition pattern.
 */
class ZaLifeAnnuityCalculator
{
    public function __construct(
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     section_10c_exempt_minor: int,
     *     section_10c_remaining_pool_minor: int,
     *     pool_exhausted: bool,
     *     taxable_minor: int,
     *     tax_due_minor: int,
     *     marginal_rate: float
     * }
     */
    public function calculate(
        int $annualAnnuityMinor,
        int $section10cPoolMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($annualAnnuityMinor < 0 || $section10cPoolMinor < 0 || $age < 0) {
            throw new InvalidArgumentException('Life annuity inputs cannot be negative.');
        }

        $exempt = min($annualAnnuityMinor, $section10cPoolMinor);
        $taxable = $annualAnnuityMinor - $exempt;
        $remaining = $section10cPoolMinor - $exempt;

        $taxResult = $taxable > 0
            ? $this->taxEngine->calculateIncomeTaxForAge($taxable, $taxYear, $age)
            : ['tax_due' => 0, 'marginal_rate' => 0.0];

        return [
            'section_10c_exempt_minor' => $exempt,
            'section_10c_remaining_pool_minor' => $remaining,
            'pool_exhausted' => $remaining === 0 && $section10cPoolMinor > 0,
            'taxable_minor' => $taxable,
            'tax_due_minor' => $taxResult['tax_due'],
            'marginal_rate' => (float) $taxResult['marginal_rate'],
        ];
    }
}
```

- [ ] **Step 3: Run — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaLifeAnnuityCalculatorTest.php
```

Expected: 4 passing.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/src/Retirement/ZaLifeAnnuityCalculator.php \
        packs/country-za/tests/Unit/ZaLifeAnnuityCalculatorTest.php
git commit -m "feat(za-pack): ZaLifeAnnuityCalculator with Section 10C exemption (WS 1.4b)"
```

---

## Task 4: ZaCompulsoryAnnuitisationService (tests + impl)

- [ ] **Step 1: Failing test**

`packs/country-za/tests/Unit/ZaCompulsoryAnnuitisationServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ANNUIT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->svc = app(ZaCompulsoryAnnuitisationService::class);
});

it('allows full commutation when total is under the R165k de minimis', function () {
    $r = $this->svc->apportion(
        vestedMinor: 10_000_000,  // R100,000
        providentVestedPre2021Minor: 0,
        retirementMinor: 5_000_000,  // R50,000
        taxYear: ANNUIT_TAX_YEAR,
    );

    // Total R150k < R165k → full commutation allowed.
    expect($r['pcls_minor'])->toBe(15_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(0);
    expect($r['de_minimis_applied'])->toBeTrue();
});

it('splits 1/3 PCLS + 2/3 compulsory on vested, locks retirement bucket regardless', function () {
    // R300k vested + R600k retirement = R900k (well above de minimis).
    // Two-Pot rule: retirement_balance always annuitises. Vested splits 1/3 PCLS.
    $r = $this->svc->apportion(
        vestedMinor: 30_000_000,
        providentVestedPre2021Minor: 0,
        retirementMinor: 60_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    // 1/3 of vested = R100k PCLS
    // 2/3 of vested = R200k annuity + R600k retirement = R800k annuity
    expect($r['pcls_minor'])->toBe(10_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(80_000_000);
    expect($r['de_minimis_applied'])->toBeFalse();
});

it('adds provident-pre-2021 100% commutable portion to PCLS', function () {
    // R200k provident-pre-2021 (100% commutable) + R300k vested + R300k retirement.
    // Total R800k — above de minimis.
    // Vested splits 1/3–2/3: R100k PCLS + R200k annuity
    // Provident-pre-2021: R200k to PCLS (100% commutable)
    // Retirement: R300k to annuity
    // Total PCLS: R300k. Total annuity: R500k.
    $r = $this->svc->apportion(
        vestedMinor: 30_000_000,
        providentVestedPre2021Minor: 20_000_000,
        retirementMinor: 30_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    expect($r['pcls_minor'])->toBe(30_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(50_000_000);
});

it('honours retirement-bucket-never-commutes even when overall is under de minimis', function () {
    // Total R100k under de minimis, but retirement bucket alone is R50k.
    // Spec: "Retirement Component of Two-Pot cannot be commuted regardless
    // of overall size once the two-pot regime applies — it must buy an
    // annuity."
    // Vested R50k can be commuted (under de minimis).
    $r = $this->svc->apportion(
        vestedMinor: 5_000_000,
        providentVestedPre2021Minor: 0,
        retirementMinor: 5_000_000,
        taxYear: ANNUIT_TAX_YEAR,
    );

    // De minimis applies to vested + pre-2021, NOT to retirement bucket.
    // PCLS: R50k vested. Annuity: R50k retirement.
    expect($r['pcls_minor'])->toBe(5_000_000);
    expect($r['compulsory_annuity_minor'])->toBe(5_000_000);
    expect($r['de_minimis_applied'])->toBeTrue();
});

it('returns zero when all buckets are zero', function () {
    $r = $this->svc->apportion(0, 0, 0, ANNUIT_TAX_YEAR);

    expect($r['pcls_minor'])->toBe(0);
    expect($r['compulsory_annuity_minor'])->toBe(0);
});
```

- [ ] **Step 2: Implement**

`packs/country-za/src/Retirement/ZaCompulsoryAnnuitisationService.php`:

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use InvalidArgumentException;

/**
 * At-retirement compulsory annuitisation service.
 *
 * Applies three SA rules in precedence:
 *   1. Two-Pot retirement bucket ALWAYS annuitises, regardless of total.
 *   2. Provident-pre-2021 balance is 100% commutable to PCLS for members
 *      55+ on 1 March 2021 (spec § 9.1). Counts to the lump-sum cap.
 *   3. If the commutable subset (vested + provident_pre2021) total is
 *      under the R165k de minimis threshold, it may be fully commuted.
 *      Otherwise: 1/3 PCLS, 2/3 annuity on the commutable subset.
 *
 * Retirement bucket is never included in the de minimis calculation —
 * it always annuitises per Two-Pot rules.
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
            // Full commutation of commutable subset; retirement bucket
            // still annuitises.
            $pcls = $commutableBase;
            $compAnnuity = $retirementMinor;
        } else {
            // Provident-pre-2021 is always 100% commutable.
            // Vested splits 1/3 PCLS + 2/3 annuity.
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
```

- [ ] **Step 3: Run — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaCompulsoryAnnuitisationServiceTest.php
```

Expected: 5 passing.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/src/Retirement/ZaCompulsoryAnnuitisationService.php \
        packs/country-za/tests/Unit/ZaCompulsoryAnnuitisationServiceTest.php
git commit -m "feat(za-pack): ZaCompulsoryAnnuitisationService 1/3+2/3+de-minimis (WS 1.4b)"
```

---

## Task 5: Provider bindings

- [ ] **Step 1: Add to `ZaPackServiceProvider::register()`**

```php
// WS 1.4b — Annuity mechanics
$this->app->bind(
    'pack.za.retirement.living_annuity',
    \Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator::class,
);
$this->app->bind(
    'pack.za.retirement.life_annuity',
    \Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator::class,
);
$this->app->bind(
    'pack.za.retirement.compulsory_annuitisation',
    \Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService::class,
);
```

- [ ] **Step 2: Add provider test assertion**

```php
it('registers annuity container bindings (WS 1.4b)', function () {
    expect(app('pack.za.retirement.living_annuity'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator::class);
    expect(app('pack.za.retirement.life_annuity'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator::class);
    expect(app('pack.za.retirement.compulsory_annuitisation'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService::class);
});
```

- [ ] **Step 3: Run + commit**

```bash
./vendor/bin/pest packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
git add packs/country-za/src/Providers/ZaPackServiceProvider.php \
        packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
git commit -m "feat(za-pack): register annuity calculator bindings (WS 1.4b)"
```

---

## Task 6: Full regression

```bash
./vendor/bin/pest
```

Expected: +14 tests net (5 living + 4 life + 5 compulsory). 0 new failures.
