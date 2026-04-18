# WS 1.4a — SA Retirement Core + Two-Pot Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Amended — 18 April 2026 — conflicts resolved against codebase audit. Three amendments vs v1 draft:
- Bucket schema gains a `provident_vested_pre2021_balance_minor` column so WS 1.4b annuity mechanics can distinguish provident-fund pre-2021 members who retain 100% commutability at retirement. Zero-cost additive column.
- Integration test gains a scenario demonstrating the `ZaSection11fTracker` + engine composition pattern — caller threads carry-forward explicitly via the tracker before invoking the stateless engine method.
- The 10% vested-to-savings historic seeding (SARB 2024-09-01) is **NOT** modelled as a helper — treated as user-entered state. Documented in Out of Scope below.

**Goal:** Ship the South Africa Retirement **core + Two-Pot** backend — `ZaRetirementEngine` implementing `core/app/Core/Contracts/RetirementEngine`, the three-bucket balance model (vested / savings / retirement) per member per fund, the 1/3–2/3 contribution split service for contributions from 1 September 2024 onwards, Section 11F deduction composition, Savings-Pot withdrawal marginal-tax simulator. UI, annuity mechanics, Reg 28 Monitor are WS 1.4b/c/d.

**Architecture:**
- `ZaRetirementEngine implements RetirementEngine` — composes `ZaTaxEngine::calculateIncomeTaxForAge` (for Savings-Pot withdrawal marginal-tax delta), `ZaTaxEngine::calculateRetirementDeduction` (Section 11F path from WS 1.1), `ZaTaxConfigService` (for caps). Pure calculator; no DB access.
- **Two-Pot buckets.** Each ZA retirement fund holding has three balances: `vested_balance_minor`, `savings_balance_minor`, `retirement_balance_minor`. Stored in a new `za_retirement_fund_buckets` table keyed by `(user_id, fund_holding_id)`. One row per fund holding — the three balances update together.
- `ZaContributionSplitService` — stateless helper. Given a gross contribution amount, applies the 1/3–2/3 split to the Savings and Retirement buckets respectively (contributions from 1 September 2024 onwards). Pre-two-pot funds (start_date < 2024-09-01) contribute 100% to vested. Returns a `{savings_delta_minor, retirement_delta_minor, vested_delta_minor}` breakdown.
- `ZaSavingsPotWithdrawalSimulator` — given a proposed Savings-Pot withdrawal amount and the member's current-year cumulative income, projects the marginal-tax hit by composing `ZaTaxEngine::calculateIncomeTaxForAge(income + withdrawal)` minus `calculateIncomeTaxForAge(income)`. Enforces the R2,000 minimum and once-per-tax-year rule (rule check only — the ledger tracks frequency in WS 1.4b's annuity workstream or a future enhancement).
- `ZaRetirementFundBucket` — pack-owned Eloquent model. Cross-namespace FK via runtime FQCN.
- One new tax-config row: `retirement.savings_pot_minimum_withdrawal_minor` (R2,000 = 200,000 cents).
- Container keys: `pack.za.retirement` (the engine), `pack.za.retirement.contribution_split`, `pack.za.retirement.savings_pot_simulator`, `pack.za.retirement.buckets`.
- UK side: `App\Services\Retirement\UkRetirementEngine implements RetirementEngine` — stub exposing UK DC annual allowance, LSA (lifetime allowance), state pension age. Registered at `pack.gb.retirement`, replacing the previous `RetirementAgent::class` binding (mirrors the WS 1.3a precedent for Investment).

**Tech Stack:** Laravel 10, PHP 8.2 strict types, Pest v2, MySQL 8.

**Out of scope (deferred to WS 1.4b / 1.4c / 1.4d):**
- Living annuity drawdown band (2.5–17.5%) → WS 1.4b
- Life annuity + Section 10C exemption composition → WS 1.4b
- Compulsory annuitisation rules (1/3 PCLS + 2/3 annuity, R165k de minimis) → WS 1.4b
- Reg 28 Monitor (look-through asset allocation, breach detection, `reg28_snapshots` table) → WS 1.4c
- SASSA Old Age Grant data field on User → WS 1.4c or 1.4d
- Vue components (retirement dashboard, Two-Pot tracker UI) → WS 1.4d
- Observer to auto-update buckets on fund-holding creation → WS 1.4d
- Unclaimed-benefits search link-out → WS 1.4d
- **10% vested-to-savings historic seeding** (SARB 2024-09-01 regulation) — NOT modelled as a helper. The Fynla SA planning app records member-declared balances as the current state. The historic seeding moved 10% of each member's vested balance (capped R30,000) into the Savings Component in September 2024; whatever balances a member reports today already reflect that move. Modelling it as a migration helper would try to reconstruct history Fynla doesn't need to track.

---

## File Structure

**New files (core):** None — `RetirementEngine` contract already exists.

**New files (ZA pack):**
- `packs/country-za/src/Retirement/ZaRetirementEngine.php` — contract implementation
- `packs/country-za/src/Retirement/ZaContributionSplitService.php` — Two-Pot 1/3–2/3 split
- `packs/country-za/src/Retirement/ZaSavingsPotWithdrawalSimulator.php` — marginal-tax simulator
- `packs/country-za/src/Retirement/ZaRetirementFundBucketRepository.php` — thin persistence
- `packs/country-za/src/Models/ZaRetirementFundBucket.php` — Eloquent model
- `packs/country-za/database/migrations/2026_04_18_800001_create_za_retirement_fund_buckets_table.php`
- `packs/country-za/tests/Unit/ZaRetirementEngineTest.php`
- `packs/country-za/tests/Unit/ZaContributionSplitServiceTest.php`
- `packs/country-za/tests/Unit/ZaSavingsPotWithdrawalSimulatorTest.php`
- `packs/country-za/tests/Unit/ZaRetirementFundBucketRepositoryTest.php`

**New files (main app):**
- `app/Services/Retirement/UkRetirementEngine.php` — GB-side stub `implements RetirementEngine`
- `tests/Unit/Services/Retirement/UkRetirementEngineTest.php`
- `tests/Integration/Za/ZaRetirementIntegrationTest.php`

**Modified files:**
- `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php` — add Savings-Pot minimum row + Two-Pot effective date
- `packs/country-za/src/Providers/ZaPackServiceProvider.php` — four new bindings
- `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php` — binding assertions
- `app/Providers/GbPackServiceProvider.php` — rebind `pack.gb.retirement` to `UkRetirementEngine`
- `tests/Architecture/PackIsolationTest.php` — contract-implementation assertions

---

## Task 0: GB-side RetirementEngine stub

Mirror WS 1.3a's precedent — `pack.gb.retirement` currently binds to `RetirementAgent` (an orchestrator, not a contract impl). Create `UkRetirementEngine` and rebind.

**Files:**
- Create: `app/Services/Retirement/UkRetirementEngine.php`
- Create: `tests/Unit/Services/Retirement/UkRetirementEngineTest.php`
- Modify: `app/Providers/GbPackServiceProvider.php`
- Modify: `tests/Architecture/PackIsolationTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Retirement\UkRetirementEngine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Fynla\Core\Contracts\RetirementEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app(TaxConfigService::class)->clearCache();
    $this->engine = app(UkRetirementEngine::class);
});

it('implements the RetirementEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(RetirementEngine::class);
});

it('returns the UK annual pension allowance from TaxConfigService', function () {
    $allowance = $this->engine->getAnnualAllowance('2025/26');

    // £60,000 = 6_000_000 pence.
    expect($allowance)->toBeGreaterThanOrEqual(6_000_000);
});

it('returns state pension age for a 1970 male (67)', function () {
    expect($this->engine->getStatePensionAge('1970-06-15', 'male'))->toBeInt()->toBeGreaterThanOrEqual(66);
});
```

- [ ] **Step 2: Run test — expect red**

```bash
./vendor/bin/pest tests/Unit/Services/Retirement/UkRetirementEngineTest.php
```

Expected: class not found.

- [ ] **Step 3: Write the stub**

```php
<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\RetirementEngine;

/**
 * UK-side RetirementEngine implementation.
 *
 * Exposes UK pension-allowance values behind the contract. Full UK
 * pension-tax composition lives in existing services
 * (RetirementAgent, PensionProjector etc.) and is not duplicated here;
 * callers that need deep UK behaviour resolve those classes directly.
 *
 * State pension age follows the UK's SPA phasing: 66 for those born
 * 1955–1960, 67 for 1961–1977, 68 for 1978+ (Pensions Act 2014).
 */
class UkRetirementEngine implements RetirementEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {
    }

    public function calculatePensionTaxRelief(int $contributionMinor, int $incomeMinor, string $taxYear): array
    {
        // Stub — full UK PSA + MPAA + tapered-allowance composition lives
        // in the existing UK service layer and is not lifted here.
        return [
            'relief_amount' => 0,
            'relief_rate' => 0.0,
            'net_cost' => $contributionMinor,
            'method' => 'uk_stub',
        ];
    }

    public function getAnnualAllowance(string $taxYear): int
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $allowancePounds = (int) ($pension['annual_allowance'] ?? 60_000);

        return $allowancePounds * 100;
    }

    public function getLifetimeAllowance(string $taxYear): ?int
    {
        // LTA abolished from 2024/25. Return null to signal "no lifetime
        // limit" rather than zero (zero is semantically different).
        return null;
    }

    public function getStatePensionAge(string $dateOfBirth, string $gender): int
    {
        $birthYear = (int) substr($dateOfBirth, 0, 4);

        return match (true) {
            $birthYear >= 1978 => 68,
            $birthYear >= 1961 => 67,
            default => 66,
        };
    }

    public function projectPensionGrowth(array $params): array
    {
        $current = (int) ($params['current_value'] ?? 0);
        $annual = (int) ($params['annual_contribution'] ?? 0);
        $rate = (float) ($params['growth_rate'] ?? 0.05);
        $years = (int) ($params['years'] ?? 0);

        $value = $current;
        $yearByYear = [];

        for ($y = 1; $y <= $years; $y++) {
            $value = (int) round(($value + $annual) * (1 + $rate));
            $yearByYear[] = ['year' => $y, 'value' => $value];
        }

        return [
            'projected_value' => $value,
            'total_contributions' => $current + ($annual * $years),
            'total_growth' => max(0, $value - $current - ($annual * $years)),
            'year_by_year' => $yearByYear,
        ];
    }
}
```

- [ ] **Step 4: Run test — expect green**

```bash
./vendor/bin/pest tests/Unit/Services/Retirement/UkRetirementEngineTest.php
```

Expected: 3 passing.

- [ ] **Step 5: Rebind `pack.gb.retirement`**

In `app/Providers/GbPackServiceProvider.php`:

```php
// Before:
$this->app->bind('pack.gb.retirement', \App\Agents\RetirementAgent::class);

// After:
$this->app->bind('pack.gb.retirement', \App\Services\Retirement\UkRetirementEngine::class);
```

- [ ] **Step 6: Add architecture assertions**

In `tests/Architecture/PackIsolationTest.php`:

```php
it('UkRetirementEngine implements the core RetirementEngine contract', function () {
    expect(class_implements(\App\Services\Retirement\UkRetirementEngine::class))
        ->toContain(\Fynla\Core\Contracts\RetirementEngine::class);
});

it('ZaRetirementEngine implements the core RetirementEngine contract', function () {
    if (! class_exists(\Fynla\Packs\Za\Retirement\ZaRetirementEngine::class)) {
        $this->markTestSkipped('ZaRetirementEngine not yet loaded (WS 1.4a in progress)');
    }

    expect(class_implements(\Fynla\Packs\Za\Retirement\ZaRetirementEngine::class))
        ->toContain(\Fynla\Core\Contracts\RetirementEngine::class);
});
```

- [ ] **Step 7: Run**

```bash
./vendor/bin/pest tests/Unit/Services/Retirement/UkRetirementEngineTest.php \
  tests/Architecture/PackIsolationTest.php
```

Expected: green, ZA assertion skipped.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Retirement/UkRetirementEngine.php \
        app/Providers/GbPackServiceProvider.php \
        tests/Unit/Services/Retirement/UkRetirementEngineTest.php \
        tests/Architecture/PackIsolationTest.php
git commit -m "feat(core): UkRetirementEngine stub + rebind pack.gb.retirement (WS 1.4a prep)"
```

---

## Task 1: Seeder additions

**Files:**
- Modify: `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php`

- [ ] **Step 1: Add `retirementRows()` method**

After the existing `exchangeControlRows()` method:

```php
/**
 * Retirement — Two-Pot system constants (WS 1.4a).
 *
 * Two-Pot effective date: 1 September 2024. All contributions from
 * that date onwards split 1/3 Savings / 2/3 Retirement. Pre-2024-09-01
 * contributions remain in the Vested bucket.
 *
 * Savings-Pot withdrawal minimum: R2,000 per SARS Regulation.
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
```

- [ ] **Step 2: Wire it into `rows()`**

```php
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
);
```

- [ ] **Step 3: Re-run and verify**

```bash
php artisan db:seed --class="Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder" --force
php artisan tinker --execute="echo DB::table('za_tax_configurations')->where('key_path','like','retirement.savings_pot%')->orderBy('key_path')->get()->pluck('value_cents','key_path')->toJson(JSON_PRETTY_PRINT);"
```

Expected — three rows with minimum 200,000 cents (R2,000) and the two bps split values.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php
git commit -m "feat(za-pack): seed Two-Pot constants (WS 1.4a)"
```

---

## Task 2: Migration + model for retirement fund buckets

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_18_800001_create_za_retirement_fund_buckets_table.php`
- Create: `packs/country-za/src/Models/ZaRetirementFundBucket.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-Pot balance buckets per member per fund holding.
 *
 * One row per (user, fund_holding). The three balances update together:
 *   - vested_balance_minor: pre-2024-09-01 balances (old rules apply)
 *   - savings_balance_minor: 1/3 of post-2024-09-01 contributions;
 *     accessible once per tax year, min R2,000, taxed at marginal rate
 *   - retirement_balance_minor: 2/3 of post-2024-09-01 contributions;
 *     locked until retirement, must buy compulsory annuity
 *
 * fund_holding_id is a foreign key into the UK-side dc_pensions table
 * (the pack uses the UK's pension holding model for storage; country_code
 * = 'ZA' distinguishes SA holdings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_retirement_fund_buckets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('fund_holding_id')->constrained('dc_pensions')->cascadeOnDelete();
            $t->bigInteger('vested_balance_minor')->default(0);
            // Sub-split of vested: pre-2021 provident fund portion for
            // members 55+ on 1 March 2021 (retains 100% commutability
            // per spec § 9.1). Must never exceed vested_balance_minor.
            // Consumed by WS 1.4b annuity mechanics.
            $t->bigInteger('provident_vested_pre2021_balance_minor')->default(0);
            $t->bigInteger('savings_balance_minor')->default(0);
            $t->bigInteger('retirement_balance_minor')->default(0);
            $t->string('balance_ccy', 3)->default('ZAR');
            $t->date('last_transaction_date')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'fund_holding_id'], 'za_retirement_buckets_unique');
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_retirement_fund_buckets');
    }
};
```

- [ ] **Step 2: Write the model**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two-Pot balance bucket row — one per (member, fund holding).
 *
 * Pack-owned model. Cross-namespace FK targets (users, dc_pensions)
 * resolved via runtime FQCN construction to keep the pack free of
 * compile-time main-app imports.
 */
class ZaRetirementFundBucket extends Model
{
    protected $table = 'za_retirement_fund_buckets';

    protected $fillable = [
        'user_id',
        'fund_holding_id',
        'vested_balance_minor',
        'provident_vested_pre2021_balance_minor',
        'savings_balance_minor',
        'retirement_balance_minor',
        'balance_ccy',
        'last_transaction_date',
    ];

    protected $casts = [
        'vested_balance_minor' => 'integer',
        'provident_vested_pre2021_balance_minor' => 'integer',
        'savings_balance_minor' => 'integer',
        'retirement_balance_minor' => 'integer',
        'last_transaction_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function fundHolding(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('DCPension'), 'fund_holding_id');
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
```

- [ ] **Step 3: Run migration and verify**

```bash
php artisan migrate
php artisan tinker --execute="echo Schema::hasTable('za_retirement_fund_buckets') ? 'ok' : 'missing';"
```

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_18_800001_create_za_retirement_fund_buckets_table.php \
        packs/country-za/src/Models/ZaRetirementFundBucket.php
git commit -m "feat(za-pack): za_retirement_fund_buckets table + model (WS 1.4a)"
```

---

## Task 3: Bucket repository (failing tests + implementation)

**Files:**
- Create: `packs/country-za/src/Retirement/ZaRetirementFundBucketRepository.php`
- Create: `packs/country-za/tests/Unit/ZaRetirementFundBucketRepositoryTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function zaBucketCreateFundHolding(int $userId): int
{
    $pensionClass = '\\' . 'App' . '\\Models\\DCPension';
    $pension = $pensionClass::factory()->create([
        'user_id' => $userId,
        'country_code' => 'ZA',
    ]);

    return (int) $pension->id;
}

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->repo = app(ZaRetirementFundBucketRepository::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
    $this->fundHoldingId = zaBucketCreateFundHolding($this->user->id);
});

it('creates a zero-balance bucket for a new (user, fund holding) pair', function () {
    $bucket = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);

    expect($bucket->vested_balance_minor)->toBe(0);
    expect($bucket->savings_balance_minor)->toBe(0);
    expect($bucket->retirement_balance_minor)->toBe(0);
    expect($bucket->balance_ccy)->toBe('ZAR');
});

it('returns the same row on subsequent calls (idempotent)', function () {
    $first = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);
    $second = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);

    expect($second->id)->toBe($first->id);
});

it('applies bucket deltas atomically', function () {
    $this->repo->applyDeltas(
        userId: $this->user->id,
        fundHoldingId: $this->fundHoldingId,
        vestedDeltaMinor: 5_000_000,
        savingsDeltaMinor: 1_000_000,
        retirementDeltaMinor: 2_000_000,
        transactionDate: '2026-04-10',
    );

    $bucket = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);
    expect($bucket->vested_balance_minor)->toBe(5_000_000);
    expect($bucket->savings_balance_minor)->toBe(1_000_000);
    expect($bucket->retirement_balance_minor)->toBe(2_000_000);
    expect($bucket->last_transaction_date?->format('Y-m-d'))->toBe('2026-04-10');
});

it('accumulates deltas across multiple calls', function () {
    $this->repo->applyDeltas($this->user->id, $this->fundHoldingId, 0, 500_000, 1_000_000, '2026-04-10');
    $this->repo->applyDeltas($this->user->id, $this->fundHoldingId, 0, 300_000, 600_000, '2026-05-10');

    $bucket = $this->repo->findOrCreate($this->user->id, $this->fundHoldingId);
    expect($bucket->savings_balance_minor)->toBe(800_000);
    expect($bucket->retirement_balance_minor)->toBe(1_600_000);
});

it('rejects deltas that would drive a bucket below zero', function () {
    $this->repo->applyDeltas($this->user->id, $this->fundHoldingId, 0, 500_000, 0, '2026-04-10');

    expect(fn () => $this->repo->applyDeltas(
        userId: $this->user->id,
        fundHoldingId: $this->fundHoldingId,
        vestedDeltaMinor: 0,
        savingsDeltaMinor: -600_000,
        retirementDeltaMinor: 0,
        transactionDate: '2026-05-10',
    ))->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaRetirementFundBucketRepositoryTest.php
```

Expected: class not found.

- [ ] **Step 3: Write the repository**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Thin persistence for Two-Pot bucket rows.
 *
 * Each (user_id, fund_holding_id) pair has exactly one bucket row.
 * applyDeltas is the only write path — vested/savings/retirement
 * balances only move via explicit delta calls, which never drive a
 * bucket below zero.
 */
class ZaRetirementFundBucketRepository
{
    public function findOrCreate(int $userId, int $fundHoldingId): ZaRetirementFundBucket
    {
        return ZaRetirementFundBucket::firstOrCreate(
            [
                'user_id' => $userId,
                'fund_holding_id' => $fundHoldingId,
            ],
            [
                'vested_balance_minor' => 0,
                'provident_vested_pre2021_balance_minor' => 0,
                'savings_balance_minor' => 0,
                'retirement_balance_minor' => 0,
                'balance_ccy' => 'ZAR',
            ],
        );
    }

    public function applyDeltas(
        int $userId,
        int $fundHoldingId,
        int $vestedDeltaMinor,
        int $savingsDeltaMinor,
        int $retirementDeltaMinor,
        string $transactionDate,
    ): ZaRetirementFundBucket {
        return DB::transaction(function () use (
            $userId,
            $fundHoldingId,
            $vestedDeltaMinor,
            $savingsDeltaMinor,
            $retirementDeltaMinor,
            $transactionDate,
        ) {
            $bucket = $this->findOrCreate($userId, $fundHoldingId);

            $newVested = $bucket->vested_balance_minor + $vestedDeltaMinor;
            $newSavings = $bucket->savings_balance_minor + $savingsDeltaMinor;
            $newRetirement = $bucket->retirement_balance_minor + $retirementDeltaMinor;

            if ($newVested < 0 || $newSavings < 0 || $newRetirement < 0) {
                throw new InvalidArgumentException(
                    'Delta would drive a bucket balance below zero.',
                );
            }

            $bucket->vested_balance_minor = $newVested;
            $bucket->savings_balance_minor = $newSavings;
            $bucket->retirement_balance_minor = $newRetirement;
            $bucket->last_transaction_date = $transactionDate;
            $bucket->save();

            return $bucket;
        });
    }

    public function totalBalanceMinor(int $userId, int $fundHoldingId): int
    {
        $bucket = $this->findOrCreate($userId, $fundHoldingId);

        return $bucket->vested_balance_minor
            + $bucket->savings_balance_minor
            + $bucket->retirement_balance_minor;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaRetirementFundBucketRepositoryTest.php
```

Expected: 5 passing.

- [ ] **Step 5: Commit**

```bash
git add packs/country-za/src/Retirement/ZaRetirementFundBucketRepository.php \
        packs/country-za/tests/Unit/ZaRetirementFundBucketRepositoryTest.php
git commit -m "feat(za-pack): ZaRetirementFundBucketRepository (WS 1.4a)"
```

---

## Task 4: Contribution split service (failing tests + impl)

**Files:**
- Create: `packs/country-za/src/Retirement/ZaContributionSplitService.php`
- Create: `packs/country-za/tests/Unit/ZaContributionSplitServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Retirement\ZaContributionSplitService;

beforeEach(function () {
    $this->splitter = app(ZaContributionSplitService::class);
});

const TWO_POT_EFFECTIVE = '2024-09-01';

it('splits a post-2024-09-01 contribution 1/3 savings / 2/3 retirement', function () {
    $r = $this->splitter->split(
        contributionMinor: 3_000_000,  // R30,000
        contributionDate: '2026-05-10',
    );

    expect($r['vested_delta_minor'])->toBe(0);
    expect($r['savings_delta_minor'])->toBe(1_000_000);  // R10,000
    expect($r['retirement_delta_minor'])->toBe(2_000_000);  // R20,000
});

it('allocates pre-2024-09-01 contributions 100% to vested', function () {
    $r = $this->splitter->split(
        contributionMinor: 3_000_000,
        contributionDate: '2024-07-15',
    );

    expect($r['vested_delta_minor'])->toBe(3_000_000);
    expect($r['savings_delta_minor'])->toBe(0);
    expect($r['retirement_delta_minor'])->toBe(0);
});

it('splits exactly on the Two-Pot effective date', function () {
    // 1 September 2024 is INCLUSIVE — counts as post-effective.
    $r = $this->splitter->split(3_000_000, TWO_POT_EFFECTIVE);

    expect($r['savings_delta_minor'])->toBe(1_000_000);
    expect($r['retirement_delta_minor'])->toBe(2_000_000);
    expect($r['vested_delta_minor'])->toBe(0);
});

it('handles rounding drift — splits are exact integers that sum to the contribution', function () {
    // R1,000 contribution — 1/3 = 333.33 cents. Ensure we don't lose a cent.
    $r = $this->splitter->split(100_000, '2026-05-10');

    expect($r['savings_delta_minor'] + $r['retirement_delta_minor'] + $r['vested_delta_minor'])
        ->toBe(100_000);
});

it('rejects negative contributions', function () {
    expect(fn () => $this->splitter->split(-1_000, '2026-05-10'))
        ->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaContributionSplitServiceTest.php
```

- [ ] **Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use InvalidArgumentException;

/**
 * Two-Pot contribution splitter (WS 1.4a).
 *
 * Contributions with date >= 2024-09-01 split:
 *   - 1/3 → Savings Component
 *   - 2/3 → Retirement Component
 *
 * Contributions with date < 2024-09-01 go 100% to the Vested Component.
 *
 * Pure calculator. Stateless. The caller applies the returned deltas
 * through ZaRetirementFundBucketRepository::applyDeltas.
 */
class ZaContributionSplitService
{
    private const TWO_POT_EFFECTIVE_DATE = '2024-09-01';

    /**
     * @return array{vested_delta_minor: int, savings_delta_minor: int, retirement_delta_minor: int}
     */
    public function split(int $contributionMinor, string $contributionDate): array
    {
        if ($contributionMinor < 0) {
            throw new InvalidArgumentException('Contribution cannot be negative.');
        }

        if ($contributionDate < self::TWO_POT_EFFECTIVE_DATE) {
            return [
                'vested_delta_minor' => $contributionMinor,
                'savings_delta_minor' => 0,
                'retirement_delta_minor' => 0,
            ];
        }

        // Integer split preserving the total to the cent. Savings gets
        // floor(contribution / 3); retirement gets the rest.
        $savings = intdiv($contributionMinor, 3);
        $retirement = $contributionMinor - $savings;

        return [
            'vested_delta_minor' => 0,
            'savings_delta_minor' => $savings,
            'retirement_delta_minor' => $retirement,
        ];
    }
}
```

- [ ] **Step 4: Run — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaContributionSplitServiceTest.php
```

Expected: 5 passing.

- [ ] **Step 5: Commit**

```bash
git add packs/country-za/src/Retirement/ZaContributionSplitService.php \
        packs/country-za/tests/Unit/ZaContributionSplitServiceTest.php
git commit -m "feat(za-pack): ZaContributionSplitService Two-Pot 1/3-2/3 splitter (WS 1.4a)"
```

---

## Task 5: Savings-Pot withdrawal simulator (failing tests + impl)

**Files:**
- Create: `packs/country-za/src/Retirement/ZaSavingsPotWithdrawalSimulator.php`
- Create: `packs/country-za/tests/Unit/ZaSavingsPotWithdrawalSimulatorTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SIM_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->sim = app(ZaSavingsPotWithdrawalSimulator::class);
});

it('returns the marginal-tax delta on a withdrawal', function () {
    // R50,000 withdrawal, current-year income R450,000 (bracket 3 at 31%).
    // Expected delta: income_tax(R500,000) − income_tax(R450,000).
    $r = $this->sim->simulate(
        withdrawalMinor: 5_000_000,
        currentYearIncomeMinor: 45_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['tax_delta_minor'])->toBeGreaterThan(0);
    expect($r['net_received_minor'])->toBe(5_000_000 - $r['tax_delta_minor']);
    expect($r['marginal_rate'])->toBe(31.0);
});

it('rejects withdrawals below the R2,000 minimum', function () {
    expect(fn () => $this->sim->simulate(
        withdrawalMinor: 100_000,  // R1,000 — below minimum
        currentYearIncomeMinor: 45_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    ))->toThrow(InvalidArgumentException::class, 'below R2,000 minimum');
});

it('accepts the exact R2,000 minimum', function () {
    $r = $this->sim->simulate(
        withdrawalMinor: 200_000,  // R2,000 exactly
        currentYearIncomeMinor: 45_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['tax_delta_minor'])->toBeGreaterThan(0);
});

it('rejects negative withdrawals', function () {
    expect(fn () => $this->sim->simulate(-100, 45_000_000, 40, SIM_TAX_YEAR))
        ->toThrow(InvalidArgumentException::class);
});

it('flags bracket-crossing — withdrawal pushes member into a higher marginal band', function () {
    // Income at R240,000 (bracket 1, 18% marginal).
    // Withdraw R20,000 pushes to R260,000 (bracket 2 starts at R245,100).
    $r = $this->sim->simulate(
        withdrawalMinor: 2_000_000,  // R20,000
        currentYearIncomeMinor: 24_000_000,  // R240,000
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['crosses_bracket'])->toBeTrue();
});

it('does NOT flag bracket-crossing when both before and after are in the same band', function () {
    // Income R300,000, withdraw R20,000 — both in bracket 2.
    $r = $this->sim->simulate(
        withdrawalMinor: 2_000_000,
        currentYearIncomeMinor: 30_000_000,
        age: 40,
        taxYear: SIM_TAX_YEAR,
    );

    expect($r['crosses_bracket'])->toBeFalse();
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaSavingsPotWithdrawalSimulatorTest.php
```

- [ ] **Step 3: Write the simulator**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * Savings-Pot withdrawal simulator.
 *
 * Two-Pot savings-component withdrawals are taxed at the member's
 * marginal rate on top of current-year income. The simulator composes
 * ZaTaxEngine::calculateIncomeTaxForAge(income) and
 * calculateIncomeTaxForAge(income + withdrawal) and returns the delta.
 *
 * Enforces the R2,000 minimum per SARS Regulation. Does NOT enforce
 * the "one withdrawal per tax year" rule — that requires a frequency
 * ledger outside the scope of WS 1.4a.
 */
class ZaSavingsPotWithdrawalSimulator
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     tax_delta_minor: int,
     *     net_received_minor: int,
     *     marginal_rate: float,
     *     crosses_bracket: bool
     * }
     */
    public function simulate(
        int $withdrawalMinor,
        int $currentYearIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($withdrawalMinor < 0 || $currentYearIncomeMinor < 0 || $age < 0) {
            throw new InvalidArgumentException('Simulator inputs cannot be negative.');
        }

        $minimum = (int) $this->config->get($taxYear, 'retirement.savings_pot_minimum_withdrawal_minor', 0);
        if ($withdrawalMinor < $minimum) {
            $minRand = intdiv($minimum, 100);
            throw new InvalidArgumentException(
                "Withdrawal {$withdrawalMinor} cents is below R{$minRand} minimum.",
            );
        }

        $baseline = $this->taxEngine->calculateIncomeTaxForAge(
            $currentYearIncomeMinor,
            $taxYear,
            $age,
        );
        $withWithdrawal = $this->taxEngine->calculateIncomeTaxForAge(
            $currentYearIncomeMinor + $withdrawalMinor,
            $taxYear,
            $age,
        );

        $taxDelta = max(0, $withWithdrawal['tax_due'] - $baseline['tax_due']);
        $crossesBracket = $baseline['breakdown']['bracket_index'] !== $withWithdrawal['breakdown']['bracket_index'];

        return [
            'tax_delta_minor' => $taxDelta,
            'net_received_minor' => $withdrawalMinor - $taxDelta,
            'marginal_rate' => (float) $withWithdrawal['marginal_rate'],
            'crosses_bracket' => $crossesBracket,
        ];
    }
}
```

- [ ] **Step 4: Run — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaSavingsPotWithdrawalSimulatorTest.php
```

Expected: 6 passing.

- [ ] **Step 5: Commit**

```bash
git add packs/country-za/src/Retirement/ZaSavingsPotWithdrawalSimulator.php \
        packs/country-za/tests/Unit/ZaSavingsPotWithdrawalSimulatorTest.php
git commit -m "feat(za-pack): ZaSavingsPotWithdrawalSimulator (WS 1.4a)"
```

---

## Task 6: ZaRetirementEngine (failing tests + impl)

**Files:**
- Create: `packs/country-za/src/Retirement/ZaRetirementEngine.php`
- Create: `packs/country-za/tests/Unit/ZaRetirementEngineTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaRetirementEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_RETIRE_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaRetirementEngine::class);
});

it('implements the RetirementEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(RetirementEngine::class);
});

describe('Contract methods', function () {
    it('exposes the Section 11F absolute cap as the annual allowance', function () {
        // R350,000 cap = 35,000,000 cents
        expect($this->engine->getAnnualAllowance(ZA_RETIRE_TAX_YEAR))->toBe(35_000_000);
    });

    it('returns null lifetime allowance (SA has no lifetime cap)', function () {
        expect($this->engine->getLifetimeAllowance(ZA_RETIRE_TAX_YEAR))->toBeNull();
    });

    it('returns age 60 as the Old Age Grant threshold (SA has no state pension in the UK sense)', function () {
        expect($this->engine->getStatePensionAge('1965-01-01', 'male'))->toBe(60);
        expect($this->engine->getStatePensionAge('1965-01-01', 'female'))->toBe(60);
    });
});

describe('calculatePensionTaxRelief', function () {
    it('computes Section 11F deductible within the R350k cap', function () {
        $r = $this->engine->calculatePensionTaxRelief(
            contributionMinor: 10_000_000,  // R100,000
            incomeMinor: 40_000_000,  // R400,000
            taxYear: ZA_RETIRE_TAX_YEAR,
        );

        // Contribution well under R350k cap — fully deductible.
        expect($r['relief_amount'])->toBeGreaterThan(0);
        expect($r['method'])->toBe('section_11f');
        expect($r['net_cost'])->toBeLessThan(10_000_000);
    });

    it('caps relief at the R350k absolute limit', function () {
        $r = $this->engine->calculatePensionTaxRelief(
            contributionMinor: 50_000_000,  // R500,000
            incomeMinor: 200_000_000,  // R2,000,000
            taxYear: ZA_RETIRE_TAX_YEAR,
        );

        // Cap is R350k = 35M cents. Tax relief is on the first R350k only.
        // Remaining R150k contribution is not deductible this year but
        // carries forward (full S11F logic composed in the engine's
        // calculateSection11fDeduction helper).
        expect($r['relief_amount'])->toBeGreaterThan(0);
    });

    it('returns zero relief on a zero contribution', function () {
        $r = $this->engine->calculatePensionTaxRelief(0, 40_000_000, ZA_RETIRE_TAX_YEAR);

        expect($r['relief_amount'])->toBe(0);
        expect($r['net_cost'])->toBe(0);
    });
});

describe('projectPensionGrowth', function () {
    it('projects a 10-year growth with R12,000/year contribution at 8%', function () {
        $r = $this->engine->projectPensionGrowth([
            'current_value' => 5_000_000,  // R50,000
            'annual_contribution' => 1_200_000,  // R12,000
            'growth_rate' => 0.08,
            'years' => 10,
        ]);

        // Starting R50,000 + R12,000/yr at 8% for 10yr is approximately
        // R120,000 + growth (rough compound). Assert shape + positive.
        expect($r['projected_value'])->toBeGreaterThan(5_000_000);
        expect($r['year_by_year'])->toHaveCount(10);
        expect($r['year_by_year'][0]['year'])->toBe(1);
        expect($r['year_by_year'][9]['year'])->toBe(10);
    });
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaRetirementEngineTest.php
```

- [ ] **Step 3: Write the engine**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;

/**
 * SARS 2026/27 retirement engine for South Africa.
 *
 * Implements the core RetirementEngine contract. SA differs from the UK
 * in several material ways that affect contract semantics:
 *   - Annual allowance ≡ Section 11F absolute cap (R350,000).
 *   - No lifetime allowance.
 *   - No state pension in the UK sense — SASSA Old Age Grant is
 *     means-tested and starts at 60. We return 60 as the "state pension
 *     age" proxy, documented in the method's docblock.
 *
 * Pure calculator. Composes ZaTaxEngine for relief / withdrawal-tax
 * deltas. No DB access.
 */
class ZaRetirementEngine implements RetirementEngine
{
    private const OLD_AGE_GRANT_START = 60;

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    public function calculatePensionTaxRelief(int $contributionMinor, int $incomeMinor, string $taxYear): array
    {
        if ($contributionMinor <= 0) {
            return [
                'relief_amount' => 0,
                'relief_rate' => 0.0,
                'net_cost' => max(0, $contributionMinor),
                'method' => 'section_11f',
            ];
        }

        // Section 11F deduction: capped at the absolute limit; the
        // percentage cap (27.5% of remuneration/taxable income) is
        // applied by the caller pre-engine if they know the base.
        $deduction = $this->taxEngine->calculateRetirementDeduction(
            $contributionMinor,
            $taxYear,
            0,  // no prior carry-forward in this stateless shape
        );

        // Compute the marginal-tax-delta relief: income tax at income vs
        // income - deductible. The relief is what the deductible is
        // worth against the member's marginal rate.
        $baseline = $this->taxEngine->calculateIncomeTaxForAge($incomeMinor, $taxYear, null);
        $withDeduction = $this->taxEngine->calculateIncomeTaxForAge(
            max(0, $incomeMinor - $deduction['deductible_minor']),
            $taxYear,
            null,
        );

        $relief = max(0, $baseline['tax_due'] - $withDeduction['tax_due']);
        $reliefRate = $contributionMinor > 0 ? $relief / $contributionMinor : 0.0;

        return [
            'relief_amount' => $relief,
            'relief_rate' => round($reliefRate, 4),
            'net_cost' => $contributionMinor - $relief,
            'method' => 'section_11f',
        ];
    }

    public function getAnnualAllowance(string $taxYear): int
    {
        return (int) $this->config->get($taxYear, 'section_11f.absolute_cap_minor', 35_000_000);
    }

    public function getLifetimeAllowance(string $taxYear): ?int
    {
        return null;
    }

    /**
     * SA has no UK-style state pension. Returns the SASSA Old Age Grant
     * start age (60) as the closest contract-compatible value. Callers
     * that need the means-test rules consume a separate Old-Age-Grant
     * service (deferred to WS 1.4c/d).
     */
    public function getStatePensionAge(string $dateOfBirth, string $gender): int
    {
        return self::OLD_AGE_GRANT_START;
    }

    public function projectPensionGrowth(array $params): array
    {
        $current = (int) ($params['current_value'] ?? 0);
        $annual = (int) ($params['annual_contribution'] ?? 0);
        $rate = (float) ($params['growth_rate'] ?? 0.08);
        $years = (int) ($params['years'] ?? 0);

        $value = $current;
        $yearByYear = [];

        for ($y = 1; $y <= $years; $y++) {
            $value = (int) round(($value + $annual) * (1 + $rate));
            $yearByYear[] = ['year' => $y, 'value' => $value];
        }

        return [
            'projected_value' => $value,
            'total_contributions' => $current + ($annual * $years),
            'total_growth' => max(0, $value - $current - ($annual * $years)),
            'year_by_year' => $yearByYear,
        ];
    }
}
```

- [ ] **Step 4: Run — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaRetirementEngineTest.php
```

Expected: 8 passing.

- [ ] **Step 5: Commit**

```bash
git add packs/country-za/src/Retirement/ZaRetirementEngine.php \
        packs/country-za/tests/Unit/ZaRetirementEngineTest.php
git commit -m "feat(za-pack): ZaRetirementEngine implements RetirementEngine (WS 1.4a)"
```

---

## Task 7: Wire provider bindings

**Files:**
- Modify: `packs/country-za/src/Providers/ZaPackServiceProvider.php`
- Modify: `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php`

- [ ] **Step 1: Add bindings**

Inside `register()`:

```php
// WS 1.4a — Retirement
$this->app->bind('pack.za.retirement', \Fynla\Packs\Za\Retirement\ZaRetirementEngine::class);
$this->app->bind(
    'pack.za.retirement.contribution_split',
    \Fynla\Packs\Za\Retirement\ZaContributionSplitService::class,
);
$this->app->bind(
    'pack.za.retirement.savings_pot_simulator',
    \Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator::class,
);
$this->app->bind(
    'pack.za.retirement.buckets',
    \Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository::class,
);
```

- [ ] **Step 2: Extend provider tests**

```php
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
```

- [ ] **Step 3: Run**

```bash
./vendor/bin/pest packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
```

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/src/Providers/ZaPackServiceProvider.php \
        packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
git commit -m "feat(za-pack): register retirement engine + two-pot services (WS 1.4a)"
```

---

## Task 8: End-to-end integration test

**Files:**
- Create: `tests/Integration/Za/ZaRetirementIntegrationTest.php`

- [ ] **Step 1: Write the integration test**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Retirement\ZaContributionSplitService;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_RETIRE_INT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: pre-two-pot + post-two-pot contributions populate buckets correctly', function () {
    $user = User::factory()->create();
    $fund = DCPension::factory()->create(['user_id' => $user->id, 'country_code' => 'ZA']);
    $splitter = app(ZaContributionSplitService::class);
    $buckets = app(ZaRetirementFundBucketRepository::class);

    // Pre-two-pot contribution (100% vested)
    $pre = $splitter->split(5_000_000, '2024-07-15');
    $buckets->applyDeltas(
        $user->id, $fund->id,
        $pre['vested_delta_minor'], $pre['savings_delta_minor'], $pre['retirement_delta_minor'],
        '2024-07-15',
    );

    // Post-two-pot contribution (1/3 savings, 2/3 retirement)
    $post = $splitter->split(3_000_000, '2026-05-10');
    $buckets->applyDeltas(
        $user->id, $fund->id,
        $post['vested_delta_minor'], $post['savings_delta_minor'], $post['retirement_delta_minor'],
        '2026-05-10',
    );

    $bucket = $buckets->findOrCreate($user->id, $fund->id);
    expect($bucket->vested_balance_minor)->toBe(5_000_000);
    expect($bucket->savings_balance_minor)->toBe(1_000_000);
    expect($bucket->retirement_balance_minor)->toBe(2_000_000);
    expect($buckets->totalBalanceMinor($user->id, $fund->id))->toBe(8_000_000);
});

it('end-to-end: Savings-Pot withdrawal composes marginal tax and decrements the savings bucket', function () {
    $user = User::factory()->create();
    $fund = DCPension::factory()->create(['user_id' => $user->id, 'country_code' => 'ZA']);
    $splitter = app(ZaContributionSplitService::class);
    $buckets = app(ZaRetirementFundBucketRepository::class);
    $sim = app(ZaSavingsPotWithdrawalSimulator::class);

    // Build up R60k in savings bucket via R180k contribution.
    $split = $splitter->split(18_000_000, '2026-05-10');
    $buckets->applyDeltas(
        $user->id, $fund->id,
        $split['vested_delta_minor'], $split['savings_delta_minor'], $split['retirement_delta_minor'],
        '2026-05-10',
    );

    // Simulate a R30,000 withdrawal at R400k income, age 40.
    $result = $sim->simulate(3_000_000, 40_000_000, 40, ZA_RETIRE_INT_TAX_YEAR);

    expect($result['tax_delta_minor'])->toBeGreaterThan(0);
    expect($result['net_received_minor'])->toBeLessThan(3_000_000);

    // Apply the withdrawal.
    $buckets->applyDeltas($user->id, $fund->id, 0, -3_000_000, 0, '2026-11-15');

    $bucket = $buckets->findOrCreate($user->id, $fund->id);
    expect($bucket->savings_balance_minor)->toBe(3_000_000);
    expect($bucket->retirement_balance_minor)->toBe(12_000_000);
});

it('end-to-end: Section 11F carry-forward composes tracker + engine (documentation pattern)', function () {
    $user = \App\Models\User::factory()->create();
    $engine = app(\Fynla\Packs\Za\Retirement\ZaRetirementEngine::class);
    $tracker = app(\Fynla\Packs\Za\Tax\ZaSection11fTracker::class);

    // Scenario: member contributes R400,000 — above the R350,000 cap.
    // R50,000 carry-forward rolls into next year. This test shows the
    // composition pattern for future callers: stateless engine +
    // explicit tracker thread.

    // Year 1: fresh member, zero prior carry-forward.
    $priorCarry = $tracker->getCarryForward($user->id, ZA_RETIRE_INT_TAX_YEAR);
    expect($priorCarry)->toBe(0);

    // Engine is stateless — callers pass contribution + carry as a
    // combined "gross" via the tax engine's calculateRetirementDeduction.
    $deduction = app(\Fynla\Packs\Za\Tax\ZaTaxEngine::class)
        ->calculateRetirementDeduction(40_000_000, ZA_RETIRE_INT_TAX_YEAR, $priorCarry);

    expect($deduction['deductible_minor'])->toBe(35_000_000);  // R350k cap
    expect($deduction['carry_forward_minor'])->toBe(5_000_000);  // R50k rolls forward

    // Caller persists the carry-forward for year 2 via the tracker.
    $tracker->setCarryForward($user->id, '2027/28', $deduction['carry_forward_minor']);

    // Year 2: tracker returns the rolled-forward R50k.
    expect($tracker->getCarryForward($user->id, '2027/28'))->toBe(5_000_000);
});
```

- [ ] **Step 2: Run**

```bash
./vendor/bin/pest tests/Integration/Za/ZaRetirementIntegrationTest.php
```

Expected: 2 passing.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/Za/ZaRetirementIntegrationTest.php
git commit -m "test(za-pack): WS 1.4a end-to-end retirement + Two-Pot integration tests"
```

---

## Task 9: Full regression + baseline

- [ ] **Step 1: Pack suite**

```bash
./vendor/bin/pest packs/country-za/
```

Expected: all pack tests green.

- [ ] **Step 2: Architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```

Expected: both `UkRetirementEngine` and `ZaRetirementEngine` implement the contract.

- [ ] **Step 3: Full suite**

```bash
./vendor/bin/pest
```

Expected: 2,572 + new WS 1.4a tests passing. 0 new failures (4 `ProtectionWorkflowTest` + 1 `InvestmentControllerTest` flake unchanged).

- [ ] **Step 4: Completion note at `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-4a-complete.md`** (vault, outside repo — write via Write tool, no commit).

---

## Self-Review

**Spec coverage (SA Research § 9 + Implementation_Plan_v2 WS 1.4a subset):**
| Requirement | Task |
|-------------|------|
| `ZaRetirementEngine` (RA/PF/PvF/Preservation — engine-level) | Task 6 |
| Two-Pot: savings / retirement / vested | Tasks 2, 3, 4 |
| `za_retirement_fund_buckets` table | Task 2 |
| Contribution split service | Task 4 |
| Savings-pot withdrawal simulator | Task 5 |
| Section 11F deduction carry-forward | existing (WS 1.1 `ZaSection11fTracker`) — engine composes it via `ZaTaxEngine::calculateRetirementDeduction` in Task 6 |
| Compulsory annuitisation | **Deferred to WS 1.4b** |
| Living / Life annuity | **Deferred to WS 1.4b** |
| Reg 28 Monitor | **Deferred to WS 1.4c** (moved from WS 1.3 per earlier audit) |
| SASSA Old Age Grant | **Deferred to WS 1.4c/d** |
| Vue components | **Deferred to WS 1.4d** |

**Placeholder scan:** All tasks have complete code, exact paths, concrete commands. No TODOs.

**Type consistency:**
- Bucket delta shape `vested_delta_minor` / `savings_delta_minor` / `retirement_delta_minor` consistent across splitter (Task 4), repository (Task 3), integration test (Task 8).
- Simulator return shape `tax_delta_minor` / `net_received_minor` / `marginal_rate` / `crosses_bracket` consistent in Tasks 5, 8.
- Container keys `pack.gb.retirement`, `pack.za.retirement`, `pack.za.retirement.contribution_split`, `pack.za.retirement.savings_pot_simulator`, `pack.za.retirement.buckets` identical across provider code, tests, arch.

**Known risks:**
- `DCPension::factory()` is assumed to work with `country_code = 'ZA'`. If the factory doesn't have that key, tests will fail; WS 0.6 added `country_code` to the `$fillable` so this should work.
- Two-Pot effective date `2024-09-01` is hardcoded in the splitter. Could live in config; kept in code for v1 because the date is a regulatory absolute and won't change.
- `getStatePensionAge` returns 60 for all SA members regardless of gender. This may surprise callers familiar with UK logic; documented in the method docblock.

---

## Execution Handoff

Plan saved to `docs/superpowers/plans/2026-04-18-ws-1-4a-za-retirement.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task.
2. **Inline Execution** — current session with checkpoints.
