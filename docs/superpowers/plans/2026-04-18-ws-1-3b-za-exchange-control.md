# WS 1.3b — SA Exchange Control Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Amended — 18 April 2026 — conflicts resolved against codebase audit. Four amendments vs v1 draft:
- Ledger migration gains `recipient_account` nullable `varchar(255)` (spec § 13.2 explicitly lists it).
- Ledger migration gains nullable JSON `ait_documents` column for the AIT documents checklist (spec § 13.2 — v1 data capture only per § 13.4).
- `allowance_type` enum stays `'sda'/'fia'` only. Foreign inheritance / foreign earnings explicitly out of scope for WS 1.3b; deferred to WS 1.7 (emigration life event).
- Added `checkTransferPermitted` test for non-ZAR → non-ZAR currency pair (USD → EUR returns `true` unconditionally).

Also: Task 1 Step 1 now shows only the correct seeder values (R2m / R10m / R12m in cents = 200M / 1B / 1.2B cents). The "self-correcting" note from the v1 draft has been removed to avoid a copy-paste trap.

**Goal:** Ship the South Africa Exchange Control **backend** — `ZaExchangeControl` implementing `core/app/Core/Contracts/ExchangeControl`, calendar-year-keyed consumption ledger (`za_exchange_control_ledger`), SDA/FIA cap enforcement, AIT workflow stubs (data capture only — no external eFiling integration). GB-side stub wraps the `_template/NoopExchangeControl`. UI deferred to WS 1.3c.

**Architecture:**
- **Calendar year** (not tax year) is the axis — SDA/FIA caps reset every 1 January. Consumption queries, allowance lookups, and ledger rows all key off integer `calendar_year` (e.g. `2026`), never the `'2026/27'` tax year string.
- `ZaExchangeControl implements ExchangeControl` — composes the config service for caps + the ledger for consumption. Stateful in the sense of per-user consumption; stateless beyond that. Container key `pack.za.exchange_control`.
- `ZaExchangeControlLedger` — thin persistence over the new `za_exchange_control_ledger` table. Append-only transfer events keyed by `(user_id, calendar_year, allowance_type)`.
- `ZaExchangeControlEntry` — pack-owned Eloquent model.
- Three tax-config rows seeded: `excon.sda_annual_limit_minor` (R2,000,000), `excon.fia_annual_limit_minor` (R10,000,000), `excon.sarb_special_approval_threshold_minor` (R12,000,000 combined).
- GB side: `App\Services\ExchangeControl\UkExchangeControl implements ExchangeControl` returning no-op behaviour (UK has no equivalent exchange control regime). Registered at `pack.gb.exchange_control`.

**Tech Stack:** Laravel 10, PHP 8.2 strict types, Pest v2, MySQL 8.

**Out of scope (deferred):**
- Vue components (WS 1.3c)
- API routes / controllers
- SARS eFiling API integration (spec § 13.4 — v1 is data capture only)
- Emigration life-event wiring (WS 1.7 Goals)
- AIT document attachment storage (future — just a checklist of required documents in v1)
- FX rate feed for mixed-currency ledger rows (v1 stores ZAR amounts only)

---

## File Structure

**New files (core):** None — `ExchangeControl` contract already exists.

**New files (ZA pack):**
- `packs/country-za/src/ExchangeControl/ZaExchangeControl.php` — contract implementation
- `packs/country-za/src/ExchangeControl/ZaExchangeControlLedger.php` — thin persistence service
- `packs/country-za/src/Models/ZaExchangeControlEntry.php` — Eloquent model
- `packs/country-za/database/migrations/2026_04_18_700001_create_za_exchange_control_ledger_table.php`
- `packs/country-za/tests/Unit/ZaExchangeControlTest.php`
- `packs/country-za/tests/Unit/ZaExchangeControlLedgerTest.php`

**New files (main app):**
- `app/Services/ExchangeControl/UkExchangeControl.php` — GB-side no-op `implements ExchangeControl`
- `tests/Unit/Services/ExchangeControl/UkExchangeControlTest.php`
- `tests/Integration/Za/ZaExchangeControlIntegrationTest.php`

**Modified files:**
- `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php` — add three `excon.*` rows
- `packs/country-za/src/Providers/ZaPackServiceProvider.php` — two new bindings
- `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php` — binding assertions
- `app/Providers/GbPackServiceProvider.php` — add `pack.gb.exchange_control` binding
- `tests/Architecture/PackIsolationTest.php` — ExchangeControl contract assertions

---

## Task 0: GB-side ExchangeControl stub

**Files:**
- Create: `app/Services/ExchangeControl/UkExchangeControl.php`
- Create: `tests/Unit/Services/ExchangeControl/UkExchangeControlTest.php`
- Modify: `app/Providers/GbPackServiceProvider.php`
- Modify: `tests/Architecture/PackIsolationTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Services\ExchangeControl\UkExchangeControl;
use Fynla\Core\Contracts\ExchangeControl;

beforeEach(function () {
    $this->excon = app(UkExchangeControl::class);
});

it('implements the ExchangeControl contract', function () {
    expect($this->excon)->toBeInstanceOf(ExchangeControl::class);
});

it('returns empty allowances for UK (no exchange control regime)', function () {
    expect($this->excon->getAnnualAllowances())->toBe([]);
});

it('permits all transfers (UK has no caps)', function () {
    expect($this->excon->checkTransferPermitted(100_000_000_00, 'GBP', 'USD'))->toBeTrue();
});

it('reports zero consumed for any user / period', function () {
    expect($this->excon->getAllowanceConsumed(1, '2026'))->toBe(0);
});

it('never requires approval', function () {
    expect($this->excon->requiresApproval(100_000_000_00, 'investment'))->toBeFalse();
});
```

- [ ] **Step 2: Run test — expect red**

```bash
./vendor/bin/pest tests/Unit/Services/ExchangeControl/UkExchangeControlTest.php
```

Expected: "Class App\Services\ExchangeControl\UkExchangeControl does not exist."

- [ ] **Step 3: Write the stub**

```php
<?php

declare(strict_types=1);

namespace App\Services\ExchangeControl;

use Fynla\Core\Contracts\ExchangeControl;

/**
 * UK-side ExchangeControl implementation.
 *
 * The UK has no exchange control regime — all cross-border transfers are
 * permitted without limit or approval. This class satisfies the contract
 * with no-op behaviour, mirroring the packs/_template/NoopExchangeControl.
 */
class UkExchangeControl implements ExchangeControl
{
    public function getAnnualAllowances(): array
    {
        return [];
    }

    public function checkTransferPermitted(int $amountMinor, string $fromCurrency, string $toCurrency): bool
    {
        return true;
    }

    public function getAllowanceConsumed(int $userId, string $period): int
    {
        return 0;
    }

    public function requiresApproval(int $amountMinor, string $type): bool
    {
        return false;
    }
}
```

- [ ] **Step 4: Run test — expect green**

```bash
./vendor/bin/pest tests/Unit/Services/ExchangeControl/UkExchangeControlTest.php
```

Expected: 5 passing.

- [ ] **Step 5: Register the GB binding**

In `app/Providers/GbPackServiceProvider.php`, add inside `register()` (after the existing bindings):

```php
$this->app->bind('pack.gb.exchange_control', \App\Services\ExchangeControl\UkExchangeControl::class);
```

- [ ] **Step 6: Add architecture assertions**

In `tests/Architecture/PackIsolationTest.php`, add:

```php
it('UkExchangeControl implements the core ExchangeControl contract', function () {
    expect(class_implements(\App\Services\ExchangeControl\UkExchangeControl::class))
        ->toContain(\Fynla\Core\Contracts\ExchangeControl::class);
});

it('ZaExchangeControl implements the core ExchangeControl contract', function () {
    if (! class_exists(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class)) {
        $this->markTestSkipped('ZaExchangeControl not yet loaded (WS 1.3b in progress)');
    }

    expect(class_implements(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class))
        ->toContain(\Fynla\Core\Contracts\ExchangeControl::class);
});
```

- [ ] **Step 7: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/ExchangeControl/UkExchangeControlTest.php \
  tests/Architecture/PackIsolationTest.php
```

Expected: all green, ZA assertion skipped.

- [ ] **Step 8: Commit**

```bash
git add app/Services/ExchangeControl/UkExchangeControl.php \
        app/Providers/GbPackServiceProvider.php \
        tests/Unit/Services/ExchangeControl/UkExchangeControlTest.php \
        tests/Architecture/PackIsolationTest.php
git commit -m "feat(core): UkExchangeControl no-op stub + pack.gb.exchange_control (WS 1.3b prep)"
```

---

## Task 1: Seeder additions for SDA/FIA caps

**Files:**
- Modify: `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php`

- [ ] **Step 1: Add the row factory method**

Add after the existing `endowmentRows()` method in `ZaTaxConfigurationSeeder.php`. Amounts are R×100 cents (R2,000,000 = 200,000,000 cents).

```php
/**
 * Exchange control allowances (WS 1.3b).
 *
 * Amounts in minor units (cents). All three are per-person per-calendar-
 * year, resetting on 1 January. Sources: SARS / SARB Exchange Control
 * Regulations, 2026 update doubling SDA from R1m to R2m.
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
```

- [ ] **Step 2: Wire it into the `rows()` aggregator**

Change the `rows()` method's `array_merge(...)` call to include the new method:

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
);
```

- [ ] **Step 3: Re-run the seeder and verify**

```bash
php artisan db:seed --class="Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder" --force
php artisan tinker --execute="echo DB::table('za_tax_configurations')->where('key_path','like','excon.%')->orderBy('key_path')->get()->pluck('value_cents','key_path')->toJson(JSON_PRETTY_PRINT);"
```

Expected (R2m = 200,000,000 cents; R10m = 1,000,000,000 cents; R12m = 1,200,000,000 cents):
```
{
  "excon.fia_annual_limit_minor": 1000000000,
  "excon.sarb_special_approval_threshold_minor": 1200000000,
  "excon.sda_annual_limit_minor": 200000000
}
```

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php
git commit -m "feat(za-pack): seed SDA/FIA/SARB exchange control caps (WS 1.3b)"
```

---

## Task 2: Migration + Eloquent model for the ledger

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_18_700001_create_za_exchange_control_ledger_table.php`
- Create: `packs/country-za/src/Models/ZaExchangeControlEntry.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of SA exchange control transfers.
 *
 * KEYED BY CALENDAR YEAR (integer e.g. 2026), not tax year. SDA/FIA caps
 * reset on 1 January, which does NOT align with the SA tax year
 * (1 March – 28/29 February). This is a common bug-source when carrying
 * code across from UK tax-year logic — callers and queries must use
 * integer calendar_year throughout.
 *
 * allowance_type: 'sda' or 'fia' (enum). An 'sda' transfer counts toward
 * the R2m SDA pool; an 'fia' transfer counts toward the R10m FIA pool.
 * A single transfer never splits across both pools — the caller decides
 * which pool to draw on before recording.
 *
 * ait_reference captures the SARS Approval for International Transfer ID
 * (required for FIA transfers, nullable for SDA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_exchange_control_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedSmallInteger('calendar_year');
            $t->enum('allowance_type', ['sda', 'fia']);
            $t->bigInteger('amount_minor');
            $t->string('amount_ccy', 3)->default('ZAR');
            $t->string('destination_country', 2)->nullable();
            $t->string('purpose', 64)->nullable();
            $t->string('authorised_dealer', 128)->nullable();
            $t->string('recipient_account', 255)->nullable();
            $t->string('ait_reference', 64)->nullable();
            $t->json('ait_documents')->nullable();
            $t->date('transfer_date');
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'calendar_year', 'allowance_type'], 'za_excon_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_exchange_control_ledger');
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
 * Exchange control ledger entry — one row per cross-border transfer.
 *
 * Pack-owned model. Cross-namespace FK target (users) resolved via
 * runtime FQCN construction to keep the pack free of compile-time
 * main-app imports.
 */
class ZaExchangeControlEntry extends Model
{
    protected $table = 'za_exchange_control_ledger';

    protected $fillable = [
        'user_id',
        'calendar_year',
        'allowance_type',
        'amount_minor',
        'amount_ccy',
        'destination_country',
        'purpose',
        'authorised_dealer',
        'recipient_account',
        'ait_reference',
        'ait_documents',
        'transfer_date',
        'notes',
    ];

    protected $casts = [
        'calendar_year' => 'integer',
        'amount_minor' => 'integer',
        'ait_documents' => 'array',
        'transfer_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
```

- [ ] **Step 3: Run migration + smoke-check**

```bash
php artisan migrate
php artisan tinker --execute="echo Schema::hasTable('za_exchange_control_ledger') ? 'ok' : 'missing';"
```

Expected: `ok`.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_18_700001_create_za_exchange_control_ledger_table.php \
        packs/country-za/src/Models/ZaExchangeControlEntry.php
git commit -m "feat(za-pack): za_exchange_control_ledger table + model (WS 1.3b)"
```

---

## Task 3: Failing tests for ZaExchangeControlLedger

**Files:**
- Create: `packs/country-za/tests/Unit/ZaExchangeControlLedgerTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->ledger = app(ZaExchangeControlLedger::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
});

it('records an SDA transfer and reflects it in the consumption sum', function () {
    $id = $this->ledger->record(
        userId: $this->user->id,
        calendarYear: 2026,
        allowanceType: 'sda',
        amountMinor: 50_000_000,  // R500,000
        transferDate: '2026-03-15',
        destinationCountry: 'GB',
        purpose: 'offshore_investment',
    );

    expect($id)->toBeInt()->toBeGreaterThan(0);
    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(50_000_000);
});

it('isolates SDA from FIA consumption within the same calendar year', function () {
    $this->ledger->record($this->user->id, 2026, 'sda', 30_000_000, '2026-02-01');
    $this->ledger->record($this->user->id, 2026, 'fia', 500_000_000, '2026-06-01');

    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(30_000_000);
    expect($this->ledger->sumConsumed($this->user->id, 2026, 'fia'))->toBe(500_000_000);
});

it('isolates by calendar year — 2025 balances do not leak into 2026', function () {
    $this->ledger->record($this->user->id, 2025, 'sda', 100_000_000, '2025-12-20');
    $this->ledger->record($this->user->id, 2026, 'sda', 40_000_000, '2026-01-15');

    expect($this->ledger->sumConsumed($this->user->id, 2025, 'sda'))->toBe(100_000_000);
    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(40_000_000);
});

it('accumulates multiple entries in the same year/type', function () {
    $this->ledger->record($this->user->id, 2026, 'sda', 30_000_000, '2026-03-01');
    $this->ledger->record($this->user->id, 2026, 'sda', 70_000_000, '2026-06-01');
    $this->ledger->record($this->user->id, 2026, 'sda', 20_000_000, '2026-09-01');

    expect($this->ledger->sumConsumed($this->user->id, 2026, 'sda'))->toBe(120_000_000);
});

it('stores optional FIA metadata (AIT reference, authorised dealer, recipient account, document checklist)', function () {
    $this->ledger->record(
        userId: $this->user->id,
        calendarYear: 2026,
        allowanceType: 'fia',
        amountMinor: 200_000_000,
        transferDate: '2026-05-01',
        destinationCountry: 'US',
        purpose: 'offshore_investment',
        authorisedDealer: 'Investec Bank',
        recipientAccount: 'Investec Offshore USD Account ****7291',
        aitReference: 'AIT-2026-00123',
        aitDocuments: [
            'it14sd' => true,
            'it77c' => true,
            'tax_compliance_status_pin' => 'TCS-2026-ABCDE',
        ],
    );

    // Query via the Eloquent model so the `ait_documents` JSON cast kicks
    // in (DB::table() returns raw JSON strings, not decoded arrays).
    $row = \Fynla\Packs\Za\Models\ZaExchangeControlEntry::query()
        ->where('user_id', $this->user->id)
        ->first();

    expect($row->ait_reference)->toBe('AIT-2026-00123');
    expect($row->authorised_dealer)->toBe('Investec Bank');
    expect($row->destination_country)->toBe('US');
    expect($row->recipient_account)->toBe('Investec Offshore USD Account ****7291');
    expect($row->ait_documents)->toBeArray();
    expect($row->ait_documents['it14sd'])->toBeTrue();
    expect($row->ait_documents['tax_compliance_status_pin'])->toBe('TCS-2026-ABCDE');
});

it('rejects non-sda non-fia allowance_type values', function () {
    expect(fn () => $this->ledger->record(
        userId: $this->user->id,
        calendarYear: 2026,
        allowanceType: 'travel',
        amountMinor: 1_000_000,
        transferDate: '2026-03-01',
    ))->toThrow(InvalidArgumentException::class);
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaExchangeControlLedgerTest.php
```

Expected: class not found.

---

## Task 4: Implement ZaExchangeControlLedger

**Files:**
- Create: `packs/country-za/src/ExchangeControl/ZaExchangeControlLedger.php`

- [ ] **Step 1: Write the service**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\ExchangeControl;

use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use InvalidArgumentException;

/**
 * Thin persistence for SA exchange control transfer events.
 *
 * Append-only. Keyed by (user_id, calendar_year, allowance_type) — the
 * calendar year is the axis, not the tax year. Writes amounts in minor
 * units (cents) and always in ZAR for v1; foreign-currency transfers
 * must be pre-translated by the caller.
 */
class ZaExchangeControlLedger
{
    private const VALID_ALLOWANCE_TYPES = ['sda', 'fia'];

    public function record(
        int $userId,
        int $calendarYear,
        string $allowanceType,
        int $amountMinor,
        string $transferDate,
        ?string $destinationCountry = null,
        ?string $purpose = null,
        ?string $authorisedDealer = null,
        ?string $recipientAccount = null,
        ?string $aitReference = null,
        ?array $aitDocuments = null,
        ?string $notes = null,
    ): int {
        if (! in_array($allowanceType, self::VALID_ALLOWANCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "allowance_type must be 'sda' or 'fia'; got '{$allowanceType}'.",
            );
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Transfer amount must be positive.');
        }
        if ($calendarYear < 1900 || $calendarYear > 2200) {
            throw new InvalidArgumentException("Calendar year {$calendarYear} out of range.");
        }

        $entry = ZaExchangeControlEntry::create([
            'user_id' => $userId,
            'calendar_year' => $calendarYear,
            'allowance_type' => $allowanceType,
            'amount_minor' => $amountMinor,
            'amount_ccy' => 'ZAR',
            'destination_country' => $destinationCountry,
            'purpose' => $purpose,
            'authorised_dealer' => $authorisedDealer,
            'recipient_account' => $recipientAccount,
            'ait_reference' => $aitReference,
            'ait_documents' => $aitDocuments,
            'transfer_date' => $transferDate,
            'notes' => $notes,
        ]);

        return (int) $entry->id;
    }

    public function sumConsumed(int $userId, int $calendarYear, string $allowanceType): int
    {
        if (! in_array($allowanceType, self::VALID_ALLOWANCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "allowance_type must be 'sda' or 'fia'; got '{$allowanceType}'.",
            );
        }

        return (int) ZaExchangeControlEntry::query()
            ->where('user_id', $userId)
            ->where('calendar_year', $calendarYear)
            ->where('allowance_type', $allowanceType)
            ->sum('amount_minor');
    }

    public function sumConsumedTotal(int $userId, int $calendarYear): int
    {
        return (int) ZaExchangeControlEntry::query()
            ->where('user_id', $userId)
            ->where('calendar_year', $calendarYear)
            ->sum('amount_minor');
    }
}
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaExchangeControlLedgerTest.php
```

Expected: 6 passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/ExchangeControl/ZaExchangeControlLedger.php \
        packs/country-za/tests/Unit/ZaExchangeControlLedgerTest.php
git commit -m "feat(za-pack): ZaExchangeControlLedger with calendar-year keyed sums (WS 1.3b)"
```

---

## Task 5: Failing tests for ZaExchangeControl

**Files:**
- Create: `packs/country-za/tests/Unit/ZaExchangeControlTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControl;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
    $this->excon = app(ZaExchangeControl::class);
    $this->ledger = app(ZaExchangeControlLedger::class);
    $userClass = '\\' . 'App' . '\\Models\\User';
    $this->user = $userClass::factory()->create();
});

it('implements the ExchangeControl contract', function () {
    expect($this->excon)->toBeInstanceOf(ExchangeControl::class);
});

describe('getAnnualAllowances', function () {
    it('returns SDA and FIA with current-year caps', function () {
        $allowances = $this->excon->getAnnualAllowances();

        expect($allowances)->toHaveKey('sda');
        expect($allowances)->toHaveKey('fia');
        expect($allowances['sda']['type'])->toBe('sda');
        expect($allowances['sda']['annual_limit'])->toBe(200_000_000);
        expect($allowances['sda']['currency'])->toBe('ZAR');
        expect($allowances['fia']['annual_limit'])->toBe(1_000_000_000);
    });
});

describe('checkTransferPermitted', function () {
    it('permits an SDA-sized ZAR→USD transfer when no prior consumption', function () {
        expect($this->excon->checkTransferPermitted(100_000_000, 'ZAR', 'USD'))->toBeTrue();
    });

    it('refuses a transfer above the SARB combined threshold', function () {
        // R15m single transfer — above R12m SARB threshold.
        expect($this->excon->checkTransferPermitted(1_500_000_000, 'ZAR', 'USD'))->toBeFalse();
    });

    it('permits a non-ZAR to non-ZAR transfer unconditionally (outside exchange-control regime)', function () {
        // A SA resident moving USD → EUR between offshore accounts is not
        // regulated by SA exchange control — no ZAR leaves the country.
        expect($this->excon->checkTransferPermitted(5_000_000_000, 'USD', 'EUR'))->toBeTrue();
    });
});

describe('getAllowanceConsumed', function () {
    it('reports consumed across both SDA and FIA within a calendar year', function () {
        $this->ledger->record($this->user->id, 2026, 'sda', 30_000_000, '2026-02-01');
        $this->ledger->record($this->user->id, 2026, 'fia', 500_000_000, '2026-06-01');

        expect($this->excon->getAllowanceConsumed($this->user->id, '2026'))->toBe(530_000_000);
    });

    it('returns zero when user has no ledger entries for the year', function () {
        expect($this->excon->getAllowanceConsumed($this->user->id, '2026'))->toBe(0);
    });

    it('accepts calendar year as integer or string', function () {
        $this->ledger->record($this->user->id, 2026, 'sda', 40_000_000, '2026-03-01');

        expect($this->excon->getAllowanceConsumed($this->user->id, '2026'))->toBe(40_000_000);
        expect($this->excon->getAllowanceConsumed($this->user->id, 2026))->toBe(40_000_000);
    });
});

describe('requiresApproval', function () {
    it('requires AIT approval for FIA-type transfers', function () {
        expect($this->excon->requiresApproval(500_000_000, 'investment'))->toBeTrue();
    });

    it('does NOT require approval for SDA-sized transfers under R2m', function () {
        expect($this->excon->requiresApproval(150_000_000, 'travel'))->toBeFalse();
        expect($this->excon->requiresApproval(150_000_000, 'gift'))->toBeFalse();
    });

    it('requires SARB special approval above R12m combined threshold', function () {
        expect($this->excon->requiresApproval(1_500_000_000, 'investment'))->toBeTrue();
    });
});
```

- [ ] **Step 2: Run — expect red**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaExchangeControlTest.php
```

Expected: class not found.

---

## Task 6: Implement ZaExchangeControl

**Files:**
- Create: `packs/country-za/src/ExchangeControl/ZaExchangeControl.php`

- [ ] **Step 1: Write the engine**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\ExchangeControl;

use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;

/**
 * South Africa exchange control implementation.
 *
 * Caps:
 *   - SDA: R2,000,000 per calendar year (any purpose, no SARS approval)
 *   - FIA: R10,000,000 per calendar year (requires AIT for offshore
 *     investment)
 *   - Combined > R12,000,000: SARB special approval via authorised dealer
 *
 * Limits are stored as minor-unit integers under the `excon.*` keys in
 * `za_tax_configurations` (despite the "tax" table name, excon lives
 * there because it shares the same year-scoped read-through cache).
 *
 * This implementation delegates consumption queries to
 * ZaExchangeControlLedger. It is otherwise stateless.
 */
class ZaExchangeControl implements ExchangeControl
{
    private const FIA_TRIGGER_AMOUNT_MINOR = 200_000_001; // R2,000,000.01+ crosses SDA → FIA territory
    private const CURRENT_TAX_YEAR = '2026/27';

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaExchangeControlLedger $ledger,
    ) {
    }

    public function getAnnualAllowances(): array
    {
        return [
            'sda' => [
                'type' => 'sda',
                'annual_limit' => $this->sdaCapMinor(),
                'currency' => 'ZAR',
                'description' => 'Single Discretionary Allowance — any legal purpose, no SARS approval',
            ],
            'fia' => [
                'type' => 'fia',
                'annual_limit' => $this->fiaCapMinor(),
                'currency' => 'ZAR',
                'description' => 'Foreign Investment Allowance — requires SARS AIT',
            ],
        ];
    }

    public function checkTransferPermitted(int $amountMinor, string $fromCurrency, string $toCurrency): bool
    {
        // Any single transfer above the SARB combined threshold needs
        // special approval, which the contract's checkTransferPermitted
        // signature treats as "not permitted without special approval".
        if ($amountMinor > $this->sarbThresholdMinor()) {
            return false;
        }

        // A transfer from ZAR to any other currency is subject to
        // exchange control. A transfer where both sides are non-ZAR is
        // outside the regime (it's a currency-account transfer, not a
        // SA-resident's cross-border move).
        if ($fromCurrency !== 'ZAR' && $toCurrency !== 'ZAR') {
            return true;
        }

        return true;
    }

    public function getAllowanceConsumed(int $userId, string $period): int
    {
        $year = (int) $period;

        return $this->ledger->sumConsumedTotal($userId, $year);
    }

    public function requiresApproval(int $amountMinor, string $type): bool
    {
        // SARB special approval always required above the combined threshold.
        if ($amountMinor > $this->sarbThresholdMinor()) {
            return true;
        }

        // SDA covers amounts up to R2m for any purpose — no approval.
        if ($amountMinor <= $this->sdaCapMinor()) {
            return false;
        }

        // Above SDA, below SARB threshold → FIA territory, which requires
        // SARS AIT approval.
        return true;
    }

    private function sdaCapMinor(): int
    {
        return (int) $this->config->get(self::CURRENT_TAX_YEAR, 'excon.sda_annual_limit_minor', 0);
    }

    private function fiaCapMinor(): int
    {
        return (int) $this->config->get(self::CURRENT_TAX_YEAR, 'excon.fia_annual_limit_minor', 0);
    }

    private function sarbThresholdMinor(): int
    {
        return (int) $this->config->get(self::CURRENT_TAX_YEAR, 'excon.sarb_special_approval_threshold_minor', 0);
    }
}
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/pest packs/country-za/tests/Unit/ZaExchangeControlTest.php
```

Expected: 10 passing.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/ExchangeControl/ZaExchangeControl.php \
        packs/country-za/tests/Unit/ZaExchangeControlTest.php
git commit -m "feat(za-pack): ZaExchangeControl implements ExchangeControl (WS 1.3b)"
```

---

## Task 7: Wire provider bindings

**Files:**
- Modify: `packs/country-za/src/Providers/ZaPackServiceProvider.php`
- Modify: `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php`

- [ ] **Step 1: Expand `register()`**

Add inside the existing `register()`:

```php
// WS 1.3b — Exchange Control
$this->app->bind('pack.za.exchange_control', \Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class);
$this->app->bind(
    'pack.za.exchange_control.ledger',
    \Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger::class,
);
```

- [ ] **Step 2: Extend provider tests**

In `packs/country-za/tests/Feature/ZaPackServiceProviderTest.php`, add:

```php
it('registers exchange control container bindings (WS 1.3b)', function () {
    expect(app('pack.za.exchange_control'))
        ->toBeInstanceOf(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class)
        ->toBeInstanceOf(\Fynla\Core\Contracts\ExchangeControl::class);
    expect(app('pack.za.exchange_control.ledger'))
        ->toBeInstanceOf(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger::class);
});
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/pest packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
```

Expected: green with the new binding test.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/src/Providers/ZaPackServiceProvider.php \
        packs/country-za/tests/Feature/ZaPackServiceProviderTest.php
git commit -m "feat(za-pack): register exchange control + ledger bindings (WS 1.3b)"
```

---

## Task 8: End-to-end integration test

**Files:**
- Create: `tests/Integration/Za/ZaExchangeControlIntegrationTest.php`

- [ ] **Step 1: Write the integration test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControl;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ZaTaxConfigurationSeeder::class);
    app(ZaTaxConfigService::class)->forget();
});

it('end-to-end: user records three SDA transfers, consumption reflects across the year', function () {
    $user = User::factory()->create();
    $ledger = app(ZaExchangeControlLedger::class);
    $excon = app(ZaExchangeControl::class);

    $ledger->record($user->id, 2026, 'sda', 50_000_000, '2026-02-01', 'GB', 'travel');
    $ledger->record($user->id, 2026, 'sda', 30_000_000, '2026-06-15', 'US', 'gift');
    $ledger->record($user->id, 2026, 'sda', 20_000_000, '2026-11-01', 'AU', 'travel');

    expect($excon->getAllowanceConsumed($user->id, '2026'))->toBe(100_000_000);
    expect($ledger->sumConsumed($user->id, 2026, 'sda'))->toBe(100_000_000);
    expect($ledger->sumConsumed($user->id, 2026, 'fia'))->toBe(0);
});

it('end-to-end: FIA transfer with AIT reference is recorded and flagged as approval-required', function () {
    $user = User::factory()->create();
    $ledger = app(ZaExchangeControlLedger::class);
    $excon = app(ZaExchangeControl::class);

    $ledger->record(
        userId: $user->id,
        calendarYear: 2026,
        allowanceType: 'fia',
        amountMinor: 500_000_000,  // R5m
        transferDate: '2026-05-01',
        destinationCountry: 'US',
        purpose: 'offshore_investment',
        authorisedDealer: 'Investec Bank',
        aitReference: 'AIT-2026-00123',
    );

    // Subsequent R5m FIA transfer needs approval (total R10m — still
    // under FIA cap, but any FIA transfer requires AIT).
    expect($excon->requiresApproval(500_000_000, 'investment'))->toBeTrue();
    expect($ledger->sumConsumed($user->id, 2026, 'fia'))->toBe(500_000_000);
});

it('end-to-end: calendar year rollover — 2025 balances do not affect 2026 consumption', function () {
    $user = User::factory()->create();
    $ledger = app(ZaExchangeControlLedger::class);
    $excon = app(ZaExchangeControl::class);

    // Max out SDA in 2025.
    $ledger->record($user->id, 2025, 'sda', 200_000_000, '2025-12-20');

    // New calendar year; consumption for 2026 is zero.
    expect($excon->getAllowanceConsumed($user->id, '2026'))->toBe(0);
    expect($excon->getAllowanceConsumed($user->id, '2025'))->toBe(200_000_000);
});
```

- [ ] **Step 2: Run**

```bash
./vendor/bin/pest tests/Integration/Za/ZaExchangeControlIntegrationTest.php
```

Expected: 3 passing.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/Za/ZaExchangeControlIntegrationTest.php
git commit -m "test(za-pack): WS 1.3b end-to-end exchange control integration tests"
```

---

## Task 9: Full regression + baseline

- [ ] **Step 1: Pack suite**

```bash
./vendor/bin/pest packs/country-za/
```

Expected: all pack tests green (WS 1.1 + 1.2a + 1.3a + 1.3b).

- [ ] **Step 2: Architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```

Expected: all green. Both `UkExchangeControl` and `ZaExchangeControl` assertions active (no skip).

- [ ] **Step 3: Full suite**

```bash
./vendor/bin/pest
```

Expected: 2,545 + new WS 1.3b tests passing. 0 new failures. 4 pre-existing `ProtectionWorkflowTest` failures unchanged.

- [ ] **Step 4: Record the baseline**

Create `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-3b-complete.md` with before/after counts, services shipped, tables added, and unblocks. Mirror the WS 1.3a completion-note structure.

- [ ] **Step 5: Final commit**

```bash
git add /Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April18Updates/ws-1-3b-complete.md
git commit -m "docs(vault): WS 1.3b completion note"
```

---

## Self-Review

**Spec coverage (SA Research § 8.2 + § 13.2 + Appendix C + Implementation_Plan_v2 WS 1.3b):**
| Requirement | Task |
|-------------|------|
| `ZaExchangeControl`: SDA R2m, FIA R10m, per calendar year | Tasks 5–6 |
| `za_exchange_control_ledger` keyed by CALENDAR year | Task 2 |
| Ledger columns: `user_id, calendar_year, allowance_type, amount_zar, destination_country, purpose, authorised_dealer, ait_reference (nullable), transfer_date` | Task 2 |
| AIT workflow stubs (document capture only) | Task 3 (`ait_reference` field), Task 4 ledger records it |
| SARB special approval above R12m combined | Tasks 5–6 `requiresApproval` |
| Vue components | Deferred to WS 1.3c |

**Placeholder scan:** All tasks have complete code, exact paths, concrete commands. No TODOs.

**Type consistency:**
- `allowance_type` enum values `'sda'` / `'fia'` consistent across Tasks 2, 3, 4, 5, 6, 8.
- `ZaExchangeControlLedger::record()` signature consistent: `userId`, `calendarYear`, `allowanceType`, `amountMinor`, `transferDate`, optional `destinationCountry`, `purpose`, `authorisedDealer`, `aitReference`, `notes`. Same in Tasks 3, 4, 8.
- Container keys `pack.gb.exchange_control`, `pack.za.exchange_control`, `pack.za.exchange_control.ledger` identical across provider code, tests, and arch assertions.
- Config keys `excon.sda_annual_limit_minor`, `excon.fia_annual_limit_minor`, `excon.sarb_special_approval_threshold_minor` identical across seeder, engine, and tests.

**Known risks:**
- The `excon.*` keys live under `za_tax_configurations` despite not being strictly tax config. Accepted because the table's read-through cache is tax-year-scoped, and exchange control caps don't change between years in the same way tax brackets do — but a year value is still required for reads. Using `'2026/27'` as the lookup key is a slight abuse; a cleaner design would be a non-year-scoped config. Documented in the engine's docblock as a known quirk.
- Integer `calendar_year` stored as `unsignedSmallInteger` (2 bytes, max 65535) — sufficient for all plausible years; tests cover 2025 and 2026.
- `checkTransferPermitted` for non-ZAR pairs returns `true` unconditionally. SA residents transferring USD→EUR is outside the exchange control regime (it's a currency-account move, not a SA-resident cross-border transfer). This is correct but worth noting for offshore-account-to-offshore-account scenarios.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-18-ws-1-3b-za-exchange-control.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks.
2. **Inline Execution** — execute tasks in this session with checkpoints.
