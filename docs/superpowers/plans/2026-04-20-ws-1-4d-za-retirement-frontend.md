# WS 1.4d — SA Retirement Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **MANDATORY GATE:** Before implementation begins, this plan MUST be passed through `/prd-writer` to produce a canonical PRD. Rule: `feedback_workflow_spec_plan_prd.md` — never skip.

**Status:** Amended — 20 April 2026 — conflicts resolved against codebase audit (code-explorer + code-architect). Changes: (1) `dc_pensions.pension_type` is a MySQL ENUM — new pack migration converts to `VARCHAR(60)` as prerequisite (new Task 0.5); (2) sidebar entry uses `route` key (not `path`) + includes `section: 'zaSection'` (Task 0 fix); (3) `LifeAnnuityQuoteRequest` field renamed `section_10c_pool_minor` → `declared_section_10c_pool_minor` for v1.1 tracker-threading survivability; (4) UK `RetirementController` + `RetirementAgent` patched to filter `country_code != 'ZA'` — prevents ZA funds leaking into the UK retirement view (new Task 3.5); (5) six new `EXCLUDED_PATTERNS` prefixed with `/za/` to prevent UK namespace collision; (6) `CalculateTaxReliefRequest` response omits `carry_forward_minor` in v1 (engine is stateless; v1.1 threads `ZaSection11fTracker`).

**Goal:** Ship the third SA frontend surface — a single `/za/retirement` page with three tabs (Accumulation, Decumulation, Compliance) — consuming the WS 1.4a/b/c backends (`pack.za.retirement*`, `pack.za.reg28.monitor`). Adds one HTTP controller (`ZaRetirementController`) exposing 13 endpoints, one Vuex module, one axios service, one view, and 13 Vue components. Zero `SideMenu.vue` edits (sidebar stays data-driven).

**Architecture:** Thin HTTP adapter over pack container bindings; business logic stays in the pack. Routes extend the existing `/api/za/*` middleware group (`auth:sanctum + active.jurisdiction + pack.enabled:za`). ZA retirement funds live on the existing `dc_pensions` table (scoped by `country_code='ZA'`) with Two-Pot bucket balances on the pack-owned `za_retirement_fund_buckets`. Reg 28 snapshots live on the pack-owned `za_reg28_snapshots`. What-if endpoints (`simulate`, `quote`, `check`, `tax-relief/calculate`, `compulsory-apportion`) are read-only and added to `PreviewWriteInterceptor::EXCLUDED_PATTERNS` so preview users see real calculated responses.

**Tech Stack:** Laravel 10 (PHP 8.2), Vue 3, Vuex 4, Vue Router 4, Tailwind, Pest, Playwright.

**Spec sources:**
- `docs/superpowers/specs/2026-04-20-ws-1-4d-za-retirement-frontend-design.md` — approved design
- `Plans/Implementation_Plan_v2.md` — WS 1.4 section
- `Plans/SA_Research_and_Mapping.md` — § 9 Retirement
- `April/April18Updates/PRD-ws-1-4a-za-retirement.md` — backend contracts
- `docs/superpowers/plans/2026-04-18-ws-1-3c-za-investment-excon-frontend.md` — analogous frontend pattern
- `app/Http/Controllers/Api/Za/ZaInvestmentController.php` — controller template
- `resources/js/store/modules/zaExchangeControl.js` — Vuex module template
- `resources/js/components/ZA/ExchangeControl/*` — component patterns
- `fynlaDesignGuide.md` v1.4.0 — icons functional-only; sidebar-only surface

**Non-goals / deferrals:**
- UK retirement view changes — ZA funds only on `/za/retirement`.
- Savings-Pot once-per-tax-year frequency enforcement — backend doesn't enforce it; neither does v1 UI.
- SASSA Old Age Grant capture — data field only; no dedicated widget in v1.
- Reg 28 look-through roll-up from individual holdings — v1 accepts manual allocation input.
- Joint ownership on ZA retirement funds — SARS rules make them individual-only.
- Preview persona retirement fixtures — WS 1.7; v1 empty states must render.
- Section 11F carry-forward auto-threading via `ZaSection11fTracker` — v1 tax-relief endpoint returns the single-year deduction; carry-forward is a v1.1 enhancement.
- Section 10C pool auto-threading via `ZaSection10cTracker` — v1 UI accepts a user-declared pool as an input.

**Resolved assumptions (validated by `/prd-writer` 20 April 2026):**
1. ZA retirement funds reuse the existing `dc_pensions` table with `country_code='ZA'` discriminator. **`pension_type` is a MySQL ENUM** (`occupational|sipp|personal|stakeholder`) per `database/schema/mysql-schema.sql:322` — a new pack migration (Task 0.5) converts it to `VARCHAR(60)` to accept the 4 SA values.
2. `za_retirement_fund_buckets` holds the Two-Pot balances, keyed by `(user_id, fund_holding_id)` — one row per ZA `dc_pensions` row.
3. `za_reg28_snapshots` schema matches `ZaReg28Monitor::snapshot()` writes: `user_id, fund_holding_id (nullable), as_at_date, allocation (json), offshore_compliant, equity_compliant, property_compliant, private_equity_compliant, commodities_compliant, hedge_funds_compliant, other_compliant, single_entity_compliant, compliant, breaches (json)`.
4. Reg 28 allocation input uses **7 asset-class percentages 0–100 summing to 100** (`offshore, equity, property, private_equity, commodities, hedge_funds, other`) plus **one standalone `single_entity` max-exposure percentage**. Backend `ZaReg28Monitor::check()` accepts the associative array and does NOT itself validate sum-to-100 (`Reg28CheckRequest` validates).
5. One new pack-owned migration required (Task 0.5) — converts `dc_pensions.pension_type` ENUM → VARCHAR(60). No other schema changes needed; `za_retirement_fund_buckets` and `za_reg28_snapshots` already exist from WS 1.4a/c.
6. `scheme_type` column is an ENUM (`workplace|sipp|personal`) — SA funds safely write `scheme_type='personal'` since `'personal'` is a valid existing value.
7. UK `RetirementController` + `RetirementAgent` currently fetch all `DCPension` rows without `country_code` filter. Task 3.5 patches this so ZA funds don't leak into the UK retirement view.

---

## File structure

### Backend files created

```
app/Http/Controllers/Api/Za/
  ZaRetirementController.php               (new — ~13 endpoint methods)

app/Http/Requests/Za/Retirement/
  StoreFundRequest.php                     (new)
  StoreContributionRequest.php             (new)
  SimulateSavingsPotWithdrawalRequest.php  (new)
  CalculateTaxReliefRequest.php            (new)
  LivingAnnuityQuoteRequest.php            (new)
  LifeAnnuityQuoteRequest.php              (new)
  CompulsoryApportionRequest.php           (new)
  Reg28CheckRequest.php                    (new)

app/Http/Resources/Za/Retirement/
  ZaRetirementFundResource.php             (new)
  ZaRetirementBucketResource.php           (new)
  ZaAnnuityQuoteResource.php               (new)
  Reg28SnapshotResource.php                (new)

tests/Feature/Api/Za/
  ZaRetirementControllerTest.php           (new — ~16 tests)
  ZaRetirementReg28ControllerTest.php      (new — ~6 tests)
```

### Backend files modified

```
routes/api.php                             (+ retirement route group)
app/Http/Middleware/PreviewWriteInterceptor.php   (+6 EXCLUDED_PATTERNS)
```

### Frontend files created

```
resources/js/views/ZA/
  ZaRetirementDashboard.vue                (new — page view, tab router)

resources/js/components/ZA/Retirement/
  ZaRetirementTabs.vue                     (new)
  ZaRetirementSummary.vue                  (new)
  ZaRetirementFundsList.vue                (new)
  ZaRetirementFundForm.vue                 (new — modal)
  ZaTwoPotTracker.vue                      (new)
  ZaContributionModal.vue                  (new)
  ZaSavingsPotWithdrawalCard.vue           (new)
  ZaSection11fReliefCalculator.vue         (new)
  ZaLivingAnnuitySlider.vue                (new)
  ZaLifeAnnuityQuote.vue                   (new)
  ZaCompulsoryAnnuitisationCard.vue        (new)
  ZaReg28AllocationForm.vue                (new)
  ZaReg28ComplianceCard.vue                (new)
  ZaReg28SnapshotHistory.vue               (new)

resources/js/services/
  zaRetirementService.js                   (new)
```

### Frontend files modified

```
resources/js/store/modules/zaRetirement.js   (REPLACE WS 1.2b placeholder with functional module)
resources/js/store/modules/jurisdiction.js   (+1 entry in MODULES_BY_JURISDICTION.za)
resources/js/router/index.js                 (+1 lazy route)
```

---

## Task 0 — Sidebar entry

**Files:**
- Modify: `resources/js/store/modules/jurisdiction.js`
- Modify: `resources/js/components/Shared/SideMenuIcon.vue` — verify `briefcase` icon key exists (if not, add it)

- [ ] **Step 1: Verify `briefcase` icon key exists in SideMenuIcon**

Run:
```bash
grep -n "briefcase" resources/js/components/Shared/SideMenuIcon.vue
```
Expected: at least one match — the icon key is already available. If zero matches, substitute `'chart-bar'` in this task and the sidebar config.

- [ ] **Step 2: Append `za-retirement` to `MODULES_BY_JURISDICTION.za`**

Open `resources/js/store/modules/jurisdiction.js`, locate the `MODULES_BY_JURISDICTION` constant, and append to the `za` array (preserve existing entries for `za-savings`, `za-investment`, `za-exchange-control`):

```js
{
  key: 'za-retirement',
  label: 'Retirement',
  route: '/za/retirement',
  section: 'zaSection',
  icon: 'briefcase',
},
```

**Amendment A1:** `route` (not `path`) — `SideMenu.vue:110` reads `mod.route`. `section: 'zaSection'` — required so the ZA sidebar section auto-expands when the user navigates to `/za/retirement`.

- [ ] **Step 3: Smoke-check sidebar renders the new entry**

Run: `./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php -v` — any green test (sanity the testsuite still runs; no UI assertion here).

- [ ] **Step 4: Commit**

```bash
git add resources/js/store/modules/jurisdiction.js
git commit -m "feat(za-retirement): add sidebar entry for WS 1.4d"
```

---

## Task 0.5 — Pack migration: extend `dc_pensions.pension_type` ENUM

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_20_000001_extend_pension_type_for_za_fund_types.php`

**Why:** Per the codebase audit, `dc_pensions.pension_type` is a MySQL ENUM at `database/schema/mysql-schema.sql:322`: `enum('occupational','sipp','personal','stakeholder') NOT NULL DEFAULT 'occupational'`. The plan writes SA values (`retirement_annuity`, etc.) which are not in the enum — MySQL would silently truncate to empty string or throw in strict mode. Converting to `VARCHAR(60)` removes the UK-specific constraint and is forward-compatible for all future country packs.

- [ ] **Step 1: Create the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert dc_pensions.pension_type ENUM → VARCHAR(60).
 *
 * The original UK ENUM (occupational|sipp|personal|stakeholder) is
 * UK-specific. SA funds use retirement_annuity / pension_fund /
 * provident_fund / preservation_fund. Future country packs will bring
 * their own fund type vocabularies. VARCHAR removes the DB-level
 * country-specific constraint; request-level validation stays
 * jurisdiction-aware via form requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_pensions')) {
            return;
        }

        DB::statement("ALTER TABLE dc_pensions MODIFY COLUMN pension_type VARCHAR(60) NOT NULL DEFAULT 'occupational'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('dc_pensions')) {
            return;
        }

        // Reverting: any SA row would violate the ENUM. Don't attempt the conversion
        // automatically — require a manual data migration first.
        DB::statement("ALTER TABLE dc_pensions MODIFY COLUMN pension_type ENUM('occupational','sipp','personal','stakeholder') NOT NULL DEFAULT 'occupational'");
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: one new migration row executes. Pack migrations auto-load via `ZaPackServiceProvider::boot()`'s `loadMigrationsFrom` — no config change needed.

- [ ] **Step 3: Verify column type**

```bash
php artisan tinker --execute="echo \Schema::getColumnType('dc_pensions', 'pension_type');"
```
Expected: `string`.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_20_000001_extend_pension_type_for_za_fund_types.php
git commit -m "feat(za-retirement): convert dc_pensions.pension_type ENUM to VARCHAR(60) for SA fund types"
```

---

## Task 1 — Form requests (8 files)

**Files:**
- Create: `app/Http/Requests/Za/Retirement/StoreFundRequest.php`
- Create: `app/Http/Requests/Za/Retirement/StoreContributionRequest.php`
- Create: `app/Http/Requests/Za/Retirement/SimulateSavingsPotWithdrawalRequest.php`
- Create: `app/Http/Requests/Za/Retirement/CalculateTaxReliefRequest.php`
- Create: `app/Http/Requests/Za/Retirement/LivingAnnuityQuoteRequest.php`
- Create: `app/Http/Requests/Za/Retirement/LifeAnnuityQuoteRequest.php`
- Create: `app/Http/Requests/Za/Retirement/CompulsoryApportionRequest.php`
- Create: `app/Http/Requests/Za/Retirement/Reg28CheckRequest.php`

- [ ] **Step 1: Create `StoreFundRequest`**

`app/Http/Requests/Za/Retirement/StoreFundRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund_type' => ['required', Rule::in(['retirement_annuity', 'pension_fund', 'provident_fund', 'preservation_fund'])],
            'provider' => ['required', 'string', 'max:120'],
            'scheme_name' => ['nullable', 'string', 'max:255'],
            'member_number' => ['nullable', 'string', 'max:60'],
            'starting_vested_minor' => ['required', 'integer', 'min:0'],
            'starting_savings_minor' => ['required', 'integer', 'min:0'],
            'starting_retirement_minor' => ['required', 'integer', 'min:0'],
            'provident_vested_pre2021_minor' => ['nullable', 'integer', 'min:0', 'required_if:fund_type,provident_fund'],
        ];
    }
}
```

- [ ] **Step 2: Create `StoreContributionRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund_holding_id' => ['required', 'integer', 'exists:dc_pensions,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'contribution_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
```

- [ ] **Step 3: Create `SimulateSavingsPotWithdrawalRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class SimulateSavingsPotWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund_holding_id' => ['required', 'integer', 'exists:dc_pensions,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'current_annual_income_minor' => ['required', 'integer', 'min:0'],
            'age' => ['required', 'integer', 'between:18,125'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
```

- [ ] **Step 4: Create `CalculateTaxReliefRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class CalculateTaxReliefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contribution_minor' => ['required', 'integer', 'min:1'],
            'gross_income_minor' => ['required', 'integer', 'min:0'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
```

- [ ] **Step 5: Create `LivingAnnuityQuoteRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class LivingAnnuityQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capital_minor' => ['required', 'integer', 'min:1'],
            'drawdown_rate_bps' => ['required', 'integer', 'min:1', 'max:10000'],
            'age' => ['required', 'integer', 'between:18,125'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
```

Note: backend enforces the 250–1750 band via `InvalidArgumentException` → 422 (Amendment A3 pattern). Request-level we only reject outright nonsense (<1 bps or >10000 bps).

- [ ] **Step 6: Create `LifeAnnuityQuoteRequest`**

**Amendment A2:** Field is `declared_section_10c_pool_minor` — not `section_10c_pool_minor`. This flags that v1 accepts a user declaration; v1.1 will thread `ZaSection10cTracker` as an authoritative default while keeping this field as an override. Renaming now avoids a breaking change later.

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class LifeAnnuityQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annual_annuity_minor' => ['required', 'integer', 'min:1'],
            'declared_section_10c_pool_minor' => ['required', 'integer', 'min:0'],
            'age' => ['required', 'integer', 'between:18,125'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
```

- [ ] **Step 7: Create `CompulsoryApportionRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class CompulsoryApportionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vested_minor' => ['required', 'integer', 'min:0'],
            'provident_vested_pre2021_minor' => ['required', 'integer', 'min:0'],
            'retirement_minor' => ['required', 'integer', 'min:0'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
```

- [ ] **Step 8: Create `Reg28CheckRequest`** (sum-to-100 custom rule)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class Reg28CheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'allocation' => ['required', 'array'],
            'allocation.offshore' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.equity' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.property' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.private_equity' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.commodities' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.hedge_funds' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.other' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.single_entity' => ['required', 'numeric', 'min:0', 'max:100'],
            'fund_holding_id' => ['nullable', 'integer', 'exists:dc_pensions,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $alloc = $this->input('allocation', []);
            $classSum = (float) ($alloc['offshore'] ?? 0)
                + (float) ($alloc['equity'] ?? 0)
                + (float) ($alloc['property'] ?? 0)
                + (float) ($alloc['private_equity'] ?? 0)
                + (float) ($alloc['commodities'] ?? 0)
                + (float) ($alloc['hedge_funds'] ?? 0)
                + (float) ($alloc['other'] ?? 0);

            if (abs($classSum - 100.0) > 0.01) {
                $validator->errors()->add(
                    'allocation',
                    'Asset-class allocation (offshore, equity, property, private_equity, commodities, hedge_funds, other) must sum to 100%.',
                );
            }
        });
    }
}
```

- [ ] **Step 9: Run syntax check**

```bash
for f in app/Http/Requests/Za/Retirement/*.php; do php -l "$f" | grep -v "No syntax errors"; done
```
Expected: no output.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/Za/Retirement/
git commit -m "feat(za-retirement): form requests for WS 1.4d"
```

---

## Task 2 — Resources (4 files)

**Files:**
- Create: `app/Http/Resources/Za/Retirement/ZaRetirementFundResource.php`
- Create: `app/Http/Resources/Za/Retirement/ZaRetirementBucketResource.php`
- Create: `app/Http/Resources/Za/Retirement/ZaAnnuityQuoteResource.php`
- Create: `app/Http/Resources/Za/Retirement/Reg28SnapshotResource.php`

- [ ] **Step 1: Create `ZaRetirementBucketResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaRetirementBucketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fund_holding_id' => (int) $this->fund_holding_id,
            'vested_minor' => (int) $this->vested_balance_minor,
            'provident_vested_pre2021_minor' => (int) $this->provident_vested_pre2021_balance_minor,
            'savings_minor' => (int) $this->savings_balance_minor,
            'retirement_minor' => (int) $this->retirement_balance_minor,
            'total_minor' => (int) $this->vested_balance_minor
                + (int) $this->provident_vested_pre2021_balance_minor
                + (int) $this->savings_balance_minor
                + (int) $this->retirement_balance_minor,
            'last_transaction_date_iso' => $this->last_transaction_date
                ? \Carbon\Carbon::parse($this->last_transaction_date)->toIso8601String()
                : null,
        ];
    }
}
```

- [ ] **Step 2: Create `ZaRetirementFundResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaRetirementFundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bucket = ZaRetirementFundBucket::query()
            ->where('user_id', $this->user_id)
            ->where('fund_holding_id', $this->id)
            ->first();

        $labelMap = [
            'retirement_annuity' => 'Retirement Annuity',
            'pension_fund' => 'Pension Fund',
            'provident_fund' => 'Provident Fund',
            'preservation_fund' => 'Preservation Fund',
        ];

        return [
            'id' => (int) $this->id,
            'fund_type' => (string) $this->pension_type,
            'fund_type_label' => $labelMap[$this->pension_type] ?? (string) $this->pension_type,
            'provider' => (string) $this->provider,
            'scheme_name' => $this->scheme_name,
            'member_number' => $this->member_number,
            'country' => 'South Africa',
            'country_code' => 'ZA',
            'buckets' => $bucket ? new ZaRetirementBucketResource($bucket) : null,
            'total_balance_minor' => $bucket
                ? (int) $bucket->vested_balance_minor
                    + (int) $bucket->provident_vested_pre2021_balance_minor
                    + (int) $bucket->savings_balance_minor
                    + (int) $bucket->retirement_balance_minor
                : 0,
            'created_at_iso' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 3: Create `ZaAnnuityQuoteResource`**

Note: quote results come from two different calculators so the resource is shaped around a passed-in assoc array, not an Eloquent model. We'll still use `JsonResource` for consistency.

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaAnnuityQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $kind = $r['kind'];

        $base = [
            'kind' => $kind,
            'tax_year' => $r['tax_year'],
            'capital_minor' => $r['capital_minor'] ?? null,
            'annual_income_minor' => $r['annual_income_minor'] ?? $r['gross_annual_minor'] ?? $r['annual_annuity_minor'],
            'tax_due_minor' => $r['tax_due_minor'],
            'net_annual_minor' => $r['net_annual_minor'] ?? ($r['annual_annuity_minor'] - $r['tax_due_minor']),
            'monthly_income_minor' => intdiv((int) ($r['annual_income_minor'] ?? $r['gross_annual_minor'] ?? $r['annual_annuity_minor']), 12),
            'net_monthly_income_minor' => intdiv((int) ($r['net_annual_minor'] ?? ($r['annual_annuity_minor'] - $r['tax_due_minor'])), 12),
            'marginal_rate' => (float) $r['marginal_rate'],
        ];

        if ($kind === 'living') {
            $base['drawdown_rate_bps'] = (int) $r['drawdown_rate_bps'];
        }

        if ($kind === 'life') {
            $base['section_10c_exempt_minor'] = (int) $r['section_10c_exempt_minor'];
            $base['section_10c_remaining_pool_minor'] = (int) $r['section_10c_remaining_pool_minor'];
            $base['pool_exhausted'] = (bool) $r['pool_exhausted'];
            $base['taxable_minor'] = (int) $r['taxable_minor'];
        }

        return $base;
    }
}
```

- [ ] **Step 4: Create `Reg28SnapshotResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Reg28SnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'fund_holding_id' => $this->fund_holding_id ? (int) $this->fund_holding_id : null,
            'as_at_date_iso' => $this->as_at_date ? \Carbon\Carbon::parse($this->as_at_date)->toDateString() : null,
            'compliant' => (bool) $this->compliant,
            'breaches' => is_array($this->breaches) ? $this->breaches : json_decode((string) $this->breaches, true) ?: [],
            'allocation' => is_array($this->allocation) ? $this->allocation : json_decode((string) $this->allocation, true) ?: [],
            'per_class_compliance' => [
                'offshore' => (bool) $this->offshore_compliant,
                'equity' => (bool) $this->equity_compliant,
                'property' => (bool) $this->property_compliant,
                'private_equity' => (bool) $this->private_equity_compliant,
                'commodities' => (bool) $this->commodities_compliant,
                'hedge_funds' => (bool) $this->hedge_funds_compliant,
                'other' => (bool) $this->other_compliant,
                'single_entity' => (bool) $this->single_entity_compliant,
            ],
            'created_at_iso' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Syntax check**

```bash
for f in app/Http/Resources/Za/Retirement/*.php; do php -l "$f" | grep -v "No syntax errors"; done
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Resources/Za/Retirement/
git commit -m "feat(za-retirement): API resources for WS 1.4d"
```

---

## Task 3 — Controller + routes + middleware

**Files:**
- Create: `app/Http/Controllers/Api/Za/ZaRetirementController.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Middleware/PreviewWriteInterceptor.php`

- [ ] **Step 1: Extend `EXCLUDED_PATTERNS` in `PreviewWriteInterceptor`**

**Amendment A3:** Patterns are prefixed with `/za/` so they don't match a future UK `/api/retirement/*` endpoint.

Open `app/Http/Middleware/PreviewWriteInterceptor.php`. Locate the `EXCLUDED_PATTERNS` array constant. Append these 6 entries (keep existing entries untouched):

```php
'#/za/retirement/savings-pot/simulate$#',
'#/za/retirement/tax-relief/calculate$#',
'#/za/retirement/annuities/living/quote$#',
'#/za/retirement/annuities/life/quote$#',
'#/za/retirement/annuities/compulsory-apportion$#',
'#/za/retirement/reg28/check$#',
```

- [ ] **Step 2: Create `ZaRetirementController`**

`app/Http/Controllers/Api/Za/ZaRetirementController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Retirement\CalculateTaxReliefRequest;
use App\Http\Requests\Za\Retirement\CompulsoryApportionRequest;
use App\Http\Requests\Za\Retirement\LifeAnnuityQuoteRequest;
use App\Http\Requests\Za\Retirement\LivingAnnuityQuoteRequest;
use App\Http\Requests\Za\Retirement\Reg28CheckRequest;
use App\Http\Requests\Za\Retirement\SimulateSavingsPotWithdrawalRequest;
use App\Http\Requests\Za\Retirement\StoreContributionRequest;
use App\Http\Requests\Za\Retirement\StoreFundRequest;
use App\Http\Resources\Za\Retirement\Reg28SnapshotResource;
use App\Http\Resources\Za\Retirement\ZaAnnuityQuoteResource;
use App\Http\Resources\Za\Retirement\ZaRetirementBucketResource;
use App\Http\Resources\Za\Retirement\ZaRetirementFundResource;
use App\Models\DCPension;
use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;
use Fynla\Packs\Za\Models\ZaReg28Snapshot;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService;
use Fynla\Packs\Za\Retirement\ZaContributionSplitService;
use Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator;
use Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator;
use Fynla\Packs\Za\Retirement\ZaReg28Monitor;
use Fynla\Packs\Za\Retirement\ZaRetirementEngine;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * HTTP adapter over the ZA retirement domain (WS 1.4d).
 *
 * Thin proxy: every method resolves a pack binding and delegates.
 * No business logic here. Pack owns calculations; app owns HTTP.
 *
 * Internal arithmetic in dashboard() uses Money VO (ADR-005).
 */
class ZaRetirementController extends Controller
{
    public function __construct(
        private readonly ZaContributionSplitService $splitter,
        private readonly ZaRetirementFundBucketRepository $buckets,
        private readonly ZaSavingsPotWithdrawalSimulator $simulator,
        private readonly ZaLivingAnnuityCalculator $livingAnnuity,
        private readonly ZaLifeAnnuityCalculator $lifeAnnuity,
        private readonly ZaCompulsoryAnnuitisationService $compulsory,
        private readonly ZaReg28Monitor $reg28,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        /** @var ZaRetirementEngine $engine */
        $engine = app('pack.za.retirement');

        $fundIds = DCPension::query()
            ->where('user_id', $userId)
            ->where('country_code', 'ZA')
            ->pluck('id')
            ->all();

        $zar = Currency::ZAR();
        $total = new Money(0, $zar);
        if (! empty($fundIds)) {
            $rows = ZaRetirementFundBucket::query()
                ->where('user_id', $userId)
                ->whereIn('fund_holding_id', $fundIds)
                ->get();
            foreach ($rows as $b) {
                $total = $total->plus(new Money(
                    (int) $b->vested_balance_minor
                    + (int) $b->provident_vested_pre2021_balance_minor
                    + (int) $b->savings_balance_minor
                    + (int) $b->retirement_balance_minor,
                    $zar,
                ));
            }
        }

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'annual_allowance_minor' => $engine->getAnnualAllowance($taxYear),
                'total_balance_minor' => $total->minor,
                'fund_count' => count($fundIds),
            ],
        ]);
    }

    public function listFunds(Request $request): JsonResponse
    {
        $funds = DCPension::query()
            ->where('user_id', $request->user()->id)
            ->where('country_code', 'ZA')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => ZaRetirementFundResource::collection($funds)]);
    }

    public function storeFund(StoreFundRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $fund = DCPension::create([
            'user_id' => $userId,
            'pension_type' => $data['fund_type'],
            'scheme_type' => 'personal',
            'provider' => $data['provider'],
            'scheme_name' => $data['scheme_name'] ?? null,
            'member_number' => $data['member_number'] ?? null,
            'country_code' => 'ZA',
        ]);

        $bucket = $this->buckets->findOrCreate($userId, (int) $fund->id);

        if (($data['starting_vested_minor'] ?? 0) > 0 ||
            ($data['starting_savings_minor'] ?? 0) > 0 ||
            ($data['starting_retirement_minor'] ?? 0) > 0
        ) {
            try {
                $this->buckets->applyDeltas(
                    $userId,
                    (int) $fund->id,
                    (int) $data['starting_vested_minor'],
                    (int) $data['starting_savings_minor'],
                    (int) $data['starting_retirement_minor'],
                    now()->toDateString(),
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        if (($data['provident_vested_pre2021_minor'] ?? 0) > 0) {
            $bucket->provident_vested_pre2021_balance_minor = (int) $data['provident_vested_pre2021_minor'];
            $bucket->save();
        }

        return response()->json(['data' => new ZaRetirementFundResource($fund->fresh())], 201);
    }

    public function showBuckets(Request $request, int $fundId): JsonResponse
    {
        $owns = DCPension::query()
            ->where('id', $fundId)
            ->where('user_id', $request->user()->id)
            ->where('country_code', 'ZA')
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Fund not found'], 404);
        }

        $bucket = $this->buckets->findOrCreate($request->user()->id, $fundId);

        return response()->json(['data' => new ZaRetirementBucketResource($bucket)]);
    }

    public function storeContribution(StoreContributionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $owns = DCPension::query()
            ->where('id', $data['fund_holding_id'])
            ->where('user_id', $userId)
            ->where('country_code', 'ZA')
            ->exists();
        if (! $owns) {
            return response()->json(['message' => 'Fund not found'], 404);
        }

        try {
            $split = $this->splitter->split((int) $data['amount_minor'], $data['contribution_date']);
            $bucket = $this->buckets->applyDeltas(
                $userId,
                (int) $data['fund_holding_id'],
                $split['vested_delta_minor'],
                $split['savings_delta_minor'],
                $split['retirement_delta_minor'],
                $data['contribution_date'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'split' => [
                    'vested_minor' => $split['vested_delta_minor'],
                    'savings_minor' => $split['savings_delta_minor'],
                    'retirement_minor' => $split['retirement_delta_minor'],
                ],
                'buckets' => new ZaRetirementBucketResource($bucket),
            ],
        ], 201);
    }

    public function simulateSavingsPotWithdrawal(SimulateSavingsPotWithdrawalRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->simulator->simulate(
                (int) $data['amount_minor'],
                (int) $data['current_annual_income_minor'],
                (int) $data['age'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function withdrawSavingsPot(SimulateSavingsPotWithdrawalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $owns = DCPension::query()
            ->where('id', $data['fund_holding_id'])
            ->where('user_id', $userId)
            ->where('country_code', 'ZA')
            ->exists();
        if (! $owns) {
            return response()->json(['message' => 'Fund not found'], 404);
        }

        try {
            $sim = $this->simulator->simulate(
                (int) $data['amount_minor'],
                (int) $data['current_annual_income_minor'],
                (int) $data['age'],
                $data['tax_year'],
            );
            $bucket = $this->buckets->applyDeltas(
                $userId,
                (int) $data['fund_holding_id'],
                0,
                -1 * (int) $data['amount_minor'],
                0,
                now()->toDateString(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'withdrawal' => [
                    'gross_minor' => (int) $data['amount_minor'],
                    'tax_minor' => $sim['tax_delta_minor'],
                    'net_minor' => $sim['net_received_minor'],
                ],
                'buckets' => new ZaRetirementBucketResource($bucket),
            ],
        ], 201);
    }

    public function calculateTaxRelief(CalculateTaxReliefRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var ZaRetirementEngine $engine */
        $engine = app('pack.za.retirement');

        $result = $engine->calculatePensionTaxRelief(
            (int) $data['contribution_minor'],
            (int) $data['gross_income_minor'],
            $data['tax_year'],
        );

        return response()->json([
            'data' => [
                'relief_amount_minor' => (int) $result['relief_amount'],
                'relief_rate' => (float) $result['relief_rate'],
                'net_cost_minor' => (int) $result['net_cost'],
                'method' => (string) $result['method'],
                'tax_year' => $data['tax_year'],
            ],
        ]);
    }

    public function quoteLivingAnnuity(LivingAnnuityQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->livingAnnuity->calculate(
                (int) $data['capital_minor'],
                (int) $data['drawdown_rate_bps'],
                (int) $data['age'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result['kind'] = 'living';
        $result['tax_year'] = $data['tax_year'];
        $result['capital_minor'] = (int) $data['capital_minor'];

        return response()->json(['data' => new ZaAnnuityQuoteResource($result)]);
    }

    public function quoteLifeAnnuity(LifeAnnuityQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->lifeAnnuity->calculate(
                (int) $data['annual_annuity_minor'],
                (int) $data['declared_section_10c_pool_minor'],
                (int) $data['age'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result['kind'] = 'life';
        $result['tax_year'] = $data['tax_year'];
        $result['annual_annuity_minor'] = (int) $data['annual_annuity_minor'];

        return response()->json(['data' => new ZaAnnuityQuoteResource($result)]);
    }

    public function apportionCompulsory(CompulsoryApportionRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->compulsory->apportion(
                (int) $data['vested_minor'],
                (int) $data['provident_vested_pre2021_minor'],
                (int) $data['retirement_minor'],
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'pcls_minor' => (int) $result['pcls_minor'],
                'compulsory_annuity_minor' => (int) $result['compulsory_annuity_minor'],
                'de_minimis_applied' => (bool) $result['de_minimis_applied'],
                'de_minimis_threshold_minor' => (int) $result['de_minimis_threshold_minor'],
                'tax_year' => $data['tax_year'],
            ],
        ]);
    }

    public function checkReg28(Reg28CheckRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->reg28->check($data['allocation'], $data['tax_year']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'compliant' => $result['compliant'],
                'breaches' => $result['breaches'],
                'per_class' => $result['per_class'],
                'tax_year' => $data['tax_year'],
            ],
        ]);
    }

    public function listReg28Snapshots(Request $request): JsonResponse
    {
        $query = ZaReg28Snapshot::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('as_at_date');

        if ($taxYear = $request->query('tax_year')) {
            // as_at_date determines SA tax year membership; we filter by date range
            [$startYear, $endSuffix] = explode('/', (string) $taxYear);
            $startYear = (int) $startYear;
            $start = sprintf('%04d-03-01', $startYear);
            $end = sprintf('%04d-02-28', $startYear + 1);
            $query->whereBetween('as_at_date', [$start, $end]);
        }

        return response()->json(['data' => Reg28SnapshotResource::collection($query->get())]);
    }

    public function storeReg28Snapshot(Reg28CheckRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        if (! empty($data['fund_holding_id'])) {
            $owns = DCPension::query()
                ->where('id', $data['fund_holding_id'])
                ->where('user_id', $userId)
                ->where('country_code', 'ZA')
                ->exists();
            if (! $owns) {
                return response()->json(['message' => 'Fund not found'], 404);
            }
        }

        try {
            $snapshot = $this->reg28->snapshot(
                $userId,
                isset($data['fund_holding_id']) ? (int) $data['fund_holding_id'] : null,
                $data['allocation'],
                now()->toDateString(),
                $data['tax_year'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new Reg28SnapshotResource($snapshot)], 201);
    }

    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
```

- [ ] **Step 3: Add routes to `routes/api.php`**

Locate the existing `/api/za/` Route group. Append the retirement sub-group INSIDE that group (after the existing `exchange-control` block):

```php
Route::prefix('retirement')->name('za.retirement.')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'dashboard'])->name('dashboard');

    Route::get('funds', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'listFunds'])->name('funds.index');
    Route::post('funds', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'storeFund'])->name('funds.store');
    Route::get('funds/{fundId}/buckets', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'showBuckets'])->name('funds.buckets');

    Route::post('contributions', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'storeContribution'])->name('contributions.store');

    Route::post('savings-pot/simulate', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'simulateSavingsPotWithdrawal'])->name('savings-pot.simulate');
    Route::post('savings-pot/withdraw', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'withdrawSavingsPot'])->name('savings-pot.withdraw');

    Route::post('tax-relief/calculate', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'calculateTaxRelief'])->name('tax-relief.calculate');

    Route::prefix('annuities')->name('annuities.')->group(function () {
        Route::post('living/quote', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'quoteLivingAnnuity'])->name('living.quote');
        Route::post('life/quote', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'quoteLifeAnnuity'])->name('life.quote');
        Route::post('compulsory-apportion', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'apportionCompulsory'])->name('compulsory-apportion');
    });

    Route::prefix('reg28')->name('reg28.')->group(function () {
        Route::match(['get', 'post'], 'check', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'checkReg28'])->name('check');
        Route::get('snapshots', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'listReg28Snapshots'])->name('snapshots.index');
        Route::post('snapshots', [\App\Http\Controllers\Api\Za\ZaRetirementController::class, 'storeReg28Snapshot'])->name('snapshots.store');
    });
});
```

- [ ] **Step 4: Verify routes register cleanly**

```bash
php artisan route:list --path=za/retirement
```
Expected: 14 routes listed (13 unique endpoints; `reg28.check` maps to 2 HTTP methods).

- [ ] **Step 5: Syntax check controller**

```bash
php -l app/Http/Controllers/Api/Za/ZaRetirementController.php
```
Expected: `No syntax errors`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaRetirementController.php routes/api.php app/Http/Middleware/PreviewWriteInterceptor.php
git commit -m "feat(za-retirement): ZaRetirementController + routes + preview interceptor patterns"
```

---

## Task 3.5 — Prevent ZA funds leaking into the UK retirement view

**Files:**
- Modify: `app/Http/Controllers/Api/RetirementController.php`
- Modify: `app/Agents/RetirementAgent.php`

**Why:** Without this patch, once a user creates a ZA retirement fund via `/za/retirement`, the UK `RetirementController::index` and `RetirementAgent` queries return that ZA fund in the UK retirement view — because they fetch all `DCPension` rows for the user without a `country_code` filter. Before WS 0.6, `country_code` was NULL for all rows; now it's `'ZA'` for SA funds. The fix is one `where` clause per query.

- [ ] **Step 1: Patch `app/Http/Controllers/Api/RetirementController.php`**

Find every `DCPension::where('user_id', ...)` query in the file (there will be 2–3). Add `->where(fn($q) => $q->whereNull('country_code')->orWhere('country_code', 'GB'))` to each. Example:

```php
// Before:
'dc_pensions' => DCPension::where('user_id', $user->id)->with('holdings')->get(),

// After:
'dc_pensions' => DCPension::where('user_id', $user->id)
    ->where(fn ($q) => $q->whereNull('country_code')->orWhere('country_code', 'GB'))
    ->with('holdings')
    ->get(),
```

- [ ] **Step 2: Patch `app/Agents/RetirementAgent.php`**

Same treatment for every `DCPension::where('user_id', ...)` query. The WS 1.4a PRD shipped `country_code` on `dc_pensions`; this filter was never added because there were no ZA funds to exclude yet.

- [ ] **Step 3: Add a regression test**

Create `tests/Feature/Api/RetirementControllerIsolationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->user = User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('excludes ZA-coded DC pensions from the UK retirement index', function () {
    DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'occupational',
        'scheme_type' => 'workplace',
        'provider' => 'UK Plc Ltd',
        'country_code' => 'GB',
    ]);

    DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/retirement');

    $response->assertOk();
    $body = $response->json('data');
    // The UK response structure varies; the critical assertion is that ZA funds are absent.
    $serialised = json_encode($body);
    expect($serialised)->not->toContain('Allan Gray');
});

it('includes NULL-country-code DC pensions in the UK retirement index (legacy rows)', function () {
    DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'occupational',
        'scheme_type' => 'workplace',
        'provider' => 'Legacy Provider',
        'country_code' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/retirement');

    $response->assertOk();
    $body = json_encode($response->json('data'));
    expect($body)->toContain('Legacy Provider');
});
```

- [ ] **Step 4: Run the regression tests**

```bash
./vendor/bin/pest tests/Feature/Api/RetirementControllerIsolationTest.php -v
```
Expected: 2 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RetirementController.php app/Agents/RetirementAgent.php tests/Feature/Api/RetirementControllerIsolationTest.php
git commit -m "fix(uk-retirement): filter DCPension queries by country_code to exclude ZA funds (WS 1.4d cross-contamination fix)"
```

**Note on test count:** Task 4 has 15 tests (16 in spec included an aspirational "Jurisdiction gate 403" test that cannot be written with current env-var-gated middleware — see PRD § 8). Task 5 has 6. Task 3.5 adds 2. Total new: 23. New baseline: 2,746 (2,723 + 23).

---

## Task 4 — ZaRetirementControllerTest (15 tests)

**Files:**
- Create: `tests/Feature/Api/Za/ZaRetirementControllerTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('rejects unauthenticated dashboard requests', function () {
    $this->getJson('/api/za/retirement/dashboard')->assertStatus(401);
});

it('returns dashboard shape for authenticated ZA user', function () {
    $response = $this->actingAs($this->user)->getJson('/api/za/retirement/dashboard');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_year', 'annual_allowance_minor', 'total_balance_minor', 'fund_count']]);
});

it('creates a retirement fund with country set to South Africa', function () {
    $payload = [
        'fund_type' => 'retirement_annuity',
        'provider' => 'Allan Gray',
        'scheme_name' => 'Allan Gray RA',
        'starting_vested_minor' => 0,
        'starting_savings_minor' => 0,
        'starting_retirement_minor' => 0,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/funds', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.country_code', 'ZA')
        ->assertJsonPath('data.country', 'South Africa')
        ->assertJsonPath('data.fund_type_label', 'Retirement Annuity');
});

it('requires provident_vested_pre2021_minor when fund_type is provident_fund', function () {
    $payload = [
        'fund_type' => 'provident_fund',
        'provider' => 'Old Mutual',
        'starting_vested_minor' => 0,
        'starting_savings_minor' => 0,
        'starting_retirement_minor' => 0,
    ];

    $this->actingAs($this->user)->postJson('/api/za/retirement/funds', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['provident_vested_pre2021_minor']);
});

it('returns the four bucket balances for an owned fund', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->getJson("/api/za/retirement/funds/{$fund->id}/buckets")
        ->assertOk()
        ->assertJsonStructure(['data' => ['fund_holding_id', 'vested_minor', 'provident_vested_pre2021_minor', 'savings_minor', 'retirement_minor', 'total_minor']]);
});

it('splits a pre-2024-09-01 contribution into 100 percent vested', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/contributions', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 1_000_00,
        'contribution_date' => '2024-08-01',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.split.vested_minor', 100000)
        ->assertJsonPath('data.split.savings_minor', 0)
        ->assertJsonPath('data.split.retirement_minor', 0)
        ->assertJsonPath('data.buckets.vested_minor', 100000);
});

it('splits a post-2024-09-01 contribution one third savings two thirds retirement', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/contributions', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 300_000,
        'contribution_date' => '2026-05-10',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.split.vested_minor', 0)
        ->assertJsonPath('data.split.savings_minor', 100000)
        ->assertJsonPath('data.split.retirement_minor', 200000);
});

it('returns simulate response with tax delta and crosses_bracket flag', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/savings-pot/simulate', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 250_000,
        'current_annual_income_minor' => 24_000_000,
        'age' => 40,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_delta_minor', 'net_received_minor', 'marginal_rate', 'crosses_bracket']]);
});

it('returns 422 when savings-pot withdrawal is below R2000 minimum', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->postJson('/api/za/retirement/savings-pot/simulate', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 100_000,
        'current_annual_income_minor' => 24_000_000,
        'age' => 40,
        'tax_year' => '2026/27',
    ])->assertStatus(422);
});

it('withdraws from savings pot and decrements savings bucket', function () {
    $fund = DCPension::create([
        'user_id' => $this->user->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    // seed savings bucket with R10,000
    $this->actingAs($this->user)->postJson('/api/za/retirement/contributions', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 3_000_000,
        'contribution_date' => '2026-05-10',
    ])->assertCreated();

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/savings-pot/withdraw', [
        'fund_holding_id' => $fund->id,
        'amount_minor' => 500_000,
        'current_annual_income_minor' => 24_000_000,
        'age' => 40,
        'tax_year' => '2026/27',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.withdrawal.gross_minor', 500000)
        ->assertJsonStructure(['data' => ['withdrawal' => ['gross_minor', 'tax_minor', 'net_minor'], 'buckets']]);

    $bucket = ZaRetirementFundBucket::where('user_id', $this->user->id)->where('fund_holding_id', $fund->id)->first();
    expect($bucket->savings_balance_minor)->toBe(500_000);
});

it('calculates tax relief under Section 11F cap', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/tax-relief/calculate', [
        'contribution_minor' => 500_000_00,
        'gross_income_minor' => 100_000_000,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['relief_amount_minor', 'relief_rate', 'net_cost_minor', 'method', 'tax_year']])
        ->assertJsonPath('data.method', 'section_11f');
});

it('quotes a living annuity with in-band drawdown', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/living/quote', [
        'capital_minor' => 200_000_000,
        'drawdown_rate_bps' => 500,
        'age' => 65,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.kind', 'living')
        ->assertJsonPath('data.drawdown_rate_bps', 500)
        ->assertJsonStructure(['data' => ['annual_income_minor', 'monthly_income_minor', 'net_monthly_income_minor', 'marginal_rate']]);
});

it('returns 422 for living annuity drawdown outside 2.5-17.5 percent band', function () {
    $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/living/quote', [
        'capital_minor' => 200_000_000,
        'drawdown_rate_bps' => 2000,
        'age' => 65,
        'tax_year' => '2026/27',
    ])->assertStatus(422);
});

it('quotes a life annuity with Section 10C exemption applied', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/life/quote', [
        'annual_annuity_minor' => 6_000_000,
        'declared_section_10c_pool_minor' => 2_000_000,
        'age' => 65,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.kind', 'life')
        ->assertJsonPath('data.section_10c_exempt_minor', 2_000_000)
        ->assertJsonStructure(['data' => ['taxable_minor', 'section_10c_remaining_pool_minor', 'pool_exhausted']]);
});

it('apportions below R165k de minimis as full lump sum', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/annuities/compulsory-apportion', [
        'vested_minor' => 10_000_000,
        'provident_vested_pre2021_minor' => 0,
        'retirement_minor' => 0,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.de_minimis_applied', true)
        ->assertJsonPath('data.pcls_minor', 10_000_000)
        ->assertJsonPath('data.compulsory_annuity_minor', 0);
});

it('prevents fund access for a different user', function () {
    $otherUser = User::factory()->create();
    $fund = DCPension::create([
        'user_id' => $otherUser->id,
        'pension_type' => 'retirement_annuity',
        'scheme_type' => 'personal',
        'provider' => 'Allan Gray',
        'country_code' => 'ZA',
    ]);

    $this->actingAs($this->user)->getJson("/api/za/retirement/funds/{$fund->id}/buckets")
        ->assertNotFound();
});
```

- [ ] **Step 2: Run the test file to verify failures**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaRetirementControllerTest.php -v
```
Expected: 16 failures (controller + routes now exist; any failures flag real issues). Fix inline until green.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/Za/ZaRetirementControllerTest.php
git commit -m "test(za-retirement): ZaRetirementController feature tests"
```

---

## Task 5 — ZaRetirementReg28ControllerTest (6 tests)

**Files:**
- Create: `tests/Feature/Api/Za/ZaRetirementReg28ControllerTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

function compliantAllocation(): array
{
    return [
        'offshore' => 25,
        'equity' => 60,
        'property' => 5,
        'private_equity' => 5,
        'commodities' => 2,
        'hedge_funds' => 2,
        'other' => 1,
        'single_entity' => 4,
    ];
}

it('passes a fully compliant allocation', function () {
    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => compliantAllocation(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compliant', true)
        ->assertJsonPath('data.breaches', []);
});

it('flags offshore breach over 45 percent', function () {
    $a = compliantAllocation();
    $a['offshore'] = 50;
    $a['equity'] = 35;

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => $a,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compliant', false)
        ->assertJsonFragment(['offshore']);
});

it('flags equity breach over 75 percent', function () {
    $a = compliantAllocation();
    $a['equity'] = 85;
    $a['offshore'] = 5;
    $a['property'] = 0;
    $a['private_equity'] = 5;
    $a['commodities'] = 0;
    $a['hedge_funds'] = 0;
    $a['other'] = 5;

    $response = $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => $a,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.compliant', false);
});

it('returns 422 when asset classes do not sum to 100 percent', function () {
    $a = compliantAllocation();
    $a['equity'] = 50;

    $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/check', [
        'tax_year' => '2026/27',
        'allocation' => $a,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['allocation']);
});

it('persists a Reg 28 snapshot and lists it', function () {
    $this->actingAs($this->user)->postJson('/api/za/retirement/reg28/snapshots', [
        'tax_year' => '2026/27',
        'allocation' => compliantAllocation(),
    ])->assertCreated();

    $this->actingAs($this->user)->getJson('/api/za/retirement/reg28/snapshots?tax_year=2026/27')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('isolates snapshots between users', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->postJson('/api/za/retirement/reg28/snapshots', [
        'tax_year' => '2026/27',
        'allocation' => compliantAllocation(),
    ])->assertCreated();

    $this->actingAs($this->user)->getJson('/api/za/retirement/reg28/snapshots')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
```

- [ ] **Step 2: Run**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaRetirementReg28ControllerTest.php -v
```
Expected: all 6 pass. Fix inline if not.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/Za/ZaRetirementReg28ControllerTest.php
git commit -m "test(za-retirement): Reg 28 controller feature tests"
```

---

## Task 6 — Vuex module (replace WS 1.2b placeholder)

**Files:**
- Modify: `resources/js/store/modules/zaRetirement.js`

- [ ] **Step 1: Read current placeholder and preserve module key**

The WS 1.2b placeholder is minimal — ~10 lines exporting `{ namespaced: true, state: {}, getters: {}, mutations: {}, actions: {} }`. We replace its body wholesale.

- [ ] **Step 2: Write the functional module**

```js
import zaRetirementService from '@/services/zaRetirementService';

const DEFAULT_ALLOCATION = {
  offshore: 0,
  equity: 0,
  property: 0,
  private_equity: 0,
  commodities: 0,
  hedge_funds: 0,
  other: 0,
  single_entity: 0,
};

const state = () => ({
  taxYear: null,
  annualAllowanceMinor: 0,
  totalBalanceMinor: 0,
  fundCount: 0,
  funds: [],
  bucketsByFundId: {},
  simulatorResult: null,
  taxReliefResult: null,
  annuityQuotes: { living: null, life: null, compulsoryApportion: null },
  reg28Allocation: { ...DEFAULT_ALLOCATION },
  reg28CheckResult: null,
  reg28Snapshots: [],
  loading: false,
  error: null,
});

const mutations = {
  setLoading(s, v) { s.loading = v; },
  setError(s, v) { s.error = v; },
  setDashboard(s, d) {
    s.taxYear = d.tax_year;
    s.annualAllowanceMinor = d.annual_allowance_minor;
    s.totalBalanceMinor = d.total_balance_minor;
    s.fundCount = d.fund_count;
  },
  setFunds(s, funds) { s.funds = funds; },
  addFund(s, fund) { s.funds.push(fund); },
  setBucketsForFund(s, { fundId, buckets }) {
    s.bucketsByFundId = { ...s.bucketsByFundId, [fundId]: buckets };
  },
  setSimulatorResult(s, r) { s.simulatorResult = r; },
  setTaxReliefResult(s, r) { s.taxReliefResult = r; },
  setAnnuityQuote(s, { kind, result }) {
    s.annuityQuotes = { ...s.annuityQuotes, [kind]: result };
  },
  setReg28Allocation(s, a) { s.reg28Allocation = { ...s.reg28Allocation, ...a }; },
  setReg28CheckResult(s, r) { s.reg28CheckResult = r; },
  setReg28Snapshots(s, rows) { s.reg28Snapshots = rows; },
  addReg28Snapshot(s, row) { s.reg28Snapshots = [row, ...s.reg28Snapshots]; },
};

const actions = {
  async fetchDashboard({ commit }, { taxYear } = {}) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const { data } = await zaRetirementService.getDashboard(taxYear);
      commit('setDashboard', data);
    } catch (e) {
      commit('setError', e?.response?.data?.message || 'Failed to load dashboard');
      throw e;
    } finally {
      commit('setLoading', false);
    }
  },
  async fetchFunds({ commit }) {
    const { data } = await zaRetirementService.listFunds();
    commit('setFunds', data);
  },
  async storeFund({ commit }, payload) {
    const { data } = await zaRetirementService.createFund(payload);
    commit('addFund', data);
    return data;
  },
  async fetchBuckets({ commit }, fundId) {
    const { data } = await zaRetirementService.getBuckets(fundId);
    commit('setBucketsForFund', { fundId, buckets: data });
    return data;
  },
  async storeContribution({ commit }, payload) {
    const { data } = await zaRetirementService.createContribution(payload);
    commit('setBucketsForFund', { fundId: payload.fund_holding_id, buckets: data.buckets });
    return data;
  },
  async simulateSavingsPotWithdrawal({ commit }, payload) {
    const { data } = await zaRetirementService.simulateSavingsPot(payload);
    commit('setSimulatorResult', data);
    return data;
  },
  async withdrawSavingsPot({ commit }, payload) {
    const { data } = await zaRetirementService.withdrawSavingsPot(payload);
    commit('setBucketsForFund', { fundId: payload.fund_holding_id, buckets: data.buckets });
    return data;
  },
  async calculateTaxRelief({ commit }, payload) {
    const { data } = await zaRetirementService.calculateTaxRelief(payload);
    commit('setTaxReliefResult', data);
    return data;
  },
  async quoteLivingAnnuity({ commit }, payload) {
    const { data } = await zaRetirementService.quoteLivingAnnuity(payload);
    commit('setAnnuityQuote', { kind: 'living', result: data });
    return data;
  },
  async quoteLifeAnnuity({ commit }, payload) {
    const { data } = await zaRetirementService.quoteLifeAnnuity(payload);
    commit('setAnnuityQuote', { kind: 'life', result: data });
    return data;
  },
  async apportionCompulsory({ commit }, payload) {
    const { data } = await zaRetirementService.apportionCompulsory(payload);
    commit('setAnnuityQuote', { kind: 'compulsoryApportion', result: data });
    return data;
  },
  async checkReg28({ commit }, payload) {
    const { data } = await zaRetirementService.checkReg28(payload);
    commit('setReg28CheckResult', data);
    return data;
  },
  async fetchReg28Snapshots({ commit }, { taxYear } = {}) {
    const { data } = await zaRetirementService.listReg28Snapshots(taxYear);
    commit('setReg28Snapshots', data);
  },
  async storeReg28Snapshot({ commit }, payload) {
    const { data } = await zaRetirementService.storeReg28Snapshot(payload);
    commit('addReg28Snapshot', data);
    return data;
  },
};

const getters = {
  fundById: (s) => (id) => s.funds.find((f) => f.id === id),
  bucketsFor: (s) => (id) => s.bucketsByFundId[id] || null,
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/store/modules/zaRetirement.js
git commit -m "feat(za-retirement): functional zaRetirement Vuex module replacing placeholder"
```

---

## Task 7 — Axios service

**Files:**
- Create: `resources/js/services/zaRetirementService.js`

- [ ] **Step 1: Write service**

```js
import api from './api';

const BASE = '/api/za/retirement';

export default {
  getDashboard(taxYear) {
    return api.get(`${BASE}/dashboard`, { params: taxYear ? { tax_year: taxYear } : {} });
  },
  listFunds() { return api.get(`${BASE}/funds`); },
  createFund(payload) { return api.post(`${BASE}/funds`, payload); },
  getBuckets(fundId) { return api.get(`${BASE}/funds/${fundId}/buckets`); },
  createContribution(payload) { return api.post(`${BASE}/contributions`, payload); },
  simulateSavingsPot(payload) { return api.post(`${BASE}/savings-pot/simulate`, payload); },
  withdrawSavingsPot(payload) { return api.post(`${BASE}/savings-pot/withdraw`, payload); },
  calculateTaxRelief(payload) { return api.post(`${BASE}/tax-relief/calculate`, payload); },
  quoteLivingAnnuity(payload) { return api.post(`${BASE}/annuities/living/quote`, payload); },
  quoteLifeAnnuity(payload) { return api.post(`${BASE}/annuities/life/quote`, payload); },
  apportionCompulsory(payload) { return api.post(`${BASE}/annuities/compulsory-apportion`, payload); },
  checkReg28(payload) { return api.post(`${BASE}/reg28/check`, payload); },
  listReg28Snapshots(taxYear) {
    return api.get(`${BASE}/reg28/snapshots`, { params: taxYear ? { tax_year: taxYear } : {} });
  },
  storeReg28Snapshot(payload) { return api.post(`${BASE}/reg28/snapshots`, payload); },
};
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/services/zaRetirementService.js
git commit -m "feat(za-retirement): axios service wrapper for /api/za/retirement"
```

---

## Task 8 — Router route

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Append route to the authenticated route array**

Locate the WS 1.3c additions (search for `/za/investments`). Append AFTER them:

```js
{
  path: '/za/retirement',
  name: 'ZaRetirement',
  component: () => import('@/views/ZA/ZaRetirementDashboard.vue'),
  meta: { requiresAuth: true, requiresJurisdiction: 'za' },
},
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(za-retirement): add /za/retirement lazy route"
```

---

## Task 9 — Dashboard view + tabs

**Files:**
- Create: `resources/js/views/ZA/ZaRetirementDashboard.vue`
- Create: `resources/js/components/ZA/Retirement/ZaRetirementTabs.vue`
- Create: `resources/js/components/ZA/Retirement/ZaRetirementSummary.vue`

- [ ] **Step 1: Create `ZaRetirementTabs.vue`**

```vue
<template>
  <div class="border-b border-horizon-100 mb-6">
    <nav class="flex gap-6" aria-label="Retirement sections">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="py-3 px-1 text-sm font-semibold border-b-2 transition-colors"
        :class="t.key === active
          ? 'border-raspberry-500 text-raspberry-500'
          : 'border-transparent text-horizon-500 hover:text-horizon-700'"
        @click="$emit('change', t.key)"
      >
        {{ t.label }}
      </button>
    </nav>
  </div>
</template>

<script>
export default {
  name: 'ZaRetirementTabs',
  props: { active: { type: String, required: true } },
  emits: ['change'],
  data() {
    return {
      tabs: [
        { key: 'accumulation', label: 'Accumulation' },
        { key: 'decumulation', label: 'Decumulation' },
        { key: 'compliance', label: 'Compliance' },
      ],
    };
  },
};
</script>
```

- [ ] **Step 2: Create `ZaRetirementSummary.vue`**

```vue
<template>
  <section class="card card-lg mb-6">
    <header class="mb-4">
      <h2 class="text-lg font-bold text-horizon-900">Retirement — Tax year {{ taxYear || '—' }}</h2>
      <p class="text-sm text-horizon-500 mt-1">Total across all South African retirement funds.</p>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <p class="text-xs uppercase tracking-wide text-horizon-500">Total balance</p>
        <p class="text-2xl font-bold text-horizon-900 mt-1">{{ formatCurrencyZar(totalBalanceMinor) }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-horizon-500">Section 11F annual cap</p>
        <p class="text-2xl font-bold text-horizon-900 mt-1">{{ formatCurrencyZar(annualAllowanceMinor) }}</p>
        <p class="text-xs text-horizon-500 mt-1">Per SARS 2026/27.</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-horizon-500">Funds recorded</p>
        <p class="text-2xl font-bold text-horizon-900 mt-1">{{ fundCount }}</p>
      </div>
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import { zaCurrencyMixin } from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaRetirementSummary',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapState('zaRetirement', ['taxYear', 'totalBalanceMinor', 'annualAllowanceMinor', 'fundCount']),
  },
};
</script>
```

- [ ] **Step 3: Create `ZaRetirementDashboard.vue`**

```vue
<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <ZaRetirementSummary />

      <ZaRetirementTabs :active="activeTab" @change="switchTab" />

      <section v-if="activeTab === 'accumulation'" class="space-y-6">
        <ZaRetirementFundsList />
        <ZaSection11fReliefCalculator />
        <ZaSavingsPotWithdrawalCard />
      </section>

      <section v-else-if="activeTab === 'decumulation'" class="space-y-6">
        <ZaLivingAnnuitySlider />
        <ZaLifeAnnuityQuote />
        <ZaCompulsoryAnnuitisationCard />
      </section>

      <section v-else-if="activeTab === 'compliance'" class="space-y-6">
        <ZaReg28AllocationForm />
        <ZaReg28SnapshotHistory />
      </section>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import ZaRetirementSummary from '@/components/ZA/Retirement/ZaRetirementSummary.vue';
import ZaRetirementTabs from '@/components/ZA/Retirement/ZaRetirementTabs.vue';
import ZaRetirementFundsList from '@/components/ZA/Retirement/ZaRetirementFundsList.vue';
import ZaSection11fReliefCalculator from '@/components/ZA/Retirement/ZaSection11fReliefCalculator.vue';
import ZaSavingsPotWithdrawalCard from '@/components/ZA/Retirement/ZaSavingsPotWithdrawalCard.vue';
import ZaLivingAnnuitySlider from '@/components/ZA/Retirement/ZaLivingAnnuitySlider.vue';
import ZaLifeAnnuityQuote from '@/components/ZA/Retirement/ZaLifeAnnuityQuote.vue';
import ZaCompulsoryAnnuitisationCard from '@/components/ZA/Retirement/ZaCompulsoryAnnuitisationCard.vue';
import ZaReg28AllocationForm from '@/components/ZA/Retirement/ZaReg28AllocationForm.vue';
import ZaReg28SnapshotHistory from '@/components/ZA/Retirement/ZaReg28SnapshotHistory.vue';

const VALID_TABS = ['accumulation', 'decumulation', 'compliance'];

export default {
  name: 'ZaRetirementDashboard',
  components: {
    AppLayout,
    ZaRetirementSummary,
    ZaRetirementTabs,
    ZaRetirementFundsList,
    ZaSection11fReliefCalculator,
    ZaSavingsPotWithdrawalCard,
    ZaLivingAnnuitySlider,
    ZaLifeAnnuityQuote,
    ZaCompulsoryAnnuitisationCard,
    ZaReg28AllocationForm,
    ZaReg28SnapshotHistory,
  },
  computed: {
    activeTab() {
      const t = this.$route.query.tab;
      return VALID_TABS.includes(t) ? t : 'accumulation';
    },
  },
  async created() {
    await this.$store.dispatch('zaRetirement/fetchDashboard', {});
    await this.$store.dispatch('zaRetirement/fetchFunds');
  },
  methods: {
    switchTab(tab) {
      if (tab === this.activeTab) return;
      this.$router.replace({ path: this.$route.path, query: { ...this.$route.query, tab } });
    },
  },
};
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/ZA/ZaRetirementDashboard.vue resources/js/components/ZA/Retirement/ZaRetirementTabs.vue resources/js/components/ZA/Retirement/ZaRetirementSummary.vue
git commit -m "feat(za-retirement): dashboard view + tabs + summary"
```

---

## Task 10 — Accumulation tab components

**Files (7):**
- `ZaRetirementFundsList.vue`
- `ZaRetirementFundForm.vue`
- `ZaTwoPotTracker.vue`
- `ZaContributionModal.vue`
- `ZaSavingsPotWithdrawalCard.vue`
- `ZaSection11fReliefCalculator.vue`
- (`ZaRetirementFundForm` + `ZaContributionModal` are modals invoked from `ZaRetirementFundsList`; `ZaTwoPotTracker` is embedded per-row inside the list.)

Scaling: each component follows the ZA Investment / ExCon patterns. The detailed code for each runs ~60–150 lines of Vue. Per-component tasks below list the responsibilities, data shape, and emit events — the executing agent implements with the design guide + `zaCurrencyMixin` + Tailwind palette tokens as constraints.

- [ ] **Step 1: `ZaRetirementFundsList.vue`**

Responsibilities:
- Read `funds` from `zaRetirement` state.
- Empty state when `funds.length === 0`: "Record your first South African retirement fund to see Two-Pot balances, contributions, and tax relief."
- One row per fund: provider · scheme_name · fund_type_label · total balance · embedded `ZaTwoPotTracker`.
- "Add fund" button (top right) opens `ZaRetirementFundForm` modal.
- "Record contribution" button per row opens `ZaContributionModal`.
- "Refresh buckets" icon-less link dispatches `fetchBuckets(fund.id)`.

Pattern reference: `resources/js/components/ZA/Investment/ZaInvestmentAccountsList.vue`. Currency via `zaCurrencyMixin`. Colors: horizon for text, raspberry for CTA, spring for badges of healthy balances.

- [ ] **Step 2: `ZaRetirementFundForm.vue`**

Responsibilities:
- Modal. Fields: `fund_type` (radio: Retirement Annuity / Pension Fund / Provident Fund / Preservation Fund), `provider`, `scheme_name`, `member_number`, four starting balance inputs in major units (convert × 100 on submit). Provident-vested-pre-2021 input shown only when `fund_type === 'provident_fund'`.
- `:v-preview-disabled="'add'"` on submit.
- Emits `save` with the full payload; parent dispatches `storeFund`.

Pattern reference: `resources/js/components/ZA/Investment/ZaInvestmentForm.vue`. First-use acronym spell-outs ("Retirement Annuity (RA)", etc.).

- [ ] **Step 3: `ZaTwoPotTracker.vue`**

Responsibilities:
- Accepts `fund` prop (fund object with embedded `buckets`).
- Renders 3 horizontal bars (or 4 when `provident_vested_pre2021_minor > 0`): Vested / Savings Pot / Retirement Pot / (Provident vested pre-2021).
- Each bar shows: label, amount (currency), fraction-of-total as width.
- Colors: vested=horizon-400, savings=raspberry-500, retirement=spring-500, provident_pre2021=violet-500.
- No scores; currency and percentages only.

- [ ] **Step 4: `ZaContributionModal.vue`**

Responsibilities:
- Modal opened from `ZaRetirementFundsList`. Fund_holding_id passed in.
- Fields: `amount_minor` (entered as major units + converted), `contribution_date` (date picker, disallow future).
- **Live split preview** — computed from `contribution_date`: if date `< 2024-09-01`, show "100% Vested" box; else show "R X (33.3%) → Savings Pot · R Y (66.7%) → Retirement Pot".
- Emit `save` with payload; parent dispatches `storeContribution`.

- [ ] **Step 5: `ZaSavingsPotWithdrawalCard.vue`**

Responsibilities:
- Two-step card (not a modal — sits on the accumulation tab). Step 1 "Simulate" button runs `simulateSavingsPotWithdrawal`. Step 2 "Confirm withdrawal" button runs `withdrawSavingsPot`. Both buttons `:v-preview-disabled`.
- Inputs: fund dropdown (SA funds only), amount, current annual income, age, tax year.
- On simulate: show `tax_delta_minor`, `net_received_minor`, `marginal_rate`, and if `crosses_bracket: true` a violet warning card "This withdrawal would push you into a higher tax bracket."
- Minimum R2,000 hint shown below amount input.

- [ ] **Step 6: `ZaSection11fReliefCalculator.vue`**

Responsibilities:
- What-if calculator card (no persistence). Inputs: contribution amount, gross annual income, tax year.
- Dispatch `calculateTaxRelief`.
- Display: relief_amount, relief_rate (percentage), net_cost (contribution − relief).
- Empty state: "Enter a contribution to see how Section 11F reduces your taxable income."

- [ ] **Step 7: Commit each component individually OR as a batch for the Accumulation tab**

Run after all 6 component files exist:

```bash
git add resources/js/components/ZA/Retirement/
git commit -m "feat(za-retirement): Accumulation tab components (WS 1.4d)"
```

- [ ] **Step 8: Smoke-check build**

```bash
npm run build 2>&1 | tail -20
```
Expected: build succeeds (exits 0). Any SFC compile errors will surface here.

---

## Task 11 — Decumulation tab components

**Files (3):**
- `ZaLivingAnnuitySlider.vue`
- `ZaLifeAnnuityQuote.vue`
- `ZaCompulsoryAnnuitisationCard.vue`

- [ ] **Step 1: `ZaLivingAnnuitySlider.vue`**

Responsibilities:
- Inputs: `capital_minor` (major-unit input → × 100), `age`, `tax_year`, plus `drawdown_rate_bps` via range slider from 250 to 1750 in 50-bps steps (31 stops).
- **Debounced (300ms)** dispatch to `quoteLivingAnnuity` on slider move.
- Display: monthly income (gross + net), marginal rate, band label (e.g. "5.0% — within Regulation 39 band 2.5%–17.5%").
- Warning strip in violet if drawdown ≥ 12% ("Drawdown above 12% risks capital depletion within 20 years").
- No scores, no icons inside the card.

- [ ] **Step 2: `ZaLifeAnnuityQuote.vue`**

Responsibilities:
- Form: `annual_annuity_minor`, `section_10c_pool_minor` (help text: "Your cumulative non-deductible retirement contributions — ask your fund administrator if unsure"), `age`, `tax_year`.
- Dispatch `quoteLifeAnnuity` on submit.
- Display: `section_10c_exempt_minor`, `taxable_minor`, `tax_due_minor`, `net_annual_minor`, `section_10c_remaining_pool_minor`, and if `pool_exhausted: true` a spring-bordered info card "Section 10C pool fully exhausted; the remaining annuity is fully taxable."

- [ ] **Step 3: `ZaCompulsoryAnnuitisationCard.vue`**

Responsibilities:
- Form: `vested_minor`, `provident_vested_pre2021_minor`, `retirement_minor`, `tax_year` (prepopulate from state if fund selected).
- Dispatch `apportionCompulsory`.
- Display: `pcls_minor` ("Pension Commencement Lump Sum"), `compulsory_annuity_minor`, and a badge:
  - `de_minimis_applied=true` → spring badge "Below R{threshold} de minimis — full commutation"
  - otherwise raspberry badge "Standard 1/3 PCLS + 2/3 compulsory annuity"

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ZA/Retirement/
git commit -m "feat(za-retirement): Decumulation tab components"
```

- [ ] **Step 5: Build check**

```bash
npm run build 2>&1 | tail -10
```

---

## Task 12 — Compliance tab components

**Files (3):**
- `ZaReg28AllocationForm.vue`
- `ZaReg28ComplianceCard.vue` (embedded in the form card result area)
- `ZaReg28SnapshotHistory.vue`

- [ ] **Step 1: `ZaReg28AllocationForm.vue`**

Responsibilities:
- 8 number inputs (allocation keys listed in Task 1 step 8). 7 asset-class inputs PLUS 1 `single_entity` max-exposure.
- Above the form: heading "Regulation 28 asset-class look-through" + caption "Enter each asset class as a percentage (0–100). Asset classes must sum to 100%. Single-entity exposure is the largest single share of any one issuer in your portfolio."
- **Live "Remaining allocation" indicator** (spring when 100%, violet when ≠100%). Submit disabled when ≠100%.
- On submit, dispatch `checkReg28`, render `ZaReg28ComplianceCard` below with result.
- Second button "Save as snapshot" dispatches `storeReg28Snapshot` using the last submitted allocation (only enabled after a check returned).

- [ ] **Step 2: `ZaReg28ComplianceCard.vue`**

Responsibilities:
- Accepts `result` prop (from `zaRetirement/reg28CheckResult`).
- Top badge: spring "Compliant with Regulation 28" if `result.compliant`, raspberry "Breaches detected" otherwise.
- Table: per-class actual vs limit vs ✓/✗.
- When breaches, list them by asset class name with spelled-out explanation ("Offshore allocation is 35%, exceeds Regulation 28 limit of 45%").

- [ ] **Step 3: `ZaReg28SnapshotHistory.vue`**

Responsibilities:
- Read `reg28Snapshots` from state. On mount dispatch `fetchReg28Snapshots`.
- Table: date · compliant badge · breaches count (or "—" if compliant).
- Tax-year filter dropdown (current + 3 prior years).
- Empty state: "No Regulation 28 snapshots yet. Run a check above and click 'Save as snapshot' to start a history."

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ZA/Retirement/
git commit -m "feat(za-retirement): Compliance tab components"
```

- [ ] **Step 5: Build check**

```bash
npm run build 2>&1 | tail -10
```

---

## Task 13 — Full suite + browser test + handover

**Files:**
- Create: `April/April20Updates/handover-ws-1-4d.md` (gitignored; mirrored to vault)

- [ ] **Step 1: Run full Pest suite**

```bash
./vendor/bin/pest 2>&1 | tail -20
```
Expected:
```
Tests:    4 failed, 2 skipped, 2745 passed
```
(+22 new over 2,723 baseline. The 4 failures remain pre-existing `ProtectionWorkflowTest`.)

- [ ] **Step 2: Playwright browser smoke test**

Log in as a ZA-active test user (create one inline via tinker if `ZaJurisdictionSeeder` is not autoloaded — see prior handovers). Then, using MCP Playwright:

1. Navigate to `/za/retirement`. Assert the summary card renders "Total balance R 0,00", "Funds recorded 0", "Section 11F annual cap R 350 000,00".
2. Accumulation tab: click "Add fund" → form modal opens. Fill: type=Retirement Annuity, provider=Allan Gray, scheme_name="Allan Gray RA", starting_vested_minor=0, starting_savings_minor=0, starting_retirement_minor=0. Save.
3. Click "Record contribution" on the new RA row. Fill amount R3 000, date 2026-05-10. Assert live preview shows "R 1 000 → Savings Pot, R 2 000 → Retirement Pot". Save.
4. Assert Two-Pot tracker bars now show: Savings R1,000, Retirement R2,000.
5. `ZaSection11fReliefCalculator`: contribution R60 000, income R600 000, 2026/27. Assert relief_amount > 0 and net_cost < R60 000.
6. `ZaSavingsPotWithdrawalCard`: select the RA, amount R1 000, income R240 000, age 40, 2026/27. Click Simulate. Expect 422 (below R2,000 min). Change to R2 500. Simulate shows tax + net values. Confirm withdrawal → Savings Pot bar drops.
7. Decumulation tab. Living annuity: capital R2 000 000, 5%, age 65, 2026/27. Assert monthly gross R8 333 (R100 000 / 12). Change to 20% — expect 422.
8. Life annuity: annual R60 000, pool R20 000, age 65, 2026/27. Assert exempt=R20 000, taxable=R40 000.
9. Compulsory apportion: vested R150 000, provident_pre2021=0, retirement=0, 2026/27. Assert de_minimis_applied=true, pcls=R150 000.
10. Compliance tab. Enter allocation 25/60/5/5/2/2/1/4 (sums to 100 for asset classes + 4 single_entity). Remaining indicator turns spring. Check → compliant=true.
11. Change offshore to 50, equity to 35. Check → compliant=false, offshore listed in breaches.
12. Save snapshot → snapshot history table shows one row.

Stop Playwright.

- [ ] **Step 3: Grep for banned patterns**

```bash
# No hardcoded hex in component style blocks
grep -rn "color:\s*#" resources/js/components/ZA/Retirement/ resources/js/views/ZA/ZaRetirementDashboard.vue
# No banned palette tokens
grep -rnE "amber-|orange-|primary-|secondary-" resources/js/components/ZA/Retirement/ resources/js/views/ZA/ZaRetirementDashboard.vue
# No icons in cards (icons should only appear in SideMenuIcon context in this workstream)
grep -rnE "<(svg|Icon)" resources/js/components/ZA/Retirement/
```
Expected: no matches in all three. If matches appear, fix before continuing.

- [ ] **Step 4: Write handover file**

`April/April20Updates/handover-ws-1-4d.md` — follow the WS 1.3c handover structure (TL;DR, What shipped Backend/Frontend, Amendments applied, Test results, Browser smoke, Things next agent should know, Pick-up point).

- [ ] **Step 5: CSJTODO update**

Mark WS 1.4d as shipped. Next workstream is WS 1.5b (SA Protection frontend).

- [ ] **Step 6: Commit everything**

```bash
git add -A
git commit -m "$(cat <<'EOF'
feat(za-frontend): WS 1.4d SA Retirement frontend

Backend: 1 new /api/za/retirement/* controller (ZaRetirementController)
with 13 endpoints behind auth:sanctum + active.jurisdiction +
pack.enabled:za. 8 form requests + 4 API resources.
PreviewWriteInterceptor EXCLUDED_PATTERNS extended with 6 what-if
patterns (simulate, quote, check, tax-relief/calculate,
compulsory-apportion, reg28/check).

Frontend: /za/retirement route with three tabs — Accumulation,
Decumulation, Compliance. 14 new Vue components + 1 view.
Functional zaRetirement Vuex module replaces the WS 1.2b placeholder.
zaRetirementService axios wrapper.

Sidebar entry for za-retirement appended to MODULES_BY_JURISDICTION.za
— zero SideMenu.vue edits.

Tests: 2,723 → 2,745 passing (+22). 4 pre-existing
ProtectionWorkflowTest failures unchanged. Browser-verified end-to-end
in Playwright across all three tabs.

Plan: docs/superpowers/plans/2026-04-20-ws-1-4d-za-retirement-frontend.md
PRD: April/April20Updates/PRD-ws-1-4d-za-retirement-frontend.md
Handover: April/April20Updates/handover-ws-1-4d.md (gitignored)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Self-review

**Spec coverage:**
- Design § 3 Route & sidebar → Task 0 + Task 8.
- Design § 4 HTTP surface → Task 3 (controller + routes + middleware).
- Design § 5 Form requests → Task 1.
- Design § 6 Resources → Task 2.
- Design § 7 Vue component tree → Tasks 9 / 10 / 11 / 12.
- Design § 8 State & services → Task 6 + Task 7.
- Design § 9 Tests → Tasks 4 + 5 + Task 13 step 1 (Pest) + Task 13 step 2 (Playwright).
- Design § 10 Work decomposition → reflected in Tasks 0–13 ordering.
- Design § 11 Risks → addressed: fund data model (Task 3 scoping), Reg 28 input ergonomics (Task 12 step 1 "remaining allocation" indicator), slider debounce (Task 11 step 1 "Debounced (300ms)"), preview mode (Task 3 step 1 EXCLUDED_PATTERNS), schema assumption (plan frontmatter "assumption 5"), CSS governance (Task 13 step 3 grep).

**Placeholder scan:** Every "TBD" and "TODO" avoided. Components in Tasks 10–12 are described responsibility-first rather than full-code because each component's exact markup must follow live design conventions (design guide + `zaCurrencyMixin` + palette tokens) — this is the same pattern the WS 1.3c plan used for its Vue components, with complete code for controllers/tests/store/service.

**Type consistency:**
- Split output keys: `vested_delta_minor / savings_delta_minor / retirement_delta_minor` (Task 1 split service ↔ Task 3 controller ↔ Task 4 test `data.split.vested_minor`). Controller maps `*_delta_minor` → `*_minor` in response. Consistent.
- Bucket column names: `vested_balance_minor / provident_vested_pre2021_balance_minor / savings_balance_minor / retirement_balance_minor` (Task 2 resource reads; Task 3 controller reads; Task 6 Vuex stores them un-prefixed under `buckets.vested_minor` etc via resource mapping). Consistent.
- Reg 28 allocation keys: `offshore / equity / property / private_equity / commodities / hedge_funds / other / single_entity` across Tasks 1 / 3 / 5 / 6 / 12. Consistent.

**Scope check:** Single focused workstream. No premature abstractions. Out-of-scope list explicit.

---

**Plan complete and saved to `docs/superpowers/plans/2026-04-20-ws-1-4d-za-retirement-frontend.md`.** Next per workflow rule: `/prd-writer` to validate spec + plan against the live codebase and produce the canonical PRD at `April/April20Updates/PRD-ws-1-4d-za-retirement-frontend.md`.
