# WS 1.4c — SA Reg 28 Monitor Backend Implementation Plan

**Goal:** Ship the SA Regulation 28 Monitor — look-through asset allocation compliance against the 7 asset-class limits + single-entity limit (spec § 9.6), per-fund and whole-portfolio flags, snapshot history.

**Scope:**
- Seeder rows for Reg 28 limits (7 asset-class + 1 single-entity).
- `za_reg28_snapshots` table — historical allocation snapshots per user.
- `ZaReg28Snapshot` Eloquent model.
- `ZaReg28Monitor` service — compliance check + snapshot persistence.
- Container binding `pack.za.reg28.monitor`.
- SASSA Old Age Grant data capture deferred to WS 1.4d (onboarding form).

**Out of scope:** SASSA means-test service, frontend, observer to auto-snapshot on holding changes.

---

## Task 1: Seeder rows

Add `reg28Rows()` to `ZaTaxConfigurationSeeder.php`:

```php
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
```

Wire into `rows()` array_merge.

## Task 2: Migration + model

`packs/country-za/database/migrations/2026_04_18_900001_create_za_reg28_snapshots_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical Reg 28 compliance snapshots.
 *
 * One row per compliance check. Stores the asset-allocation breakdown
 * (as JSON) plus boolean flags for each limit category. The `compliant`
 * overall flag is true iff ALL per-class flags are true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_reg28_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('fund_holding_id')->nullable()
                ->constrained('dc_pensions')->cascadeOnDelete();
            $t->date('as_at_date');
            $t->json('allocation');  // {offshore: 30.0, equity: 60.0, ...}
            $t->boolean('offshore_compliant')->default(true);
            $t->boolean('equity_compliant')->default(true);
            $t->boolean('property_compliant')->default(true);
            $t->boolean('private_equity_compliant')->default(true);
            $t->boolean('commodities_compliant')->default(true);
            $t->boolean('hedge_funds_compliant')->default(true);
            $t->boolean('other_compliant')->default(true);
            $t->boolean('single_entity_compliant')->default(true);
            $t->boolean('compliant')->default(true);
            $t->json('breaches')->nullable();  // list of breached class names
            $t->timestamps();

            $t->index(['user_id', 'as_at_date'], 'za_reg28_user_date_idx');
            $t->index('fund_holding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_reg28_snapshots');
    }
};
```

`packs/country-za/src/Models/ZaReg28Snapshot.php`:

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZaReg28Snapshot extends Model
{
    protected $table = 'za_reg28_snapshots';

    protected $fillable = [
        'user_id', 'fund_holding_id', 'as_at_date', 'allocation',
        'offshore_compliant', 'equity_compliant', 'property_compliant',
        'private_equity_compliant', 'commodities_compliant',
        'hedge_funds_compliant', 'other_compliant', 'single_entity_compliant',
        'compliant', 'breaches',
    ];

    protected $casts = [
        'as_at_date' => 'date',
        'allocation' => 'array',
        'breaches' => 'array',
        'offshore_compliant' => 'boolean',
        'equity_compliant' => 'boolean',
        'property_compliant' => 'boolean',
        'private_equity_compliant' => 'boolean',
        'commodities_compliant' => 'boolean',
        'hedge_funds_compliant' => 'boolean',
        'other_compliant' => 'boolean',
        'single_entity_compliant' => 'boolean',
        'compliant' => 'boolean',
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

## Task 3: ZaReg28Monitor service + tests

Implement `ZaReg28Monitor` that:
- `check(array $allocation, string $taxYear): array` — returns compliance result without persisting
- `snapshot(int $userId, ?int $fundHoldingId, array $allocation, string $asAtDate, string $taxYear): ZaReg28Snapshot` — runs check + persists

Allocation shape: `['offshore' => 30.0, 'equity' => 60.0, 'property' => 5.0, 'private_equity' => 2.0, 'commodities' => 1.0, 'hedge_funds' => 1.0, 'other' => 1.0, 'single_entity' => 15.0]` — values as percentages (0–100).

## Task 4: Provider binding + test

`pack.za.reg28.monitor` → `ZaReg28Monitor::class`.

## Task 5: Regression gate.
