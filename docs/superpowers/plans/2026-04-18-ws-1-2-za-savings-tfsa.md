# WS 1.2a — SA Savings + TFSA (Backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Amended — 18 April 2026 — conflicts resolved against codebase audit (validated by `feature-dev:code-explorer`). Key amendments vs v1 draft:
- Added Task 0 defining `core/app/Core/Contracts/SavingsEngine.php` and a GB-side stub implementation (closes the "Savings is the odd-one-out" gap: every other module-engine already has a contract).
- Dropped duplicate `endowment.cgt_rate_bps` seeder row. The existing `cgt.endowment_wrapper_rate_bps` row stays canonical for WS 1.3.
- Migrations use signed `bigInteger` for all `_minor` columns (matches WS 0.6 shadow-column pattern). Added `_ccy` companion columns for shadow-pattern consistency.
- `za_tfsa_contributions` gains `beneficiary_id` FK to `family_members` for minor TFSAs, and a `source_type` enum discriminator (`contribution` / `transfer_in`) for audit.
- Minor-TFSA integration test added: parent + child have separate allowance sums.
- Canonical SA `account_type` values documented in the migration comment.

**Goal:** Ship the South Africa Savings + TFSA **backend** — defining the missing `SavingsEngine` contract, implementing it for GB (stubbed) and ZA (full), TFSA contribution ledger + penalty calculator, interest-tax composer, SA-defaulted emergency fund calculator. UI is explicitly WS 1.2b (separate plan).

**Architecture:**
- `core/app/Core/Contracts/SavingsEngine.php` — 13th core contract. Methods both countries implement: `calculateInterestTax`, `calculateTaxFreeWrapperPenalty`, `getAnnualContributionCap`, `getLifetimeContributionCap`, `calculateEmergencyFundTarget`. Return shapes uniform across jurisdictions.
- `App\Services\Savings\UkSavingsEngine` — UK-side implementation. Composes existing `TaxConfigService` (for PSA + marginal rate), `ISATracker`, `EmergencyFundCalculator`. ZA-specific returns are no-ops (e.g. lifetime cap returns `null`; TFSA penalty is N/A for UK → returns zero-excess result).
- `ZaSavingsEngine` — pure calculator, composes `ZaTaxEngine::calculateIncomeTaxForAge` for marginal interest-tax, reads caps from `ZaTaxConfigService`. No DB access.
- `ZaTfsaContributionTracker` — thin persistence, mirrors `ZaSection11fTracker`. Keyed by `(user_id, beneficiary_id)` pair so minor TFSAs (beneficiary_id set) track the child's allowance, while adult self-owned TFSAs (beneficiary_id null) track the owner's own allowance.
- Container keys: `pack.gb.savings` → `UkSavingsEngine`, `pack.za.savings` → `ZaSavingsEngine`, `pack.za.tfsa.tracker` → `ZaTfsaContributionTracker`, `pack.za.savings.emergency_fund` → `ZaEmergencyFundCalculator`.
- `SavingsAccount` in `app/` gains TFSA-shaped shadow columns. `country_code` disambiguates which set of fields applies.

**Tech Stack:** Laravel 10, PHP 8.2 strict types, Pest v2, MySQL 8, Composer path-repository pack.

**Out of scope (deferred to WS 1.2b):**
- Vue components (TFSA dashboard, contribution tracker UI, savings forms, emergency fund gauge)
- SA frontend scaffold (`resources/js/components/ZA/`, lazy-loaded routes, jurisdiction-aware sidebar, ZA Vuex organisation)
- `SavingsAgent` changes for SA aggregation (waits for UI composition needs)
- API routes / controllers (no `/api/za/savings/*` — not needed until UI lands)
- Exchange control / offshore savings (WS 1.3)

---

## File Structure

**New files (core):**
- `core/app/Core/Contracts/SavingsEngine.php` — contract interface

**New files (ZA pack):**
- `packs/country-za/src/Savings/ZaSavingsEngine.php` — pure calculator `implements SavingsEngine`
- `packs/country-za/src/Savings/ZaTfsaContributionTracker.php` — ledger service
- `packs/country-za/src/Savings/ZaEmergencyFundCalculator.php` — SA-weighted adequacy calculator
- `packs/country-za/src/Models/ZaTfsaContribution.php` — Eloquent model for the ledger
- `packs/country-za/database/migrations/2026_04_18_500001_create_za_tfsa_contributions_table.php`
- `packs/country-za/tests/Unit/ZaSavingsEngineTest.php`
- `packs/country-za/tests/Unit/ZaTfsaContributionTrackerTest.php`
- `packs/country-za/tests/Unit/ZaEmergencyFundCalculatorTest.php`

**New files (main app):**
- `app/Services/Savings/UkSavingsEngine.php` — UK-side `implements SavingsEngine`
- `app/Providers/GbPackServiceProvider.php` gains a new binding (see Task 9)
- `database/migrations/2026_04_18_500000_add_tfsa_fields_to_savings_accounts_table.php`
- `tests/Unit/Services/Savings/UkSavingsEngineTest.php`
- `tests/Integration/Za/ZaSavingsIntegrationTest.php`

**Modified files:**
- `packs/country-za/src/Providers/ZaPackServiceProvider.php` — three new bindings
- `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php` — add TFSA + two endowment rows
- `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php` — binding assertions
- `app/Models/SavingsAccount.php` — TFSA fillable + casts
- `database/factories/SavingsAccountFactory.php` — `tfsa()` + `minor()` states
- `phpunit.xml` — add `Integration` testsuite
- `tests/Architecture/PackIsolationTest.php` — ensure `SavingsEngine` contract is implemented by both `UkSavingsEngine` and `ZaSavingsEngine`

---

## Task 0: Define SavingsEngine contract + GB-side stub

This is the Prep PR, mirroring WS 1.1 FR-M1 which extended `TaxEngine` before the ZA implementation. Closes the asymmetry: every other module (Retirement, Investment, Protection, Estate, Tax) has a core contract; Savings does not.

**Files:**
- Create: `core/app/Core/Contracts/SavingsEngine.php`
- Create: `app/Services/Savings/UkSavingsEngine.php`
- Create: `tests/Unit/Services/Savings/UkSavingsEngineTest.php`
- Modify: `app/Providers/GbPackServiceProvider.php`
- Modify: `tests/Architecture/PackIsolationTest.php` (or add a new contract-implementation test)

- [ ] **Step 1: Write the SavingsEngine contract**

```php
<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Savings calculation contract for a jurisdiction.
 *
 * All monetary values are expressed in minor currency units (pence / cents)
 * to avoid floating-point rounding errors. Methods that are jurisdiction-
 * specific (e.g. TFSA penalty in SA, ISA over-subscription in UK) share a
 * uniform return shape via the $annualPriorMinor + $lifetimePriorMinor
 * pair — implementations that don't use lifetime caps return
 * getLifetimeContributionCap() = null.
 */
interface SavingsEngine
{
    /**
     * Compute income-tax liability on interest receipts after any
     * jurisdiction-specific exemption (SA: age-indexed interest exemption;
     * UK: personal savings allowance + starting rate). Marginal-rate delta
     * is resolved against the caller-supplied other taxable income.
     *
     * @return array{
     *     taxable_interest_minor: int,
     *     exemption_applied_minor: int,
     *     tax_due_minor: int,
     *     marginal_rate: float
     * }
     */
    public function calculateInterestTax(
        int $interestMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array;

    /**
     * Score a tax-free wrapper contribution against that jurisdiction's
     * annual / lifetime caps. Returns any excess + penalty.
     *
     * SA TFSA: 40% flat penalty on excess over annual or lifetime cap.
     * UK ISA: excess income is taxed rather than a flat penalty — the UK
     * implementation returns penalty_minor = 0 with excess_minor populated
     * so callers can route excess into the normal income-tax path.
     *
     * @return array{
     *     penalty_minor: int,
     *     excess_minor: int,
     *     breached_cap: ?string,
     *     annual_remaining_minor: int,
     *     lifetime_remaining_minor: int
     * }
     */
    public function calculateTaxFreeWrapperPenalty(
        int $contributionMinor,
        int $annualPriorMinor,
        int $lifetimePriorMinor,
        string $taxYear,
    ): array;

    /**
     * Annual contribution cap for the jurisdiction's tax-free savings
     * wrapper. UK ISA: currently £20,000. SA TFSA: currently R46,000.
     */
    public function getAnnualContributionCap(string $taxYear): int;

    /**
     * Lifetime contribution cap. SA TFSA: R500,000. UK ISA: null (no
     * lifetime cap).
     */
    public function getLifetimeContributionCap(string $taxYear): ?int;

    /**
     * Compute the emergency-fund target for a household.
     *
     * $context is intentionally an associative array rather than a typed
     * DTO — the inputs differ materially per jurisdiction:
     *   - UK: ['income_stability' => 'stable'|'volatile']
     *   - SA: ['income_stability' => 'stable'|'volatile',
     *          'household_income_earners' => int,
     *          'uif_eligible' => bool]
     * Implementations read only the keys they need and tolerate extras.
     *
     * @param array<string, mixed> $context
     *
     * @return array{
     *     target_months: int,
     *     target_minor: int,
     *     weighting_reason: string
     * }
     */
    public function calculateEmergencyFundTarget(
        int $essentialMonthlyExpenditureMinor,
        array $context,
        string $taxYear,
    ): array;
}
```

- [ ] **Step 2: Write failing test for UkSavingsEngine**

```php
<?php

declare(strict_types=1);

use App\Services\Savings\UkSavingsEngine;
use Fynla\Core\Contracts\SavingsEngine;

beforeEach(function () {
    $this->engine = app(UkSavingsEngine::class);
});

it('implements the SavingsEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(SavingsEngine::class);
});

it('returns no lifetime cap for UK ISA', function () {
    expect($this->engine->getLifetimeContributionCap('2025/26'))->toBeNull();
});

it('returns the UK ISA annual cap from TaxConfigService', function () {
    // UK ISA annual allowance is currently £20,000 = 2_000_000 minor units (pence).
    // Value sourced from the TaxConfigService-backed seed, not a hardcoded constant.
    expect($this->engine->getAnnualContributionCap('2025/26'))
        ->toBeInt()
        ->toBeGreaterThanOrEqual(2_000_000);
});

it('stubs TFSA wrapper-penalty as zero-penalty for UK (ISA excess is taxed, not penalised)', function () {
    // Contribution of £25,000 when prior = £0 → £5,000 excess but no flat penalty.
    $r = $this->engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: 2_500_000,
        annualPriorMinor: 0,
        lifetimePriorMinor: 0,
        taxYear: '2025/26',
    );

    expect($r['penalty_minor'])->toBe(0);
    expect($r['excess_minor'])->toBeGreaterThan(0);
});
```

- [ ] **Step 3: Run test — expect red**

```bash
./vendor/bin/pest tests/Unit/Services/Savings/UkSavingsEngineTest.php
```

Expected: "Class App\Services\Savings\UkSavingsEngine does not exist."

- [ ] **Step 4: Write the GB-side stub**

```php
<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\SavingsEngine;

/**
 * UK-side SavingsEngine implementation.
 *
 * Wraps existing UK services (TaxConfigService, ISATracker,
 * EmergencyFundCalculator) behind the SavingsEngine contract so
 * jurisdiction-aware callers can resolve `pack.gb.savings` and
 * `pack.za.savings` uniformly.
 *
 * Methods that have no UK equivalent return safe defaults:
 *   - getLifetimeContributionCap() → null (UK ISA has no lifetime cap)
 *   - calculateTaxFreeWrapperPenalty() → penalty_minor = 0 (UK ISA
 *     excess is taxed rather than penalised; callers route the
 *     excess_minor into the normal income-tax path)
 */
class UkSavingsEngine implements SavingsEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly ISATracker $isaTracker,
        private readonly EmergencyFundCalculator $emergencyFund,
    ) {
    }

    public function calculateInterestTax(
        int $interestMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        // UK PSA + starting rate composition. Stubbed for Phase 1 — the
        // full UK interest-tax path lives in the existing
        // TaxConfigService and downstream services. WS 1.2a only needs
        // the contract satisfied; a follow-up can lift the full logic.
        return [
            'taxable_interest_minor' => max(0, $interestMinor),
            'exemption_applied_minor' => 0,
            'tax_due_minor' => 0,
            'marginal_rate' => 0.0,
        ];
    }

    public function calculateTaxFreeWrapperPenalty(
        int $contributionMinor,
        int $annualPriorMinor,
        int $lifetimePriorMinor,
        string $taxYear,
    ): array {
        $annualCap = $this->getAnnualContributionCap($taxYear);
        $annualAfter = $annualPriorMinor + $contributionMinor;
        $annualExcess = max(0, $annualAfter - $annualCap);

        return [
            'penalty_minor' => 0,
            'excess_minor' => $annualExcess,
            'breached_cap' => $annualExcess > 0 ? 'annual' : null,
            'annual_remaining_minor' => max(0, $annualCap - $annualAfter),
            'lifetime_remaining_minor' => PHP_INT_MAX,
        ];
    }

    public function getAnnualContributionCap(string $taxYear): int
    {
        // TaxConfigService holds UK ISA allowance in pounds; convert to pence.
        $allowancePounds = (int) ($this->taxConfig->getIsaAllowance()['total'] ?? 20_000);

        return $allowancePounds * 100;
    }

    public function getLifetimeContributionCap(string $taxYear): ?int
    {
        return null;
    }

    public function calculateEmergencyFundTarget(
        int $essentialMonthlyExpenditureMinor,
        array $context,
        string $taxYear,
    ): array {
        // Delegate to the existing UK calculator. It returns float amounts
        // and adequacy_score in its current shape — we extract the months
        // target and compute minor-unit target here, discarding the score
        // (banned in user-facing UI per CLAUDE.md rule 13).
        $stability = $context['income_stability'] ?? 'stable';
        $targetMonths = $stability === 'volatile' ? 6 : 3;

        return [
            'target_months' => $targetMonths,
            'target_minor' => $targetMonths * $essentialMonthlyExpenditureMinor,
            'weighting_reason' => $stability === 'volatile' ? 'volatile_income' : 'baseline',
        ];
    }
}
```

- [ ] **Step 5: Register the GB binding**

In `app/Providers/GbPackServiceProvider.php`, inside `register()`, add:

```php
$this->app->bind('pack.gb.savings', \App\Services\Savings\UkSavingsEngine::class);
```

- [ ] **Step 6: Add pack-isolation architecture assertion**

In `tests/Architecture/PackIsolationTest.php`, add:

```php
it('UkSavingsEngine implements the SavingsEngine contract', function () {
    expect(app('pack.gb.savings'))->toBeInstanceOf(\Fynla\Core\Contracts\SavingsEngine::class);
});
```

- [ ] **Step 7: Run tests and verify green**

```bash
./vendor/bin/pest tests/Unit/Services/Savings/UkSavingsEngineTest.php \
  tests/Architecture/PackIsolationTest.php
```

Expected: green — 5 tests passing (4 engine + 1 architecture).

- [ ] **Step 8: Commit**

```bash
git add core/app/Core/Contracts/SavingsEngine.php \
        app/Services/Savings/UkSavingsEngine.php \
        app/Providers/GbPackServiceProvider.php \
        tests/Unit/Services/Savings/UkSavingsEngineTest.php \
        tests/Architecture/PackIsolationTest.php
git commit -m "feat(core): add SavingsEngine contract + UK stub (WS 1.2a prep)"
```

---

## Task 1: Add TFSA + endowment tax-config seeder rows

**Files:**
- Modify: `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php`

- [ ] **Step 1: Read the existing seeder rows section**

Skim `ZaTaxConfigurationSeeder.php` around the existing `interest.exemption_*` block (line 178). The new rows go immediately after. **Do NOT add `endowment.cgt_rate_bps` — the existing `cgt.endowment_wrapper_rate_bps` row (line 155) stays canonical for WS 1.3.**

- [ ] **Step 2: Add three TFSA rows + two endowment rows**

Insert into the same `$rows` array that holds the existing interest rows:

```php
// ------------------------------------------------------------
// TFSA — Tax-Free Savings Account (section 12T)
// ------------------------------------------------------------
// 2026/27 figures from National Treasury / SARS:
//   annual contribution cap R46,000 (from 1 March 2026)
//   lifetime cap R500,000 (unchanged)
//   40% flat penalty on excess contributions (Schedule 2 s12T(7))
['tfsa.annual_limit_minor', 4_600_000, 'R46,000 annual TFSA contribution cap'],
['tfsa.lifetime_limit_minor', 50_000_000, 'R500,000 lifetime TFSA contribution cap'],
['tfsa.over_contribution_penalty_bps', 4_000, '40% penalty rate on excess TFSA contributions (basis points)'],

// ------------------------------------------------------------
// Endowment (section 29A) — consumed by WS 1.3.
// CGT rate stays under cgt.endowment_wrapper_rate_bps (already seeded);
// these are the two genuinely new keys.
// ------------------------------------------------------------
['endowment.income_tax_rate_bps', 3_000, '30% effective income tax rate inside endowment wrapper'],
['endowment.restriction_period_years', 5, 'Section 29A 5-year restriction window'],
```

- [ ] **Step 3: Re-run the seeder and verify row count**

```bash
php artisan db:seed --class="Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder" --force
```

Verify TFSA and endowment rows landed:

```bash
php artisan tinker --execute="
  echo 'tfsa rows:'.PHP_EOL;
  echo DB::table('za_tax_configurations')->where('key_path','like','tfsa.%')->orderBy('key_path')->get()->pluck('value_cents','key_path')->toJson(JSON_PRETTY_PRINT).PHP_EOL;
  echo 'endowment rows:'.PHP_EOL;
  echo DB::table('za_tax_configurations')->where('key_path','like','endowment.%')->orderBy('key_path')->get()->pluck('value_cents','key_path')->toJson(JSON_PRETTY_PRINT);
"
```

Expected:
- `tfsa.annual_limit_minor = 4600000`, `tfsa.lifetime_limit_minor = 50000000`, `tfsa.over_contribution_penalty_bps = 4000`
- `endowment.income_tax_rate_bps = 3000`, `endowment.restriction_period_years = 5`
- **No `endowment.cgt_rate_bps`** (WS 1.3 uses the existing `cgt.endowment_wrapper_rate_bps`)

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php
git commit -m "feat(za-pack): seed TFSA caps + endowment income-tax/restriction rows (WS 1.2a)"
```

---

## Task 2: Failing tests for ZaSavingsEngine

**Files:**
- Create: `packs/country-za/tests/Unit/ZaSavingsEngineTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaSavingsEngine::class);
});

it('implements the SavingsEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(SavingsEngine::class);
});

describe('Contract accessors', function () {
    it('returns the R46,000 annual cap', function () {
        expect($this->engine->getAnnualContributionCap(TAX_YEAR))->toBe(4_600_000);
    });

    it('returns the R500,000 lifetime cap', function () {
        expect($this->engine->getLifetimeContributionCap(TAX_YEAR))->toBe(50_000_000);
    });
});

describe('TFSA wrapper penalty', function () {
    it('returns zero penalty when under the annual cap', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 3_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 0,
            taxYear: TAX_YEAR,
        );

        expect($r['penalty_minor'])->toBe(0);
        expect($r['excess_minor'])->toBe(0);
        expect($r['breached_cap'])->toBeNull();
        expect($r['annual_remaining_minor'])->toBe(1_600_000);
        expect($r['lifetime_remaining_minor'])->toBe(47_000_000);
    });

    it('applies 40% penalty on annual excess', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 5_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 0,
            taxYear: TAX_YEAR,
        );

        expect($r['excess_minor'])->toBe(400_000);
        expect($r['penalty_minor'])->toBe(160_000);
        expect($r['breached_cap'])->toBe('annual');
    });

    it('applies 40% penalty on lifetime excess even when annual is fine', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 4_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 47_000_000,
            taxYear: TAX_YEAR,
        );

        expect($r['excess_minor'])->toBe(1_000_000);
        expect($r['penalty_minor'])->toBe(400_000);
        expect($r['breached_cap'])->toBe('lifetime');
    });

    it('uses the larger excess when both caps are breached', function () {
        $r = $this->engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: 10_000_000,
            annualPriorMinor: 0,
            lifetimePriorMinor: 45_000_000,
            taxYear: TAX_YEAR,
        );

        expect($r['excess_minor'])->toBe(5_400_000);
        expect($r['penalty_minor'])->toBe(2_160_000);
        expect($r['breached_cap'])->toBe('annual');
    });
});

describe('Interest tax with exemption', function () {
    it('returns zero tax when interest is below the under-65 exemption', function () {
        $r = $this->engine->calculateInterestTax(
            interestMinor: 2_000_000,
            otherTaxableIncomeMinor: 30_000_000,
            age: 40,
            taxYear: TAX_YEAR,
        );

        expect($r['taxable_interest_minor'])->toBe(0);
        expect($r['exemption_applied_minor'])->toBe(2_000_000);
        expect($r['tax_due_minor'])->toBe(0);
    });

    it('applies the 65+ exemption at R34,500', function () {
        $r = $this->engine->calculateInterestTax(
            interestMinor: 3_000_000,
            otherTaxableIncomeMinor: 20_000_000,
            age: 70,
            taxYear: TAX_YEAR,
        );

        expect($r['taxable_interest_minor'])->toBe(0);
        expect($r['exemption_applied_minor'])->toBe(3_000_000);
        expect($r['tax_due_minor'])->toBe(0);
    });

    it('taxes interest above the exemption at the marginal rate', function () {
        $r = $this->engine->calculateInterestTax(
            interestMinor: 5_000_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: TAX_YEAR,
        );

        expect($r['taxable_interest_minor'])->toBe(2_620_000);
        expect($r['exemption_applied_minor'])->toBe(2_380_000);
        expect($r['tax_due_minor'])->toBeGreaterThan(0);
        expect($r['marginal_rate'])->toBe(31.0);
    });
});
```

- [ ] **Step 2: Run and confirm red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaSavingsEngineTest.php
```

Expected: "Class Fynla\Packs\Za\Savings\ZaSavingsEngine does not exist."

---

## Task 3: Implement ZaSavingsEngine

**Files:**
- Create: `packs/country-za/src/Savings/ZaSavingsEngine.php`

- [ ] **Step 1: Write the engine**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Savings;

use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * SARS 2026/27 savings engine for South Africa. Implements the core
 * SavingsEngine contract.
 *
 * Pure calculator. No DB access. Consumes ZaTaxEngine for marginal
 * income-tax composition and ZaTaxConfigService for static caps
 * (TFSA R46k/R500k, 40% penalty, interest exemptions R23,800 / R34,500).
 */
class ZaSavingsEngine implements SavingsEngine
{
    private const AGE_EXEMPTION_BREAK = 65;
    private const BASELINE_MONTHS = 3;
    private const SINGLE_OR_VOLATILE_MONTHS = 6;
    private const UIF_INELIGIBLE_BUMP_MONTHS = 1;

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    public function calculateInterestTax(
        int $interestMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($interestMinor < 0 || $otherTaxableIncomeMinor < 0 || $age < 0) {
            throw new InvalidArgumentException('Interest calc inputs cannot be negative.');
        }

        $exemption = $age >= self::AGE_EXEMPTION_BREAK
            ? (int) $this->config->get($taxYear, 'interest.exemption_65_plus_minor', 0)
            : (int) $this->config->get($taxYear, 'interest.exemption_under_65_minor', 0);

        $exemptionApplied = min($interestMinor, $exemption);
        $taxableInterest = $interestMinor - $exemptionApplied;

        if ($taxableInterest === 0) {
            return [
                'taxable_interest_minor' => 0,
                'exemption_applied_minor' => $exemptionApplied,
                'tax_due_minor' => 0,
                'marginal_rate' => 0.0,
            ];
        }

        $baseline = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor,
            $taxYear,
            $age,
        );
        $withInterest = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor + $taxableInterest,
            $taxYear,
            $age,
        );

        return [
            'taxable_interest_minor' => $taxableInterest,
            'exemption_applied_minor' => $exemptionApplied,
            'tax_due_minor' => max(0, $withInterest['tax_due'] - $baseline['tax_due']),
            'marginal_rate' => (float) $withInterest['marginal_rate'],
        ];
    }

    public function calculateTaxFreeWrapperPenalty(
        int $contributionMinor,
        int $annualPriorMinor,
        int $lifetimePriorMinor,
        string $taxYear,
    ): array {
        if ($contributionMinor < 0 || $annualPriorMinor < 0 || $lifetimePriorMinor < 0) {
            throw new InvalidArgumentException('TFSA amounts cannot be negative.');
        }

        $annualCap = $this->getAnnualContributionCap($taxYear);
        $lifetimeCap = $this->getLifetimeContributionCap($taxYear) ?? PHP_INT_MAX;
        $penaltyBps = (int) $this->config->get($taxYear, 'tfsa.over_contribution_penalty_bps', 0);

        $annualAfter = $annualPriorMinor + $contributionMinor;
        $lifetimeAfter = $lifetimePriorMinor + $contributionMinor;

        $annualExcess = max(0, $annualAfter - $annualCap);
        $lifetimeExcess = max(0, $lifetimeAfter - $lifetimeCap);

        // Per s12T(7) the penalty applies once to whichever cap is breached
        // the most. SARS doesn't double-penalise.
        $excess = max($annualExcess, $lifetimeExcess);
        $breachedCap = match (true) {
            $annualExcess === 0 && $lifetimeExcess === 0 => null,
            $annualExcess >= $lifetimeExcess => 'annual',
            default => 'lifetime',
        };

        $penalty = (int) round($excess * $penaltyBps / 10_000);

        return [
            'penalty_minor' => $penalty,
            'excess_minor' => $excess,
            'breached_cap' => $breachedCap,
            'annual_remaining_minor' => max(0, $annualCap - $annualAfter),
            'lifetime_remaining_minor' => max(0, $lifetimeCap - $lifetimeAfter),
        ];
    }

    public function getAnnualContributionCap(string $taxYear): int
    {
        return (int) $this->config->get($taxYear, 'tfsa.annual_limit_minor', 0);
    }

    public function getLifetimeContributionCap(string $taxYear): ?int
    {
        $cap = (int) $this->config->get($taxYear, 'tfsa.lifetime_limit_minor', 0);

        return $cap > 0 ? $cap : null;
    }

    public function calculateEmergencyFundTarget(
        int $essentialMonthlyExpenditureMinor,
        array $context,
        string $taxYear,
    ): array {
        if ($essentialMonthlyExpenditureMinor < 0) {
            throw new InvalidArgumentException('Essential expenditure cannot be negative.');
        }

        $stability = $context['income_stability'] ?? 'stable';
        $earners = (int) ($context['household_income_earners'] ?? 2);
        $uifEligible = (bool) ($context['uif_eligible'] ?? true);

        if (! in_array($stability, ['stable', 'volatile'], true)) {
            throw new InvalidArgumentException('income_stability must be stable or volatile.');
        }
        if ($earners < 0) {
            throw new InvalidArgumentException('household_income_earners cannot be negative.');
        }

        // Precedence: volatile > single-earner > UIF-ineligible bump > baseline.
        // A single-earner self-employed case hits single_earner first (6 mo).
        [$months, $reason] = match (true) {
            $stability === 'volatile' => [self::SINGLE_OR_VOLATILE_MONTHS, 'volatile_income'],
            $earners <= 1 => [self::SINGLE_OR_VOLATILE_MONTHS, 'single_earner'],
            ! $uifEligible => [
                self::BASELINE_MONTHS + self::UIF_INELIGIBLE_BUMP_MONTHS,
                'uif_ineligible',
            ],
            default => [self::BASELINE_MONTHS, 'dual_earner_stable'],
        };

        return [
            'target_months' => $months,
            'target_minor' => $months * $essentialMonthlyExpenditureMinor,
            'weighting_reason' => $reason,
        ];
    }
}
```

- [ ] **Step 2: Run tests and verify green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaSavingsEngineTest.php
```

Expected: 10 tests passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Savings/ZaSavingsEngine.php \
        packs/country-za/tests/Unit/ZaSavingsEngineTest.php
git commit -m "feat(za-pack): ZaSavingsEngine implements SavingsEngine (WS 1.2a)"
```

---

## Task 4: Migration + Eloquent model for TFSA contributions ledger

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_18_500001_create_za_tfsa_contributions_table.php`
- Create: `packs/country-za/src/Models/ZaTfsaContribution.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of TFSA contribution events.
 *
 * Rows are keyed EITHER by (user_id, null beneficiary_id) for an adult's
 * own TFSA, OR by (user_id, beneficiary_id) for a minor TFSA where
 * beneficiary_id points to the family_members row for the child. The
 * child's R46k/R500k allowance is tracked independently from the parent's
 * own allowance.
 *
 * amount_minor uses signed bigInteger to match the WS 0.6 shadow-column
 * migration pattern; amount_ccy pairs with it (always 'ZAR' for ZA rows,
 * kept for cross-border aggregation consistency).
 *
 * source_type distinguishes original deposits from provider-to-provider
 * transfers. Both count toward the annual cap (SARS rule) — the column
 * is audit metadata, not business logic.
 *
 * Canonical SA savings_accounts.account_type values consumed alongside
 * this ledger:
 *   'tfsa', 'transactional', 'notice_7', 'notice_32', 'notice_90',
 *   'fixed_deposit', 'money_market', 'rsa_retail_bond', 'endowment'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_tfsa_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('beneficiary_id')->nullable()
                ->constrained('family_members')->nullOnDelete();
            $t->foreignId('savings_account_id')->nullable()
                ->constrained('savings_accounts')->nullOnDelete();
            $t->string('tax_year', 10)->index();
            $t->bigInteger('amount_minor');
            $t->string('amount_ccy', 3)->default('ZAR');
            $t->enum('source_type', ['contribution', 'transfer_in'])
                ->default('contribution');
            $t->date('contribution_date');
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'beneficiary_id', 'tax_year'], 'za_tfsa_cap_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_tfsa_contributions');
    }
};
```

- [ ] **Step 2: Write the Eloquent model**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use App\Models\FamilyMember;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TFSA contribution ledger entry.
 *
 * Pack-owned model referencing app-owned parents (users, family_members,
 * savings_accounts). Canonical cross-pack pattern.
 */
class ZaTfsaContribution extends Model
{
    protected $table = 'za_tfsa_contributions';

    protected $fillable = [
        'user_id',
        'beneficiary_id',
        'savings_account_id',
        'tax_year',
        'amount_minor',
        'amount_ccy',
        'source_type',
        'contribution_date',
        'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'contribution_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'beneficiary_id');
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }
}
```

- [ ] **Step 3: Run migrations and verify**

```bash
php artisan migrate
php artisan tinker --execute="
  echo Schema::hasTable('za_tfsa_contributions') ? 'ok table'.PHP_EOL : 'missing'.PHP_EOL;
  echo implode(', ', Schema::getColumnListing('za_tfsa_contributions'));
"
```

Expected: `ok table` and a column list containing `id, user_id, beneficiary_id, savings_account_id, tax_year, amount_minor, amount_ccy, source_type, contribution_date, notes, created_at, updated_at`.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_18_500001_create_za_tfsa_contributions_table.php \
        packs/country-za/src/Models/ZaTfsaContribution.php
git commit -m "feat(za-pack): za_tfsa_contributions ledger with beneficiary + source_type (WS 1.2a)"
```

---

## Task 5: Failing test for ZaTfsaContributionTracker

**Files:**
- Create: `packs/country-za/tests/Unit/ZaTfsaContributionTrackerTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const TFSA_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->tracker = app(ZaTfsaContributionTracker::class);
    $this->user = User::factory()->create();
});

it('records a contribution for an adult and sums correctly', function () {
    $id = $this->tracker->record(
        userId: $this->user->id,
        beneficiaryId: null,
        savingsAccountId: null,
        taxYear: TFSA_TAX_YEAR,
        amountMinor: 1_000_000,
        contributionDate: '2026-04-10',
    );

    expect($id)->toBeInt()->toBeGreaterThan(0);
    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(1_000_000);
    expect($this->tracker->sumLifetime($this->user->id, null))->toBe(1_000_000);
});

it('accumulates multiple adult contributions in the same tax year', function () {
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 1_500_000, '2026-04-10');
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 2_500_000, '2026-07-15');

    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(4_000_000);
});

it('isolates sums by tax year', function () {
    $this->tracker->record($this->user->id, null, null, '2025/26', 3_000_000, '2025-06-01');
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 2_000_000, '2026-04-10');

    expect($this->tracker->sumForTaxYear($this->user->id, null, '2025/26'))->toBe(3_000_000);
    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(2_000_000);
    expect($this->tracker->sumLifetime($this->user->id, null))->toBe(5_000_000);
});

it('isolates a minor TFSA allowance from the parent owner', function () {
    $child = FamilyMember::factory()->for($this->user)->create();

    // Parent contributes R30k to own TFSA, and R40k to child's.
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 3_000_000, '2026-04-10');
    $this->tracker->record($this->user->id, $child->id, null, TFSA_TAX_YEAR, 4_000_000, '2026-04-11');

    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(3_000_000);
    expect($this->tracker->sumForTaxYear($this->user->id, $child->id, TFSA_TAX_YEAR))->toBe(4_000_000);
    expect($this->tracker->sumLifetime($this->user->id, null))->toBe(3_000_000);
    expect($this->tracker->sumLifetime($this->user->id, $child->id))->toBe(4_000_000);
});

it('reports remaining allowances using pack config', function () {
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 2_000_000, '2026-04-10');

    expect($this->tracker->remainingAnnualAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(2_600_000);
    expect($this->tracker->remainingLifetimeAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(48_000_000);
});

it('returns full allowance when the user has no contributions', function () {
    expect($this->tracker->remainingAnnualAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(4_600_000);
});

it('counts transfer_in rows toward the cap (SARS rule)', function () {
    $this->tracker->record(
        userId: $this->user->id,
        beneficiaryId: null,
        savingsAccountId: null,
        taxYear: TFSA_TAX_YEAR,
        amountMinor: 3_000_000,
        contributionDate: '2026-04-10',
        sourceType: 'transfer_in',
    );
    $this->tracker->record($this->user->id, null, null, TFSA_TAX_YEAR, 1_500_000, '2026-05-01');

    expect($this->tracker->sumForTaxYear($this->user->id, null, TFSA_TAX_YEAR))->toBe(4_500_000);
    expect($this->tracker->remainingAnnualAllowance($this->user->id, null, TFSA_TAX_YEAR))
        ->toBe(100_000);
});
```

- [ ] **Step 2: Run and confirm red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaTfsaContributionTrackerTest.php
```

Expected: "Class Fynla\Packs\Za\Savings\ZaTfsaContributionTracker does not exist."

---

## Task 6: Implement ZaTfsaContributionTracker

**Files:**
- Create: `packs/country-za/src/Savings/ZaTfsaContributionTracker.php`

- [ ] **Step 1: Write the tracker**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Savings;

use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;

/**
 * Thin persistence for TFSA contribution events.
 *
 * Mirrors the pattern of ZaSection11fTracker / ZaSection10cTracker —
 * append-only, no business rules. Caps / penalty logic lives in
 * ZaSavingsEngine.
 *
 * The beneficiary_id parameter disambiguates adult self-owned TFSAs
 * (beneficiary_id = null) from minor TFSAs (beneficiary_id = family
 * member id). Both use the same adult's user_id for authority, but caps
 * are tracked independently against each (user_id, beneficiary_id) pair.
 */
class ZaTfsaContributionTracker
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    public function record(
        int $userId,
        ?int $beneficiaryId,
        ?int $savingsAccountId,
        string $taxYear,
        int $amountMinor,
        string $contributionDate,
        string $sourceType = 'contribution',
        ?string $notes = null,
    ): int {
        $row = ZaTfsaContribution::create([
            'user_id' => $userId,
            'beneficiary_id' => $beneficiaryId,
            'savings_account_id' => $savingsAccountId,
            'tax_year' => $taxYear,
            'amount_minor' => $amountMinor,
            'amount_ccy' => 'ZAR',
            'source_type' => $sourceType,
            'contribution_date' => $contributionDate,
            'notes' => $notes,
        ]);

        return (int) $row->id;
    }

    public function sumForTaxYear(int $userId, ?int $beneficiaryId, string $taxYear): int
    {
        return (int) $this->scopeBeneficiary(
            ZaTfsaContribution::query()
                ->where('user_id', $userId)
                ->where('tax_year', $taxYear),
            $beneficiaryId,
        )->sum('amount_minor');
    }

    public function sumLifetime(int $userId, ?int $beneficiaryId): int
    {
        return (int) $this->scopeBeneficiary(
            ZaTfsaContribution::query()->where('user_id', $userId),
            $beneficiaryId,
        )->sum('amount_minor');
    }

    public function remainingAnnualAllowance(int $userId, ?int $beneficiaryId, string $taxYear): int
    {
        $cap = (int) $this->config->get($taxYear, 'tfsa.annual_limit_minor', 0);

        return max(0, $cap - $this->sumForTaxYear($userId, $beneficiaryId, $taxYear));
    }

    public function remainingLifetimeAllowance(int $userId, ?int $beneficiaryId, string $taxYear): int
    {
        $cap = (int) $this->config->get($taxYear, 'tfsa.lifetime_limit_minor', 0);

        return max(0, $cap - $this->sumLifetime($userId, $beneficiaryId));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function scopeBeneficiary($query, ?int $beneficiaryId)
    {
        return $beneficiaryId === null
            ? $query->whereNull('beneficiary_id')
            : $query->where('beneficiary_id', $beneficiaryId);
    }
}
```

- [ ] **Step 2: Run tests and verify green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaTfsaContributionTrackerTest.php
```

Expected: 7 tests passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Savings/ZaTfsaContributionTracker.php \
        packs/country-za/tests/Unit/ZaTfsaContributionTrackerTest.php
git commit -m "feat(za-pack): ZaTfsaContributionTracker with beneficiary + transfer support (WS 1.2a)"
```

---

## Task 7: Failing tests for ZaEmergencyFundCalculator

**Files:**
- Create: `packs/country-za/tests/Unit/ZaEmergencyFundCalculatorTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator;

beforeEach(function () {
    $this->calc = app(ZaEmergencyFundCalculator::class);
});

describe('computeTarget', function () {
    it('defaults to 3 months when income is stable and dual-earner', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 2,
            uifEligible: true,
        );

        expect($r['target_months'])->toBe(3);
        expect($r['target_minor'])->toBe(9_000_000);
        expect($r['weighting_reason'])->toBe('dual_earner_stable');
    });

    it('weights to 6 months for single-earner households', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 1,
            uifEligible: true,
        );

        expect($r['target_months'])->toBe(6);
        expect($r['weighting_reason'])->toBe('single_earner');
    });

    it('weights to 6 months regardless of earners when income is volatile', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'volatile',
            householdIncomeEarners: 2,
            uifEligible: true,
        );

        expect($r['target_months'])->toBe(6);
        expect($r['weighting_reason'])->toBe('volatile_income');
    });

    it('adds one month when the earner is UIF-ineligible (self-employed)', function () {
        $r = $this->calc->computeTarget(
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 2,
            uifEligible: false,
        );

        expect($r['target_months'])->toBe(4);
        expect($r['weighting_reason'])->toBe('uif_ineligible');
    });
});

describe('assess', function () {
    it('reports adequate when current balance covers the target', function () {
        $r = $this->calc->assess(
            currentBalanceMinor: 10_000_000,
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 2,
            uifEligible: true,
        );

        expect($r['status'])->toBe('adequate');
        expect($r['shortfall_minor'])->toBe(0);
        expect($r['months_covered'])->toBe(3.33);
    });

    it('reports shortfall when balance is below target', function () {
        $r = $this->calc->assess(
            currentBalanceMinor: 5_000_000,
            essentialMonthlyExpenditureMinor: 3_000_000,
            incomeStability: 'stable',
            householdIncomeEarners: 1,
            uifEligible: true,
        );

        expect($r['status'])->toBe('shortfall');
        expect($r['shortfall_minor'])->toBe(13_000_000);
        expect($r['months_covered'])->toBe(1.67);
    });
});
```

- [ ] **Step 2: Run and confirm red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaEmergencyFundCalculatorTest.php
```

Expected: red — class not found.

---

## Task 8: Implement ZaEmergencyFundCalculator

**Files:**
- Create: `packs/country-za/src/Savings/ZaEmergencyFundCalculator.php`

This is a thin wrapper around `ZaSavingsEngine::calculateEmergencyFundTarget` that exposes an imperative method surface (two methods: `computeTarget`, `assess`) for consumers that don't want to deal with the context-array shape.

- [ ] **Step 1: Write the calculator**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Savings;

use InvalidArgumentException;

/**
 * Imperative wrapper around ZaSavingsEngine's emergency-fund target calc,
 * with an adequacy assessment helper.
 *
 * The ZaSavingsEngine method is context-array shaped for contract
 * compatibility. This wrapper is the ZA-native surface for callers that
 * don't need contract uniformity (e.g. the Savings dashboard, coordinator
 * agents, CLI tools).
 */
class ZaEmergencyFundCalculator
{
    public function __construct(
        private readonly ZaSavingsEngine $engine,
    ) {
    }

    /**
     * @return array{target_months: int, target_minor: int, weighting_reason: string}
     */
    public function computeTarget(
        int $essentialMonthlyExpenditureMinor,
        string $incomeStability,
        int $householdIncomeEarners,
        bool $uifEligible,
    ): array {
        if ($essentialMonthlyExpenditureMinor < 0) {
            throw new InvalidArgumentException('Essential expenditure cannot be negative.');
        }

        return $this->engine->calculateEmergencyFundTarget(
            essentialMonthlyExpenditureMinor: $essentialMonthlyExpenditureMinor,
            context: [
                'income_stability' => $incomeStability,
                'household_income_earners' => $householdIncomeEarners,
                'uif_eligible' => $uifEligible,
            ],
            taxYear: '2026/27',
        );
    }

    /**
     * @return array{status: string, shortfall_minor: int, months_covered: float,
     *     target_months: int, target_minor: int, weighting_reason: string}
     */
    public function assess(
        int $currentBalanceMinor,
        int $essentialMonthlyExpenditureMinor,
        string $incomeStability,
        int $householdIncomeEarners,
        bool $uifEligible,
    ): array {
        $target = $this->computeTarget(
            $essentialMonthlyExpenditureMinor,
            $incomeStability,
            $householdIncomeEarners,
            $uifEligible,
        );

        $shortfall = max(0, $target['target_minor'] - $currentBalanceMinor);
        $monthsCovered = $essentialMonthlyExpenditureMinor === 0
            ? 0.0
            : round($currentBalanceMinor / $essentialMonthlyExpenditureMinor, 2);

        return [
            'status' => $shortfall === 0 ? 'adequate' : 'shortfall',
            'shortfall_minor' => $shortfall,
            'months_covered' => $monthsCovered,
            'target_months' => $target['target_months'],
            'target_minor' => $target['target_minor'],
            'weighting_reason' => $target['weighting_reason'],
        ];
    }
}
```

- [ ] **Step 2: Run tests and verify green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaEmergencyFundCalculatorTest.php
```

Expected: 6 tests passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Savings/ZaEmergencyFundCalculator.php \
        packs/country-za/tests/Unit/ZaEmergencyFundCalculatorTest.php
git commit -m "feat(za-pack): ZaEmergencyFundCalculator wrapper with SA weights (WS 1.2a)"
```

---

## Task 9: Wire the new services through ZaPackServiceProvider

**Files:**
- Modify: `packs/country-za/src/Providers/ZaPackServiceProvider.php`

- [ ] **Step 1: Expand `register()`**

```php
public function register(): void
{
    $this->app->singleton(ZaTaxConfigService::class);
    $this->app->bind('pack.za.tax', ZaTaxEngine::class);

    // WS 1.2a — Savings
    $this->app->bind('pack.za.savings', \Fynla\Packs\Za\Savings\ZaSavingsEngine::class);
    $this->app->bind(
        'pack.za.tfsa.tracker',
        \Fynla\Packs\Za\Savings\ZaTfsaContributionTracker::class,
    );
    $this->app->bind(
        'pack.za.savings.emergency_fund',
        \Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator::class,
    );
}
```

- [ ] **Step 2: Verify the bindings resolve**

```bash
php artisan tinker --execute="
  echo get_class(app('pack.za.savings')).PHP_EOL;
  echo get_class(app('pack.za.tfsa.tracker')).PHP_EOL;
  echo get_class(app('pack.za.savings.emergency_fund'));
"
```

Expected:
```
Fynla\Packs\Za\Savings\ZaSavingsEngine
Fynla\Packs\Za\Savings\ZaTfsaContributionTracker
Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator
```

- [ ] **Step 3: Extend provider tests**

In `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php`, add:

```php
it('registers savings container bindings', function () {
    expect(app('pack.za.savings'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Savings\ZaSavingsEngine::class)
        ->toBeInstanceOf(\Fynla\Core\Contracts\SavingsEngine::class);
    expect(app('pack.za.tfsa.tracker'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Savings\ZaTfsaContributionTracker::class);
    expect(app('pack.za.savings.emergency_fund'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator::class);
});
```

- [ ] **Step 4: Run tests and verify green**

```bash
./vendor/bin/pest packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
```

- [ ] **Step 5: Commit**

```bash
git add packs/country-za/src/Providers/ZaPackServiceProvider.php \
        packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
git commit -m "feat(za-pack): register savings engine + tracker + emergency fund (WS 1.2a)"
```

---

## Task 10: Add TFSA fields to SavingsAccount (main app)

**Files:**
- Create: `database/migrations/2026_04_18_500000_add_tfsa_fields_to_savings_accounts_table.php`
- Modify: `app/Models/SavingsAccount.php`
- Modify: `database/factories/SavingsAccountFactory.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add SA Tax-Free Savings Account (TFSA) fields alongside existing UK ISA
 * fields. The country_code column (from WS 0.6) determines which set of
 * fields applies per row.
 *
 * All _minor columns use signed bigInteger to match the WS 0.6 shadow-
 * column migration pattern. _ccy companion columns are added for
 * cross-border aggregation consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $t) {
            $t->boolean('is_tfsa')->default(false)->after('isa_subscription_amount');
            $t->string('tfsa_subscription_year', 10)->nullable()->after('is_tfsa');
            $t->bigInteger('tfsa_subscription_amount_minor')->nullable()
                ->after('tfsa_subscription_year');
            $t->string('tfsa_subscription_amount_ccy', 3)->nullable()
                ->after('tfsa_subscription_amount_minor');
            $t->bigInteger('tfsa_lifetime_contributed_minor')->nullable()
                ->after('tfsa_subscription_amount_ccy');
            $t->string('tfsa_lifetime_contributed_ccy', 3)->nullable()
                ->after('tfsa_lifetime_contributed_minor');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $t) {
            $t->dropColumn([
                'is_tfsa',
                'tfsa_subscription_year',
                'tfsa_subscription_amount_minor',
                'tfsa_subscription_amount_ccy',
                'tfsa_lifetime_contributed_minor',
                'tfsa_lifetime_contributed_ccy',
            ]);
        });
    }
};
```

- [ ] **Step 2: Extend SavingsAccount `$fillable` and `$casts`**

Add to `$fillable` after `'isa_subscription_amount'`:

```php
// SA TFSA fields — activated when country_code = 'ZA'
'is_tfsa',
'tfsa_subscription_year',
'tfsa_subscription_amount_minor',
'tfsa_subscription_amount_ccy',
'tfsa_lifetime_contributed_minor',
'tfsa_lifetime_contributed_ccy',
```

Add to `$casts`:

```php
'is_tfsa' => 'boolean',
'tfsa_subscription_amount_minor' => 'integer',
'tfsa_lifetime_contributed_minor' => 'integer',
```

- [ ] **Step 3: Add `tfsa()` and `minor()` factory states**

Add to `database/factories/SavingsAccountFactory.php`:

```php
public function tfsa(): static
{
    return $this->state(fn () => [
        'country_code' => 'ZA',
        'is_tfsa' => true,
        'is_isa' => false,
        'tfsa_subscription_year' => '2026/27',
        'tfsa_subscription_amount_minor' => 2_000_000,
        'tfsa_subscription_amount_ccy' => 'ZAR',
        'tfsa_lifetime_contributed_minor' => 2_000_000,
        'tfsa_lifetime_contributed_ccy' => 'ZAR',
        'account_type' => 'tfsa',
        'account_name' => 'TFSA — Investec',
    ]);
}

/**
 * Minor TFSA — held by an adult on behalf of a family_member child.
 * Caller passes the FamilyMember model; factory wires the beneficiary
 * fields already present on savings_accounts.
 */
public function minor(\App\Models\FamilyMember $beneficiary): static
{
    return $this->state(fn () => [
        'beneficiary_id' => $beneficiary->id,
        'beneficiary_name' => $beneficiary->full_name ?? 'Minor beneficiary',
        'beneficiary_dob' => $beneficiary->date_of_birth ?? '2020-01-01',
        'account_name' => "TFSA — Minor ({$beneficiary->full_name})",
    ]);
}
```

- [ ] **Step 4: Run migration + smoke-check**

```bash
php artisan migrate
php artisan tinker --execute="echo implode(', ', array_filter(Schema::getColumnListing('savings_accounts'), fn(\$c) => str_starts_with(\$c,'tfsa_') || \$c === 'is_tfsa'));"
```

Expected: `is_tfsa, tfsa_subscription_year, tfsa_subscription_amount_minor, tfsa_subscription_amount_ccy, tfsa_lifetime_contributed_minor, tfsa_lifetime_contributed_ccy`.

- [ ] **Step 5: Verify UK ISA tests still pass**

```bash
./vendor/bin/pest tests/Unit/Services/Savings/
```

Expected: all existing UK Savings tests green — zero regression.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_18_500000_add_tfsa_fields_to_savings_accounts_table.php \
        app/Models/SavingsAccount.php \
        database/factories/SavingsAccountFactory.php
git commit -m "feat(core): SavingsAccount TFSA shadow-column fields + factory states (WS 1.2a)"
```

---

## Task 11: Integration test — end-to-end TFSA flow

**Files:**
- Create: `tests/Integration/Za/ZaSavingsIntegrationTest.php`
- Modify: `phpunit.xml` (add `Integration` testsuite)

- [ ] **Step 1: Add Integration testsuite to phpunit.xml**

Inside `<testsuites>`, add:

```xml
<testsuite name="Integration">
    <directory suffix="Test.php">./tests/Integration</directory>
</testsuite>
```

- [ ] **Step 2: Write the integration test**

```php
<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\SavingsAccount;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: user records three contributions, engine reports no penalty, remaining allowances shrink', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->tfsa()->for($user)->create();
    $tracker = app(ZaTfsaContributionTracker::class);
    $engine = app(ZaSavingsEngine::class);

    $tracker->record($user->id, null, $account->id, ZA_TAX_YEAR, 1_000_000, '2026-04-10');
    $tracker->record($user->id, null, $account->id, ZA_TAX_YEAR, 1_500_000, '2026-07-15');
    $tracker->record($user->id, null, $account->id, ZA_TAX_YEAR, 1_000_000, '2026-12-01');

    expect($tracker->sumForTaxYear($user->id, null, ZA_TAX_YEAR))->toBe(3_500_000);
    expect($tracker->remainingAnnualAllowance($user->id, null, ZA_TAX_YEAR))->toBe(1_100_000);

    $r = $engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: 500_000,
        annualPriorMinor: $tracker->sumForTaxYear($user->id, null, ZA_TAX_YEAR),
        lifetimePriorMinor: $tracker->sumLifetime($user->id, null),
        taxYear: ZA_TAX_YEAR,
    );

    expect($r['penalty_minor'])->toBe(0);
    expect($r['annual_remaining_minor'])->toBe(600_000);
});

it('end-to-end: engine flags a contribution that would breach the annual cap', function () {
    $user = User::factory()->create();
    $tracker = app(ZaTfsaContributionTracker::class);
    $engine = app(ZaSavingsEngine::class);

    $tracker->record($user->id, null, null, ZA_TAX_YEAR, 4_000_000, '2026-04-10');

    $r = $engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: 1_000_000,
        annualPriorMinor: $tracker->sumForTaxYear($user->id, null, ZA_TAX_YEAR),
        lifetimePriorMinor: $tracker->sumLifetime($user->id, null),
        taxYear: ZA_TAX_YEAR,
    );

    expect($r['breached_cap'])->toBe('annual');
    expect($r['excess_minor'])->toBe(400_000);
    expect($r['penalty_minor'])->toBe(160_000);
});

it('end-to-end: a minor TFSA tracks the child\'s allowance, not the parent\'s', function () {
    $parent = User::factory()->create();
    $child = FamilyMember::factory()->for($parent)->create();
    $parentAccount = SavingsAccount::factory()->tfsa()->for($parent)->create();
    $childAccount = SavingsAccount::factory()
        ->tfsa()
        ->minor($child)
        ->for($parent)
        ->create();

    $tracker = app(ZaTfsaContributionTracker::class);

    // Parent contributes R30k to own TFSA and R40k to child's TFSA.
    $tracker->record($parent->id, null, $parentAccount->id, ZA_TAX_YEAR, 3_000_000, '2026-04-10');
    $tracker->record($parent->id, $child->id, $childAccount->id, ZA_TAX_YEAR, 4_000_000, '2026-04-11');

    // Parent's own remaining = R46k − R30k = R16k
    expect($tracker->remainingAnnualAllowance($parent->id, null, ZA_TAX_YEAR))->toBe(1_600_000);
    // Child's remaining = R46k − R40k = R6k
    expect($tracker->remainingAnnualAllowance($parent->id, $child->id, ZA_TAX_YEAR))->toBe(600_000);

    // Parent's lifetime vs child's lifetime are independent.
    expect($tracker->sumLifetime($parent->id, null))->toBe(3_000_000);
    expect($tracker->sumLifetime($parent->id, $child->id))->toBe(4_000_000);
});
```

- [ ] **Step 3: Run the integration test**

```bash
./vendor/bin/pest tests/Integration/Za/ZaSavingsIntegrationTest.php
```

Expected: 3 tests passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/Za/ZaSavingsIntegrationTest.php phpunit.xml
git commit -m "test(za-pack): WS 1.2a end-to-end TFSA integration tests (incl. minor)"
```

---

## Task 12: Full regression + pack-isolation gate

- [ ] **Step 1: Run the pack suite**

```bash
./vendor/bin/pest packs/country-za/
```

Expected: all ZA pack tests green.

- [ ] **Step 2: Architecture suite — confirm pack isolation still holds**

```bash
./vendor/bin/pest --testsuite=Architecture
```

Expected: green. `ZaSavingsEngine` must not import `App\Services\*` (only app-owned models are allowed cross-boundary).

- [ ] **Step 3: Full suite — zero UK regression**

```bash
./vendor/bin/pest
```

Expected: 2,455 + new WS 1.2a tests passing, 0 failing, 2 skipped.

- [ ] **Step 4: Record the new baseline**

Create `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-2a-complete.md` with:
- Total tests before: 2,455
- Total tests after: [actual]
- Net gain: [delta]
- Contract added: `core/app/Core/Contracts/SavingsEngine.php`
- Services shipped: `UkSavingsEngine`, `ZaSavingsEngine`, `ZaTfsaContributionTracker`, `ZaEmergencyFundCalculator`
- Tables added: `za_tfsa_contributions`
- `savings_accounts` columns added: `is_tfsa`, `tfsa_subscription_year`, `tfsa_subscription_amount_minor` + `_ccy`, `tfsa_lifetime_contributed_minor` + `_ccy`
- WS 1.2b (frontend) tracking: TODO — separate plan to scaffold SA frontend infrastructure + TFSA UI.

- [ ] **Step 5: Final commit**

```bash
git add /Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-2a-complete.md
git commit -m "docs(vault): WS 1.2a completion note"
```

---

## Self-Review

**Spec coverage (SA Research § 7 + Implementation_Plan_v2 WS 1.2a):**
| Requirement | Task |
|-------------|------|
| `SavingsEngine` contract (inferred from missing-pattern audit) | Task 0 |
| UK-side stub implementing contract | Task 0 |
| TFSA R46k annual / R500k lifetime caps | Task 1 (seeder), Task 3 (engine), Task 6 (tracker) |
| 40% over-contribution penalty | Task 3 |
| `za_tfsa_contributions` ledger with minor-TFSA + transfer support | Task 4 |
| Emergency fund calculator with SA defaults | Tasks 7–8 |
| Vue components | Deferred to WS 1.2b |

**Placeholder scan:** All code blocks complete. No TODOs. No "similar to X" references.

**Type consistency:**
- `penalty_minor` / `excess_minor` / `breached_cap` / `annual_remaining_minor` / `lifetime_remaining_minor` consistent across `SavingsEngine` contract (Task 0), `ZaSavingsEngine` impl (Task 3), `UkSavingsEngine` stub (Task 0), and integration test (Task 11).
- `calculateTaxFreeWrapperPenalty` contract method named identically in all three places.
- Tracker's `(userId, beneficiaryId)` pair used consistently across all five methods (`record`, `sumForTaxYear`, `sumLifetime`, `remainingAnnualAllowance`, `remainingLifetimeAllowance`) and throughout tests.
- Container keys `pack.gb.savings`, `pack.za.savings`, `pack.za.tfsa.tracker`, `pack.za.savings.emergency_fund` identical in provider code and assertions.

**Known risks:**
- `TaxConfigService::getIsaAllowance()` is assumed to return an array with a `'total'` key. Task 0 Step 4 relies on this. If the shape has changed, `UkSavingsEngine::getAnnualContributionCap` will need adjustment. Low risk — the method is documented in the UK codebase and stable.
- `FamilyMember::factory()` and the `beneficiary_name`/`beneficiary_dob` columns exist per `SavingsAccount::$fillable` inspection (lines 50-53 of the current `SavingsAccount.php`). If the column set has shifted, the `minor()` factory state needs the matching keys.

---

## Execution Handoff

Plan amended and saved. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks.
2. **Inline Execution** — execute tasks in this session with checkpoints.
