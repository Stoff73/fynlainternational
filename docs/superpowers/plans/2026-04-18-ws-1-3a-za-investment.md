# WS 1.3a — SA Investment Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Amended — 18 April 2026 — conflicts resolved against codebase audit (validated by `feature-dev:code-explorer`). Two amendments vs v1 draft:
- `ZaBaseCostTracker::recordDisposal` now writes back the updated weighted-average cost basis × open quantity to the parent `holdings.cost_basis` column so the main-app record stays in sync with the lot ledger. Task 2 adds a test; Task 3 adds the write-back.
- `Implementation_Plan_v2.md` WS 1.3 amended to explicitly move Reg 28 Monitor into WS 1.4 (Retirement) — the audit correctly flagged that WS 1.3 and WS 1.4 had been contradicting each other on Reg 28 ownership. `ZaInvestmentEngine::getAssetAllocationRules()` still returns `[]` for WS 1.3a; the real Reg 28 rules will live on the retirement-side engine.

**Goal:** Ship the South Africa Investment **backend** — `ZaInvestmentEngine` implementing the existing `core/app/Core/Contracts/InvestmentEngine` contract, weighted-average base-cost lot tracker for SA CGT, endowment-wrapper tax routing, discretionary + endowment wrapper definitions, DWT composition. Delegates interest-tax to the existing `ZaSavingsEngine`. UI is WS 1.3c. Exchange Control (SDA/FIA/AIT) is WS 1.3b. Reg 28 Monitor is WS 1.4 (Retirement) — the authoritative plan was amended to reflect this.

**Architecture:**
- `ZaInvestmentEngine implements InvestmentEngine` — pure calculator composing `ZaTaxEngine` (for marginal rate), `ZaSavingsEngine` (for interest-tax and TFSA cap), `ZaTaxConfigService` (for CGT inclusion, endowment rate, DWT rate). No DB access.
- `ZaCgtCalculator` — thin CGT math: applies 40% inclusion + R40,000 annual exclusion + marginal-rate lookup; or 30% flat when `wrapper_code='endowment'`.
- `ZaBaseCostTracker` — weighted-average lot tracker over a new `za_holding_lots` table (one row per purchase event, keyed by `holding_id`). Specific-identification is a future enhancement; WS 1.3a ships weighted-average only (the default for unit trusts / ETFs per SA research doc § 8.3).
- `ZaHoldingLot` — pack-owned Eloquent model for the lot ledger.
- Container keys: `pack.za.investment`, `pack.za.investment.cgt`, `pack.za.investment.lot_tracker`.
- UK side gets a stub `App\Services\Investment\UkInvestmentEngine implements InvestmentEngine` registered at `pack.gb.investment`, mirroring the WS 1.2a pattern.
- Foreign CGT: accepts pre-translated `gain_zar` for v1. Full Eighth-Schedule FX translation is explicitly deferred to Phase 2 cross-border.

**Tech Stack:** Laravel 10, PHP 8.2 strict types, Pest v2, MySQL 8.

**Out of scope (deferred):**
- Vue components (WS 1.3c)
- API routes / controllers
- `InvestmentAgent` changes (WS 1.7)
- SDA/FIA/AIT ledger and `ZaExchangeControl` implementation (WS 1.3b)
- **Reg 28 Monitor** (WS 1.4 per SA research doc)
- Specific-identification lot tracking (weighted-average only in v1)
- Full FX translation for offshore CGT (caller supplies translated ZAR gains)
- Structured products / hedge funds (spec § 8.1 marks these out of scope for v1)

---

## File Structure

**New files (core):** None — `InvestmentEngine` contract already exists and is adequate.

**New files (ZA pack):**
- `packs/country-za/src/Investment/ZaInvestmentEngine.php` — main engine `implements InvestmentEngine`
- `packs/country-za/src/Investment/ZaCgtCalculator.php` — CGT math
- `packs/country-za/src/Investment/ZaBaseCostTracker.php` — lot persistence + weighted-average cost basis
- `packs/country-za/src/Models/ZaHoldingLot.php` — Eloquent model for the lot ledger
- `packs/country-za/database/migrations/2026_04_18_600001_create_za_holding_lots_table.php`
- `packs/country-za/tests/Unit/ZaInvestmentEngineTest.php`
- `packs/country-za/tests/Unit/ZaCgtCalculatorTest.php`
- `packs/country-za/tests/Unit/ZaBaseCostTrackerTest.php`

**New files (main app):**
- `app/Services/Investment/UkInvestmentEngine.php` — GB-side stub `implements InvestmentEngine`
- `tests/Unit/Services/Investment/UkInvestmentEngineTest.php`
- `tests/Integration/Za/ZaInvestmentIntegrationTest.php`

**Modified files:**
- `packs/country-za/src/Providers/ZaPackServiceProvider.php` — three new bindings
- `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php` — binding assertions
- `app/Providers/GbPackServiceProvider.php` — rebind `pack.gb.investment` from `InvestmentAgent::class` to `UkInvestmentEngine::class`
- `tests/Architecture/PackIsolationTest.php` — contract-implementation assertions

---

## Task 0: GB-side InvestmentEngine stub

The UK pack already binds `pack.gb.investment` to `InvestmentAgent`. That class does NOT implement the `InvestmentEngine` contract — it's an orchestrator. Mirror WS 1.2a: create a thin `UkInvestmentEngine` that implements the contract, rebind the key to it, and leave `InvestmentAgent` alone.

**Files:**
- Create: `app/Services/Investment/UkInvestmentEngine.php`
- Create: `tests/Unit/Services/Investment/UkInvestmentEngineTest.php`
- Modify: `app/Providers/GbPackServiceProvider.php`
- Modify: `tests/Architecture/PackIsolationTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Investment\UkInvestmentEngine;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Fynla\Core\Contracts\InvestmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    app(TaxConfigService::class)->clearCache();
    $this->engine = app(UkInvestmentEngine::class);
});

it('implements the InvestmentEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(InvestmentEngine::class);
});

it('lists UK tax wrappers (ISA, GIA, pension)', function () {
    $codes = array_column($this->engine->getTaxWrappers(), 'code');

    expect($codes)->toContain('isa')->toContain('gia');
});

it('returns ISA annual allowance from TaxConfigService', function () {
    $allowances = $this->engine->getAnnualAllowances('2025/26');

    expect($allowances['isa'])->toBeGreaterThanOrEqual(2_000_000);
});

it('returns empty asset allocation rules (UK has no Reg 28 analogue)', function () {
    expect($this->engine->getAssetAllocationRules())->toBe([]);
});
```

- [ ] **Step 2: Run test — expect red**

```bash
./vendor/bin/pest tests/Unit/Services/Investment/UkInvestmentEngineTest.php
```

Expected: "Class App\Services\Investment\UkInvestmentEngine does not exist."

- [ ] **Step 3: Write the stub**

```php
<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\InvestmentEngine;

/**
 * UK-side InvestmentEngine implementation.
 *
 * Exposes UK tax wrappers (ISA, GIA) and their annual allowances behind
 * the jurisdiction-uniform contract. Tax calculations are stubbed for
 * WS 1.3a — callers who need full UK investment tax composition keep
 * using the existing UK services directly until a follow-up lifts that
 * logic into the engine.
 */
class UkInvestmentEngine implements InvestmentEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {
    }

    public function getTaxWrappers(): array
    {
        return [
            [
                'code' => 'isa',
                'name' => 'Individual Savings Account',
                'description' => 'Tax-free wrapper for cash, stocks, and lifetime ISAs',
                'tax_treatment' => 'Tax-free growth and withdrawals',
            ],
            [
                'code' => 'gia',
                'name' => 'General Investment Account',
                'description' => 'Unwrapped discretionary portfolio',
                'tax_treatment' => 'CGT on disposal; dividend allowance; PSA on interest',
            ],
        ];
    }

    public function getAnnualAllowances(string $taxYear): array
    {
        $isa = $this->taxConfig->getISAAllowances();
        $isaPounds = (int) ($isa['annual_allowance'] ?? 20_000);

        return [
            'isa' => $isaPounds * 100,
            'gia' => PHP_INT_MAX,
        ];
    }

    public function calculateInvestmentTax(array $params): array
    {
        return [
            'total_tax' => 0,
            'gains_tax' => 0,
            'dividend_tax' => 0,
            'interest_tax' => 0,
            'breakdown' => [
                'note' => 'UK investment tax composition deferred to a follow-up; callers should use the existing UK services directly in the interim.',
            ],
        ];
    }

    public function getAssetAllocationRules(): array
    {
        // UK has no Reg-28 analogue. Pension allocation is regulated
        // differently and not exposed through this contract.
        return [];
    }
}
```

- [ ] **Step 4: Run test — expect green**

```bash
./vendor/bin/pest tests/Unit/Services/Investment/UkInvestmentEngineTest.php
```

Expected: 4 passing.

- [ ] **Step 5: Rebind pack.gb.investment**

In `app/Providers/GbPackServiceProvider.php`, change:

```php
$this->app->bind('pack.gb.investment', \App\Agents\InvestmentAgent::class);
```

to:

```php
// Pre-WS-1.3a this was InvestmentAgent (an orchestrator, not a
// contract implementation). UkInvestmentEngine is the thin contract
// stub; InvestmentAgent stays bound to its own class name for callers
// that need the orchestrator.
$this->app->bind('pack.gb.investment', \App\Services\Investment\UkInvestmentEngine::class);
```

- [ ] **Step 6: Add architecture assertion**

In `tests/Architecture/PackIsolationTest.php`, inside the top-level `describe('Pack Isolation', ...)`, add:

```php
it('UkInvestmentEngine implements the InvestmentEngine contract', function () {
    expect(class_implements(\App\Services\Investment\UkInvestmentEngine::class))
        ->toContain(\Fynla\Core\Contracts\InvestmentEngine::class);
});

it('ZaInvestmentEngine implements the InvestmentEngine contract', function () {
    if (! class_exists(\Fynla\Packs\Za\Investment\ZaInvestmentEngine::class)) {
        $this->markTestSkipped('ZaInvestmentEngine not yet loaded (WS 1.3a in progress)');
    }

    expect(class_implements(\Fynla\Packs\Za\Investment\ZaInvestmentEngine::class))
        ->toContain(\Fynla\Core\Contracts\InvestmentEngine::class);
});
```

- [ ] **Step 7: Full arch + test gate**

```bash
./vendor/bin/pest tests/Architecture/PackIsolationTest.php \
  tests/Unit/Services/Investment/UkInvestmentEngineTest.php
```

Expected: all green, `ZaInvestmentEngine` assertion skipped.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Investment/UkInvestmentEngine.php \
        app/Providers/GbPackServiceProvider.php \
        tests/Unit/Services/Investment/UkInvestmentEngineTest.php \
        tests/Architecture/PackIsolationTest.php
git commit -m "feat(core): UkInvestmentEngine stub + rebind pack.gb.investment (WS 1.3a prep)"
```

---

## Task 1: Migration + Eloquent model for lot ledger

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_18_600001_create_za_holding_lots_table.php`
- Create: `packs/country-za/src/Models/ZaHoldingLot.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only lot ledger for SA CGT base-cost tracking.
 *
 * One row per purchase event. Sells draw down weighted-average cost basis
 * across all open lots for the same holding; disposed_at marks lots that
 * have been fully liquidated (partial disposals decrement quantity_open
 * without setting disposed_at).
 *
 * Currency: always ZAR for v1. Offshore holdings in foreign currency
 * require FX translation at the caller; Eighth Schedule average/spot-rate
 * rules are deferred to Phase 2 cross-border.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_holding_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('holding_id')->constrained('holdings')->cascadeOnDelete();
            $t->decimal('quantity_acquired', 18, 8);
            $t->decimal('quantity_open', 18, 8);
            $t->bigInteger('acquisition_cost_minor');
            $t->string('acquisition_cost_ccy', 3)->default('ZAR');
            $t->date('acquisition_date');
            $t->timestamp('disposed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'holding_id'], 'za_lots_user_holding_idx');
            $t->index('acquisition_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_holding_lots');
    }
};
```

- [ ] **Step 2: Write the Eloquent model**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lot ledger entry — one row per purchase event on a holding.
 *
 * Pack-owned model. Cross-namespace FK targets (users, holdings) are
 * resolved via runtime FQCN construction to keep the pack free of
 * compile-time App\\ imports (pack-isolation arch rule).
 */
class ZaHoldingLot extends Model
{
    protected $table = 'za_holding_lots';

    protected $fillable = [
        'user_id',
        'holding_id',
        'quantity_acquired',
        'quantity_open',
        'acquisition_cost_minor',
        'acquisition_cost_ccy',
        'acquisition_date',
        'disposed_at',
        'notes',
    ];

    protected $casts = [
        'quantity_acquired' => 'float',
        'quantity_open' => 'float',
        'acquisition_cost_minor' => 'integer',
        'acquisition_date' => 'date',
        'disposed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function holding(): BelongsTo
    {
        return $this->belongsTo(
            self::resolveAppModel('Investment\\Holding'),
            'holding_id',
        );
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
php artisan tinker --execute="echo Schema::hasTable('za_holding_lots') ? 'ok' : 'missing';"
```

Expected: `ok`.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_18_600001_create_za_holding_lots_table.php \
        packs/country-za/src/Models/ZaHoldingLot.php
git commit -m "feat(za-pack): za_holding_lots lot ledger + model (WS 1.3a)"
```

---

## Task 2: Failing tests for ZaBaseCostTracker

**Files:**
- Create: `packs/country-za/tests/Unit/ZaBaseCostTrackerTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->tracker = app(ZaBaseCostTracker::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
    // A parent holding is needed for FK; factory the minimum.
    $this->holdingId = $this->createHolding($this->user->id);
});

function createHolding(int $userId): int
{
    // Minimal parent for Holding FK — InvestmentAccount is the holdable.
    $accountClass = '\\' . 'App' . '\\Models\\Investment\\InvestmentAccount';
    $account = $accountClass::factory()->create(['user_id' => $userId]);

    $holdingClass = '\\' . 'App' . '\\Models\\Investment\\Holding';
    $holding = $holdingClass::factory()->create([
        'holdable_type' => $accountClass,
        'holdable_id' => $account->id,
    ]);

    return (int) $holding->id;
}

it('records a lot and returns it in openLots()', function () {
    $lotId = $this->tracker->recordPurchase(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 100.0,
        costMinor: 5_000_000,
        acquisitionDate: '2026-04-10',
    );

    expect($lotId)->toBeInt()->toBeGreaterThan(0);

    $lots = $this->tracker->openLots($this->holdingId);
    expect($lots)->toHaveCount(1);
    expect($lots[0]['quantity_open'])->toBe(100.0);
    expect($lots[0]['acquisition_cost_minor'])->toBe(5_000_000);
});

it('computes weighted-average cost per unit across multiple lots', function () {
    // Buy 100 @ R50 (cost R5,000 → 500,000 cents).
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10',
    );
    // Buy 200 @ R80 (cost R16,000 → 1,600,000 cents).
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 200.0, 1_600_000, '2026-06-15',
    );

    // Weighted average: (100*50 + 200*80) / 300 = (5000 + 16000) / 300 = R70/unit
    // In minor units: 7,000 cents per unit.
    $avg = $this->tracker->averageCostPerUnitMinor($this->holdingId);

    expect($avg)->toBe(7_000.0);
});

it('applies a partial disposal proportionally across open lots', function () {
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10',
    );
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 200.0, 1_600_000, '2026-06-15',
    );

    // Dispose 150 units. At weighted average R70/unit, the cost basis
    // removed is 150 * 7000 = 1_050_000 cents (R10,500).
    $result = $this->tracker->recordDisposal(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 150.0,
        disposalDate: '2026-09-01',
    );

    expect($result['cost_basis_removed_minor'])->toBe(1_050_000);
    expect($result['units_disposed'])->toBe(150.0);

    // Remaining 150 units with R10,500 cost basis removed from R21,000 total.
    // Open quantity now 150 @ R70 weighted avg → open cost 1_050_000.
    $avg = $this->tracker->averageCostPerUnitMinor($this->holdingId);
    expect($avg)->toBe(7_000.0);
});

it('syncs holdings.cost_basis with the open-lot cost basis after a disposal', function () {
    // Buy 100 @ R50 (cost R5,000) and 200 @ R80 (cost R16,000) → total R21,000.
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10',
    );
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 200.0, 1_600_000, '2026-06-15',
    );

    // Before any disposal, the holding row itself doesn't know about
    // the ledger. The tracker's write-back only fires on recordDisposal.
    // Dispose 150 units → cost basis removed R10,500 → remaining open
    // cost basis = R10,500 (cents: 1_050_000).
    $this->tracker->recordDisposal(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 150.0,
        disposalDate: '2026-09-01',
    );

    $holding = \DB::table('holdings')->where('id', $this->holdingId)->first();
    expect((int) round($holding->cost_basis * 100))->toBe(1_050_000);
});

it('rejects disposal exceeding open quantity', function () {
    $this->tracker->recordPurchase(
        $this->user->id, $this->holdingId, 50.0, 250_000, '2026-04-10',
    );

    expect(fn () => $this->tracker->recordDisposal(
        userId: $this->user->id,
        holdingId: $this->holdingId,
        quantity: 100.0,
        disposalDate: '2026-09-01',
    ))->toThrow(InvalidArgumentException::class);
});

it('isolates lots by holding_id', function () {
    $otherHolding = createHolding($this->user->id);

    $this->tracker->recordPurchase($this->user->id, $this->holdingId, 100.0, 500_000, '2026-04-10');
    $this->tracker->recordPurchase($this->user->id, $otherHolding, 50.0, 250_000, '2026-04-11');

    expect($this->tracker->openLots($this->holdingId))->toHaveCount(1);
    expect($this->tracker->openLots($otherHolding))->toHaveCount(1);
    expect($this->tracker->averageCostPerUnitMinor($this->holdingId))->toBe(5_000.0);
    expect($this->tracker->averageCostPerUnitMinor($otherHolding))->toBe(5_000.0);
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaBaseCostTrackerTest.php
```

Expected: class not found.

---

## Task 3: Implement ZaBaseCostTracker

**Files:**
- Create: `packs/country-za/src/Investment/ZaBaseCostTracker.php`

- [ ] **Step 1: Write the tracker**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Investment;

use Fynla\Packs\Za\Models\ZaHoldingLot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Weighted-average base-cost lot tracker for SA discretionary CGT.
 *
 * Records purchases as separate lots. Disposals draw down weighted-
 * average cost basis proportionally across all open lots for the holding,
 * then write the new open-cost-basis total back to the main-app
 * `holdings.cost_basis` column so the holding row stays in sync with the
 * ledger.
 *
 * Specific-identification is a future enhancement; spec § 8.3 permits
 * either method but weighted-average is the default for unit trusts /
 * ETFs which is the v1 scope.
 */
class ZaBaseCostTracker
{
    public function recordPurchase(
        int $userId,
        int $holdingId,
        float $quantity,
        int $costMinor,
        string $acquisitionDate,
        ?string $notes = null,
    ): int {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Purchase quantity must be positive.');
        }
        if ($costMinor < 0) {
            throw new InvalidArgumentException('Acquisition cost cannot be negative.');
        }

        $lot = ZaHoldingLot::create([
            'user_id' => $userId,
            'holding_id' => $holdingId,
            'quantity_acquired' => $quantity,
            'quantity_open' => $quantity,
            'acquisition_cost_minor' => $costMinor,
            'acquisition_cost_ccy' => 'ZAR',
            'acquisition_date' => $acquisitionDate,
            'notes' => $notes,
        ]);

        return (int) $lot->id;
    }

    /**
     * @return array{units_disposed: float, cost_basis_removed_minor: int}
     */
    public function recordDisposal(
        int $userId,
        int $holdingId,
        float $quantity,
        string $disposalDate,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Disposal quantity must be positive.');
        }

        $openQuantity = $this->openQuantity($holdingId);
        if ($quantity > $openQuantity + 1e-6) {
            throw new InvalidArgumentException(
                "Disposal quantity {$quantity} exceeds open quantity {$openQuantity}.",
            );
        }

        $avgCostPerUnit = $this->averageCostPerUnitMinor($holdingId);
        $costBasisRemoved = (int) round($quantity * $avgCostPerUnit);

        // Draw down proportionally from each open lot.
        $remaining = $quantity;
        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->orderBy('acquisition_date')
            ->orderBy('id')
            ->get();

        $totalOpen = $lots->sum('quantity_open');

        foreach ($lots as $lot) {
            if ($remaining <= 1e-9) {
                break;
            }
            $share = $totalOpen > 0 ? $lot->quantity_open / $totalOpen : 0.0;
            $drawdown = min($remaining, $quantity * $share, $lot->quantity_open);

            $lot->quantity_open = max(0.0, $lot->quantity_open - $drawdown);
            if ($lot->quantity_open <= 1e-9) {
                $lot->disposed_at = $disposalDate;
            }
            $lot->save();

            $remaining -= $drawdown;
        }

        // Floating-point residue: if tiny amount left, draw it off the
        // earliest still-open lot so the sum exactly matches requested qty.
        if ($remaining > 1e-9) {
            $earliest = ZaHoldingLot::query()
                ->where('holding_id', $holdingId)
                ->where('quantity_open', '>', 0)
                ->orderBy('acquisition_date')
                ->orderBy('id')
                ->first();
            if ($earliest !== null) {
                $earliest->quantity_open = max(0.0, $earliest->quantity_open - $remaining);
                if ($earliest->quantity_open <= 1e-9) {
                    $earliest->disposed_at = $disposalDate;
                }
                $earliest->save();
            }
        }

        // Write back the new open-cost basis total to the main-app
        // `holdings.cost_basis` column so the row stays in sync with the
        // ledger. cost_basis on `holdings` is decimal(15,2) (pounds /
        // rand — the UK main-app column predates the minor-unit shadow
        // pattern), so divide cents by 100.
        $openCostMinor = $this->openCostBasisMinor($holdingId);
        DB::table('holdings')
            ->where('id', $holdingId)
            ->update(['cost_basis' => round($openCostMinor / 100, 2)]);

        return [
            'units_disposed' => $quantity,
            'cost_basis_removed_minor' => $costBasisRemoved,
        ];
    }

    /**
     * Sum of (acquisition_cost × quantity_open / quantity_acquired) across
     * all open lots for the holding, in minor units. This is the ledger's
     * authoritative open-cost basis.
     */
    public function openCostBasisMinor(int $holdingId): int
    {
        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->get();

        $total = 0.0;
        foreach ($lots as $lot) {
            if ($lot->quantity_acquired <= 0) {
                continue;
            }
            $proportion = $lot->quantity_open / $lot->quantity_acquired;
            $total += $lot->acquisition_cost_minor * $proportion;
        }

        return (int) round($total);
    }

    /**
     * @return array<int, array{id: int, quantity_open: float, acquisition_cost_minor: int, acquisition_date: string}>
     */
    public function openLots(int $holdingId): array
    {
        return ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->orderBy('acquisition_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($lot) => [
                'id' => (int) $lot->id,
                'quantity_open' => (float) $lot->quantity_open,
                'acquisition_cost_minor' => (int) $lot->acquisition_cost_minor,
                'acquisition_date' => $lot->acquisition_date?->format('Y-m-d') ?? '',
            ])
            ->all();
    }

    public function openQuantity(int $holdingId): float
    {
        return (float) ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->sum('quantity_open');
    }

    /**
     * Weighted average cost per unit in minor currency units (cents).
     * Uses total acquisition_cost × (quantity_open / quantity_acquired)
     * so drawn-down lots contribute proportionally.
     */
    public function averageCostPerUnitMinor(int $holdingId): float
    {
        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->get();

        $totalOpenCost = 0.0;
        $totalOpenUnits = 0.0;

        foreach ($lots as $lot) {
            if ($lot->quantity_acquired <= 0) {
                continue;
            }
            $proportion = $lot->quantity_open / $lot->quantity_acquired;
            $totalOpenCost += $lot->acquisition_cost_minor * $proportion;
            $totalOpenUnits += $lot->quantity_open;
        }

        return $totalOpenUnits > 0 ? $totalOpenCost / $totalOpenUnits : 0.0;
    }
}
```

- [ ] **Step 2: Run test — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaBaseCostTrackerTest.php
```

Expected: 5 passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Investment/ZaBaseCostTracker.php \
        packs/country-za/tests/Unit/ZaBaseCostTrackerTest.php
git commit -m "feat(za-pack): ZaBaseCostTracker with weighted-average lots (WS 1.3a)"
```

---

## Task 4: Failing tests for ZaCgtCalculator

**Files:**
- Create: `packs/country-za/tests/Unit/ZaCgtCalculatorTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaCgtCalculator;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const CGT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->calc = app(ZaCgtCalculator::class);
});

describe('Discretionary CGT (individual)', function () {
    it('applies the R40,000 annual exclusion before 40% inclusion', function () {
        // Gain R50,000. After R40k exclusion → R10,000 taxable amount.
        // 40% inclusion → R4,000 included in income. At R400,000 other
        // income (31% marginal), delta tax ≈ R1,240.
        $r = $this->calc->calculateDiscretionaryCgt(
            gainMinor: 5_000_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: CGT_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(1_000_000);
        expect($r['included_minor'])->toBe(400_000);
        expect($r['tax_due_minor'])->toBeGreaterThan(0);
        expect($r['exclusion_applied_minor'])->toBe(4_000_000);
    });

    it('returns zero tax when gain is fully covered by annual exclusion', function () {
        $r = $this->calc->calculateDiscretionaryCgt(
            gainMinor: 3_500_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: CGT_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(0);
        expect($r['included_minor'])->toBe(0);
        expect($r['tax_due_minor'])->toBe(0);
        expect($r['exclusion_applied_minor'])->toBe(3_500_000);
    });

    it('returns zero tax on a loss', function () {
        $r = $this->calc->calculateDiscretionaryCgt(
            gainMinor: -2_000_000,
            otherTaxableIncomeMinor: 40_000_000,
            age: 40,
            taxYear: CGT_TAX_YEAR,
        );

        expect($r['taxable_amount_minor'])->toBe(0);
        expect($r['tax_due_minor'])->toBe(0);
    });
});

describe('Endowment CGT (s29A wrapper)', function () {
    it('applies 30% flat rate with no annual exclusion', function () {
        // R50,000 gain inside endowment → 30% flat → R15,000 tax.
        // No R40k exclusion (wrapper-internal tax, not individual CGT).
        $r = $this->calc->calculateEndowmentCgt(
            gainMinor: 5_000_000,
            taxYear: CGT_TAX_YEAR,
        );

        expect($r['tax_due_minor'])->toBe(1_500_000);
        expect($r['exclusion_applied_minor'])->toBe(0);
        expect($r['wrapper_rate_bps'])->toBe(3000);
    });

    it('returns zero tax on a wrapper loss', function () {
        $r = $this->calc->calculateEndowmentCgt(
            gainMinor: -1_000_000,
            taxYear: CGT_TAX_YEAR,
        );

        expect($r['tax_due_minor'])->toBe(0);
    });
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaCgtCalculatorTest.php
```

Expected: class not found.

---

## Task 5: Implement ZaCgtCalculator

**Files:**
- Create: `packs/country-za/src/Investment/ZaCgtCalculator.php`

- [ ] **Step 1: Write the calculator**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Investment;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;

/**
 * SA Capital Gains Tax calculator.
 *
 * Two paths:
 *   - Discretionary: 40% inclusion × marginal rate, after the R40,000
 *     annual exclusion (cgt.annual_exclusion_minor).
 *   - Endowment wrapper (s29A): 30% flat rate, no exclusion (the rate is
 *     applied inside the wrapper before the gain reaches the individual).
 *
 * Pure calculator. Reads rates from ZaTaxConfigService, composes
 * ZaTaxEngine for marginal rate on the discretionary path.
 */
class ZaCgtCalculator
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     taxable_amount_minor: int,
     *     exclusion_applied_minor: int,
     *     included_minor: int,
     *     tax_due_minor: int,
     *     marginal_rate: float
     * }
     */
    public function calculateDiscretionaryCgt(
        int $gainMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($gainMinor <= 0) {
            return [
                'taxable_amount_minor' => 0,
                'exclusion_applied_minor' => max(0, $gainMinor),
                'included_minor' => 0,
                'tax_due_minor' => 0,
                'marginal_rate' => 0.0,
            ];
        }

        $exclusion = (int) $this->config->get($taxYear, 'cgt.annual_exclusion_minor', 0);
        $inclusionBps = (int) $this->config->get($taxYear, 'cgt.individual_inclusion_bps', 0);

        $exclusionApplied = min($gainMinor, $exclusion);
        $taxableAmount = $gainMinor - $exclusionApplied;
        $included = (int) round($taxableAmount * $inclusionBps / 10_000);

        if ($included === 0) {
            return [
                'taxable_amount_minor' => $taxableAmount,
                'exclusion_applied_minor' => $exclusionApplied,
                'included_minor' => 0,
                'tax_due_minor' => 0,
                'marginal_rate' => 0.0,
            ];
        }

        $baseline = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor,
            $taxYear,
            $age,
        );
        $withInclusion = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor + $included,
            $taxYear,
            $age,
        );

        return [
            'taxable_amount_minor' => $taxableAmount,
            'exclusion_applied_minor' => $exclusionApplied,
            'included_minor' => $included,
            'tax_due_minor' => max(0, $withInclusion['tax_due'] - $baseline['tax_due']),
            'marginal_rate' => (float) $withInclusion['marginal_rate'],
        ];
    }

    /**
     * @return array{
     *     tax_due_minor: int,
     *     exclusion_applied_minor: int,
     *     wrapper_rate_bps: int
     * }
     */
    public function calculateEndowmentCgt(int $gainMinor, string $taxYear): array
    {
        $rateBps = (int) $this->config->get($taxYear, 'cgt.endowment_wrapper_rate_bps', 0);

        if ($gainMinor <= 0) {
            return [
                'tax_due_minor' => 0,
                'exclusion_applied_minor' => 0,
                'wrapper_rate_bps' => $rateBps,
            ];
        }

        return [
            'tax_due_minor' => (int) round($gainMinor * $rateBps / 10_000),
            'exclusion_applied_minor' => 0,
            'wrapper_rate_bps' => $rateBps,
        ];
    }
}
```

- [ ] **Step 2: Run test — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaCgtCalculatorTest.php
```

Expected: 5 passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Investment/ZaCgtCalculator.php \
        packs/country-za/tests/Unit/ZaCgtCalculatorTest.php
git commit -m "feat(za-pack): ZaCgtCalculator — discretionary + endowment paths (WS 1.3a)"
```

---

## Task 6: Failing tests for ZaInvestmentEngine

**Files:**
- Create: `packs/country-za/tests/Unit/ZaInvestmentEngineTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaInvestmentEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_INV_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->engine = app(ZaInvestmentEngine::class);
});

it('implements the InvestmentEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(InvestmentEngine::class);
});

describe('Tax wrappers', function () {
    it('lists the three SA wrappers', function () {
        $codes = array_column($this->engine->getTaxWrappers(), 'code');

        expect($codes)->toContain('tfsa', 'discretionary', 'endowment');
    });
});

describe('Annual allowances', function () {
    it('exposes TFSA annual cap from the Savings engine (R46,000)', function () {
        $allowances = $this->engine->getAnnualAllowances(ZA_INV_TAX_YEAR);

        expect($allowances['tfsa'])->toBe(4_600_000);
    });

    it('reports discretionary as unbounded (PHP_INT_MAX)', function () {
        $allowances = $this->engine->getAnnualAllowances(ZA_INV_TAX_YEAR);

        expect($allowances['discretionary'])->toBe(PHP_INT_MAX);
    });

    it('reports endowment as unbounded (SA has no wrapper-side annual cap)', function () {
        $allowances = $this->engine->getAnnualAllowances(ZA_INV_TAX_YEAR);

        expect($allowances['endowment'])->toBe(PHP_INT_MAX);
    });
});

describe('calculateInvestmentTax', function () {
    it('routes discretionary gains through ZaCgtCalculator', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'discretionary',
            'gains' => 5_000_000,
            'dividends' => 0,
            'interest' => 0,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        // R50,000 gain - R40,000 exclusion = R10,000 × 40% = R4,000 included.
        expect($r['gains_tax'])->toBeGreaterThan(0);
        expect($r['dividend_tax'])->toBe(0);
        expect($r['interest_tax'])->toBe(0);
        expect($r['total_tax'])->toBe($r['gains_tax']);
        expect($r['breakdown']['wrapper_code'])->toBe('discretionary');
    });

    it('routes endowment gains through the 30% flat wrapper path', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'endowment',
            'gains' => 5_000_000,
            'dividends' => 0,
            'interest' => 0,
            'tax_year' => ZA_INV_TAX_YEAR,
        ]);

        // R50,000 × 30% = R15,000 wrapper-internal tax.
        expect($r['gains_tax'])->toBe(1_500_000);
        expect($r['total_tax'])->toBe(1_500_000);
    });

    it('applies 20% local DWT to local dividends', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'discretionary',
            'gains' => 0,
            'dividends' => 1_000_000,  // R10,000 gross
            'interest' => 0,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['dividend_tax'])->toBe(200_000);  // 20% of R10,000
    });

    it('zeros dividend tax inside TFSA wrapper', function () {
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'tfsa',
            'gains' => 5_000_000,
            'dividends' => 1_000_000,
            'interest' => 100_000,
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['total_tax'])->toBe(0);
        expect($r['gains_tax'])->toBe(0);
        expect($r['dividend_tax'])->toBe(0);
        expect($r['interest_tax'])->toBe(0);
    });

    it('composes interest tax through the Savings engine (exempt slice)', function () {
        // Small interest slice fully covered by R23,800 exemption.
        $r = $this->engine->calculateInvestmentTax([
            'wrapper_code' => 'discretionary',
            'gains' => 0,
            'dividends' => 0,
            'interest' => 1_500_000,  // R15,000 — under exemption
            'tax_year' => ZA_INV_TAX_YEAR,
            'income_minor' => 40_000_000,
            'age' => 40,
        ]);

        expect($r['interest_tax'])->toBe(0);
    });
});

describe('Asset allocation rules', function () {
    it('returns empty rules (Reg 28 is retirement-fund scope, not discretionary)', function () {
        // Discretionary / endowment portfolios have no regulatory allocation
        // limits. Reg 28 applies to RA / PF / PvF / Preservation and lands
        // in WS 1.4 Retirement.
        expect($this->engine->getAssetAllocationRules())->toBe([]);
    });
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaInvestmentEngineTest.php
```

Expected: class not found.

---

## Task 7: Implement ZaInvestmentEngine

**Files:**
- Create: `packs/country-za/src/Investment/ZaInvestmentEngine.php`

- [ ] **Step 1: Write the engine**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Investment;

use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use InvalidArgumentException;

/**
 * ZA InvestmentEngine. Routes tax composition by wrapper:
 *
 *   tfsa          → all tax suppressed (wrapper is tax-free)
 *   endowment     → 30% flat rate inside wrapper (no exclusion)
 *   discretionary → 40% inclusion × marginal rate + R40k exclusion +
 *                   20% DWT + interest exemption path via ZaSavingsEngine
 *
 * Pure calculator. Delegates to ZaCgtCalculator and ZaSavingsEngine.
 */
class ZaInvestmentEngine implements InvestmentEngine
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaCgtCalculator $cgt,
        private readonly ZaSavingsEngine $savings,
    ) {
    }

    public function getTaxWrappers(): array
    {
        return [
            [
                'code' => 'tfsa',
                'name' => 'Tax-Free Savings Account',
                'description' => 'Tax-free wrapper with annual and lifetime caps',
                'tax_treatment' => 'No income tax, CGT, or dividend withholding',
            ],
            [
                'code' => 'discretionary',
                'name' => 'Discretionary portfolio',
                'description' => 'Unwrapped unit trusts, ETFs, direct equities',
                'tax_treatment' => 'Interest at marginal rate (after exemption); 40% CGT inclusion; 20% local DWT',
            ],
            [
                'code' => 'endowment',
                'name' => 'Endowment (section 29A)',
                'description' => '5-year restriction wrapper for higher-rate taxpayers',
                'tax_treatment' => '30% flat rate inside wrapper for income and CGT',
            ],
        ];
    }

    public function getAnnualAllowances(string $taxYear): array
    {
        return [
            'tfsa' => $this->savings->getAnnualContributionCap($taxYear),
            'discretionary' => PHP_INT_MAX,
            'endowment' => PHP_INT_MAX,
        ];
    }

    public function calculateInvestmentTax(array $params): array
    {
        $wrapper = $params['wrapper_code'] ?? '';
        $gains = (int) ($params['gains'] ?? 0);
        $dividends = (int) ($params['dividends'] ?? 0);
        $interest = (int) ($params['interest'] ?? 0);
        $taxYear = (string) ($params['tax_year'] ?? '');
        $income = (int) ($params['income_minor'] ?? 0);
        $age = (int) ($params['age'] ?? 40);

        if ($taxYear === '') {
            throw new InvalidArgumentException('tax_year is required.');
        }

        return match ($wrapper) {
            'tfsa' => $this->zeroTaxBreakdown($wrapper),
            'endowment' => $this->endowmentBreakdown($gains, $taxYear),
            'discretionary' => $this->discretionaryBreakdown(
                $gains, $dividends, $interest, $income, $age, $taxYear,
            ),
            default => throw new InvalidArgumentException(
                "Unknown wrapper_code: '{$wrapper}'. Must be one of: tfsa, discretionary, endowment.",
            ),
        };
    }

    public function getAssetAllocationRules(): array
    {
        // Reg 28 applies to retirement funds (RA, PF, PvF, Preservation)
        // and is owned by WS 1.4. Discretionary / endowment wrappers have
        // no regulatory allocation limits.
        return [];
    }

    /**
     * @return array{total_tax: int, gains_tax: int, dividend_tax: int, interest_tax: int, breakdown: array<string, mixed>}
     */
    private function zeroTaxBreakdown(string $wrapper): array
    {
        return [
            'total_tax' => 0,
            'gains_tax' => 0,
            'dividend_tax' => 0,
            'interest_tax' => 0,
            'breakdown' => [
                'wrapper_code' => $wrapper,
                'note' => 'Tax-free wrapper — no income, CGT, or DWT liability.',
            ],
        ];
    }

    /**
     * @return array{total_tax: int, gains_tax: int, dividend_tax: int, interest_tax: int, breakdown: array<string, mixed>}
     */
    private function endowmentBreakdown(int $gains, string $taxYear): array
    {
        $r = $this->cgt->calculateEndowmentCgt($gains, $taxYear);

        return [
            'total_tax' => $r['tax_due_minor'],
            'gains_tax' => $r['tax_due_minor'],
            'dividend_tax' => 0,
            'interest_tax' => 0,
            'breakdown' => [
                'wrapper_code' => 'endowment',
                'wrapper_rate_bps' => $r['wrapper_rate_bps'],
            ],
        ];
    }

    /**
     * @return array{total_tax: int, gains_tax: int, dividend_tax: int, interest_tax: int, breakdown: array<string, mixed>}
     */
    private function discretionaryBreakdown(
        int $gains,
        int $dividends,
        int $interest,
        int $income,
        int $age,
        string $taxYear,
    ): array {
        $cgtResult = $this->cgt->calculateDiscretionaryCgt($gains, $income, $age, $taxYear);
        $gainsTax = $cgtResult['tax_due_minor'];

        $dwtBps = (int) $this->config->get($taxYear, 'dwt.local_rate_bps', 0);
        $dividendTax = $dividends > 0 ? (int) round($dividends * $dwtBps / 10_000) : 0;

        $interestResult = $this->savings->calculateInterestTax($interest, $income, $age, $taxYear);
        $interestTax = $interestResult['tax_due_minor'];

        return [
            'total_tax' => $gainsTax + $dividendTax + $interestTax,
            'gains_tax' => $gainsTax,
            'dividend_tax' => $dividendTax,
            'interest_tax' => $interestTax,
            'breakdown' => [
                'wrapper_code' => 'discretionary',
                'cgt' => $cgtResult,
                'dwt_rate_bps' => $dwtBps,
                'interest' => $interestResult,
            ],
        ];
    }
}
```

- [ ] **Step 2: Run test — expect green**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaInvestmentEngineTest.php
```

Expected: 10 passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Investment/ZaInvestmentEngine.php \
        packs/country-za/tests/Unit/ZaInvestmentEngineTest.php
git commit -m "feat(za-pack): ZaInvestmentEngine implements InvestmentEngine (WS 1.3a)"
```

---

## Task 8: Wire ZA investment bindings through the provider

**Files:**
- Modify: `packs/country-za/src/Providers/ZaPackServiceProvider.php`
- Modify: `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php`

- [ ] **Step 1: Expand `register()`**

Add inside the existing `register()`:

```php
// WS 1.3a — Investment
$this->app->bind('pack.za.investment', \Fynla\Packs\Za\Investment\ZaInvestmentEngine::class);
$this->app->bind(
    'pack.za.investment.cgt',
    \Fynla\Packs\Za\Investment\ZaCgtCalculator::class,
);
$this->app->bind(
    'pack.za.investment.lot_tracker',
    \Fynla\Packs\Za\Investment\ZaBaseCostTracker::class,
);
```

- [ ] **Step 2: Extend provider tests**

In `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php`, add:

```php
it('registers investment container bindings (WS 1.3a)', function () {
    expect(app('pack.za.investment'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Investment\ZaInvestmentEngine::class)
        ->toBeInstanceOf(\Fynla\Core\Contracts\InvestmentEngine::class);
    expect(app('pack.za.investment.cgt'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Investment\ZaCgtCalculator::class);
    expect(app('pack.za.investment.lot_tracker'))
        ->toBeInstanceOf(\Fynla\Packs\Za\Investment\ZaBaseCostTracker::class);
});
```

- [ ] **Step 3: Verify bindings**

```bash
./vendor/bin/pest packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
```

Expected: green, new binding test included.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/src/Providers/ZaPackServiceProvider.php \
        packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
git commit -m "feat(za-pack): register investment engine + CGT + lot tracker (WS 1.3a)"
```

---

## Task 9: End-to-end integration test

**Files:**
- Create: `tests/Integration/Za/ZaInvestmentIntegrationTest.php`

- [ ] **Step 1: Write the integration test**

```php
<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Fynla\Packs\Za\Investment\ZaInvestmentEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ZA_INV_INT_TAX_YEAR = '2026/27';

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: records two buys, computes weighted-average cost, sells and composes discretionary CGT', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);
    $holding = Holding::factory()->create([
        'holdable_type' => InvestmentAccount::class,
        'holdable_id' => $account->id,
    ]);

    $tracker = app(ZaBaseCostTracker::class);
    $engine = app(ZaInvestmentEngine::class);

    // Buy 100 @ R500 → cost R50,000 (5_000_000 cents).
    $tracker->recordPurchase($user->id, $holding->id, 100.0, 5_000_000, '2026-04-10');
    // Buy 200 @ R600 → cost R120,000 (12_000_000 cents).
    $tracker->recordPurchase($user->id, $holding->id, 200.0, 12_000_000, '2026-07-15');

    // Weighted avg = (5_000_000 + 12_000_000) / 300 = 56_666.67 cents/unit.
    expect(round($tracker->averageCostPerUnitMinor($holding->id), 2))
        ->toBe(56_666.67);

    // Sell 150 units at R800 = R120,000 proceeds (12_000_000 cents).
    // Cost basis removed = 150 × 56_666.67 = 8_500_000 cents.
    // Realised gain = 12_000_000 - 8_500_000 = 3_500_000 cents (R35,000).
    $disposal = $tracker->recordDisposal($user->id, $holding->id, 150.0, '2026-09-01');
    $costBasisRemoved = $disposal['cost_basis_removed_minor'];
    $proceeds = 12_000_000;
    $gain = $proceeds - $costBasisRemoved;

    // R35,000 gain fully covered by R40,000 annual exclusion → zero CGT.
    $r = $engine->calculateInvestmentTax([
        'wrapper_code' => 'discretionary',
        'gains' => $gain,
        'dividends' => 0,
        'interest' => 0,
        'tax_year' => ZA_INV_INT_TAX_YEAR,
        'income_minor' => 40_000_000,
        'age' => 40,
    ]);

    expect($r['gains_tax'])->toBe(0);
    expect($r['breakdown']['wrapper_code'])->toBe('discretionary');
});

it('end-to-end: large endowment gain applies 30% flat with no exclusion', function () {
    $engine = app(ZaInvestmentEngine::class);

    // R100,000 endowment gain → 30% × R100,000 = R30,000.
    $r = $engine->calculateInvestmentTax([
        'wrapper_code' => 'endowment',
        'gains' => 10_000_000,
        'dividends' => 0,
        'interest' => 0,
        'tax_year' => ZA_INV_INT_TAX_YEAR,
    ]);

    expect($r['total_tax'])->toBe(3_000_000);
    expect($r['gains_tax'])->toBe(3_000_000);
});
```

- [ ] **Step 2: Run the integration test**

```bash
./vendor/bin/pest tests/Integration/Za/ZaInvestmentIntegrationTest.php
```

Expected: 2 passing.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/Za/ZaInvestmentIntegrationTest.php
git commit -m "test(za-pack): WS 1.3a end-to-end investment CGT integration tests"
```

---

## Task 10: Full regression + baseline

- [ ] **Step 1: Pack suite**

```bash
./vendor/bin/pest packs/country-za/
```

Expected: pack tests green (WS 1.1 + 1.2a + 1.3a).

- [ ] **Step 2: Architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```

Expected: green. Both `UkInvestmentEngine` and `ZaInvestmentEngine` implement `InvestmentEngine` per the new arch assertions (no more skip for ZA).

- [ ] **Step 3: Full suite**

```bash
./vendor/bin/pest
```

Expected: 2,514 + new WS 1.3a tests passing. 0 new failures (4 pre-existing `ProtectionWorkflowTest` failures remain).

- [ ] **Step 4: Record the new baseline**

Create `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-3a-complete.md` with:
- Tests before: 2,514
- Tests after: [actual]
- Net gain: [delta]
- Contract asserted: `InvestmentEngine` now implemented by both `UkInvestmentEngine` and `ZaInvestmentEngine`
- Services shipped: `UkInvestmentEngine`, `ZaInvestmentEngine`, `ZaCgtCalculator`, `ZaBaseCostTracker`
- Tables added: `za_holding_lots`
- Deferred items: Reg 28 Monitor → WS 1.4. Specific-identification CGT → future. Full FX translation for offshore → Phase 2.

- [ ] **Step 5: Final commit**

```bash
git add /Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-3a-complete.md
git commit -m "docs(vault): WS 1.3a completion note"
```

---

## Self-Review

**Spec coverage (SA Research § 8 + Implementation_Plan_v2 WS 1.3a):**
| Requirement | Task |
|-------------|------|
| `ZaInvestmentEngine`: discretionary, endowment, offshore | Tasks 6–7 |
| CGT tracking: base cost per lot, 40% inclusion, R40k exclusion | Tasks 2–5 (lot tracker + CGT calc) |
| Endowment (s29A) 30% flat path | Tasks 4–5 (CGT calc) |
| DWT 20% local | Task 6 (engine breakdown) |
| Offshore FX translation | **Deferred to Phase 2 cross-border** (documented in plan + PRD) |
| Reg 28 Monitor | **Deferred to WS 1.4 Retirement** per SA Research doc line 432 |
| Vue components | **Deferred to WS 1.3c** |

**Placeholder scan:** All tasks have complete code, exact file paths, concrete commands. No TODOs.

**Type consistency:**
- `ZaCgtCalculator::calculateDiscretionaryCgt` return shape (`taxable_amount_minor`, `exclusion_applied_minor`, `included_minor`, `tax_due_minor`, `marginal_rate`) consistent in Tasks 4, 5, 7.
- `ZaCgtCalculator::calculateEndowmentCgt` return shape (`tax_due_minor`, `exclusion_applied_minor`, `wrapper_rate_bps`) consistent in Tasks 4, 5, 7.
- `ZaInvestmentEngine::calculateInvestmentTax` return matches the `InvestmentEngine` contract shape (Tasks 6, 7, 9) and provider binding asserts the contract in Task 8.
- `ZaBaseCostTracker::recordDisposal` return shape (`units_disposed`, `cost_basis_removed_minor`) consistent in Tasks 2, 3, 9.
- Container keys `pack.gb.investment`, `pack.za.investment`, `pack.za.investment.cgt`, `pack.za.investment.lot_tracker` identical across provider code, provider tests, and arch assertions.

**Known risks:**
- `InvestmentAccount::factory()` and `Holding::factory()` are assumed to exist without special state. If the default states require fields not settable in the test, the integration test (Task 9) will fail loudly and need factory state additions.
- Weighted-average disposal has float drift near tiny residues; the `recordDisposal` implementation catches this with an explicit residue drawdown. Integration test's expected values are rounded to 2 dp.
- `TaxConfigService::getISAAllowances` is the UK-side hot path Task 0 depends on — WS 1.2a already validated it; no surprise expected.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-18-ws-1-3a-za-investment.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks.
2. **Inline Execution** — execute tasks in this session with checkpoints.
