# WS 1.2b — SA Frontend Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Amended — 18 April 2026 — conflicts resolved against codebase audit (explorer + architect). Changes: data-driven sidebar composition (architect #5 blocker); SideMenuSection prop bugs (`label` not `title`; wire `:expanded`/`@toggle`); jurisdiction guard boot-race fix; Money VO in controller arithmetic (ADR-005); stale `country_code` conditional removed; `active.jurisdiction` no-op TODO; TFSA acronym added to CLAUDE.md Rule 10 exception; joint ownership on ZA savings deferred to post-WS 1.7.

**Goal:** Establish the SA frontend scaffold — directory, router entries, sidebar composition, Vuex module organisation, `/api/za/*` route group — and ship the first user-facing SA surface: a TFSA dashboard + contribution tracker + ZA savings form + emergency fund gauge consuming the WS 1.2a backend.

**Architecture:** A jurisdiction-gated SPA slice. The Vuex `jurisdiction` module (shipped WS 0.5) already knows the user's active jurisdictions. We extend its `MODULES_BY_JURISDICTION` registry to add `za`, add a `/za/*` route tree lazy-loaded behind a `requiresJurisdiction: 'za'` router guard, add a ZA sidebar section to `SideMenu.vue` conditioned on `hasJurisdiction('za')`, and register six ZA Vuex modules (five thin placeholders + one functional `zaSavings`). Backend consumes the existing `pack.za.savings` / `pack.za.tfsa.tracker` / `pack.za.savings.emergency_fund` container bindings through a new `ZaSavingsController` exposed under `/api/za/savings/*` behind the `active.jurisdiction` + `pack.enabled:za` middleware stack wired by `CoreServiceProvider`.

**Tech Stack:** Laravel 10 (PHP 8.2), Vue 3, Vuex 4, Vue Router 4, Tailwind, Pest, Playwright.

**Spec sources:**
- `Plans/Implementation_Plan_v2.md` lines 418–437 (WS 1.2 amendment)
- `April/April18Updates/handover.md` § 5 (next pick), § 2 (ZA bindings surface)
- `Plans/SA_Research_and_Mapping.md` § 7 (SA Savings + TFSA product spec)
- `April/April18Updates/PRD-ws-1-2a-za-savings-tfsa.md` (backend shape this consumes)
- `fynlaDesignGuide.md` v1.4.0 — CRITICAL rule: icons are functional-only, banned on dashboard cards / detail views / chat. Side-nav icons allowed.

**Non-goals / deferrals:**
- WS 1.3c / 1.4d / 1.5b / 1.6b UI — only `zaSavings` gets real UI; others get placeholder Vuex stores so later workstreams land without re-registration churn.
- SA onboarding flow — deferred to WS 1.7.
- SA preview personas — deferred to WS 1.7.
- Replacing the hardcoded UK entries in `SideMenu.vue` with a data-driven loop — too risky. **But** the new SA section IS rendered data-driven via `v-for` over `sidebarModules`, so WS 1.3c/1.4d/1.5b/1.6b add entries without further SideMenu edits (architect audit §5).
- POPIA / FAIS compliance copy — deferred to WS 1.8 frontend.
- Joint ownership on ZA savings accounts (`joint_owner_id`) — deferred until the SA family/spouse model lands in WS 1.7 (SA personas / family_members localisation). ZA savings are individual-only in v1.
- Savings agent coordination (SA-aware aggregation) — mentioned in Implementation_Plan_v2.md WS 1.2b bullet 3 but deferred to WS 1.7 Coordination per April 18 handover § 5.
- Full Money VO adoption across the whole controller (only `dashboard()` uses it; other methods keep raw integer minor units pending a broader ADR-005 sweep).

---

## File Structure

### Backend (new)

- `app/Http/Controllers/Api/Za/ZaSavingsController.php` — 5 endpoints: dashboard, list contributions, store contribution, assess emergency fund, list/store savings accounts (TFSA-flagged).
- `app/Http/Requests/Za/Savings/StoreTfsaContributionRequest.php` — validation.
- `app/Http/Requests/Za/Savings/EmergencyFundAssessmentRequest.php` — validation.
- `app/Http/Requests/Za/Savings/StoreZaSavingsAccountRequest.php` — validation.
- `app/Http/Resources/Za/TfsaContributionResource.php` — response shape.
- `app/Http/Resources/Za/ZaSavingsAccountResource.php` — response shape (shows TFSA fields).

### Backend (modify)

- `routes/api.php` — add `/api/za/*` route group (see Task 1).

### Backend tests (new)

- `tests/Feature/Api/Za/ZaSavingsControllerTest.php` — 6+ integration tests: auth, forbidden non-ZA users, dashboard shape, contribution store/list, emergency fund assessment, savings account store with TFSA flag.

### Frontend Vuex (new)

- `resources/js/store/modules/zaSavings.js` — TFSA state + emergency fund + savings accounts.
- `resources/js/store/modules/zaInvestment.js` — placeholder (empty state, `reset` action).
- `resources/js/store/modules/zaRetirement.js` — placeholder.
- `resources/js/store/modules/zaProtection.js` — placeholder.
- `resources/js/store/modules/zaEstate.js` — placeholder.
- `resources/js/store/modules/zaExchangeControl.js` — placeholder.

### Frontend services (new)

- `resources/js/services/zaSavingsService.js` — axios wrapper for `/api/za/savings/*`.

### Frontend components (new)

- `resources/js/components/ZA/Savings/TfsaDashboard.vue` — TFSA allowance summary cards (annual + lifetime caps consumed/remaining).
- `resources/js/components/ZA/Savings/TfsaContributionTracker.vue` — recent contributions list + annual progress ring (no icons — design rule).
- `resources/js/components/ZA/Savings/TfsaContributionModal.vue` — form to record a contribution.
- `resources/js/components/ZA/Savings/ZaSavingsForm.vue` — add/edit ZA savings account with TFSA toggle, ZAR currency fixed.
- `resources/js/components/ZA/Savings/ZaEmergencyFundGauge.vue` — months-covered gauge with SA weighting (UIF, single-earner).
- `resources/js/views/ZA/ZaSavingsDashboard.vue` — page composition.

### Frontend utils (new)

- `resources/js/utils/zaCurrency.js` — ZAR formatter (`R 1 234 567.89`, space thousands, comma decimals per SA Research § 17).
- `resources/js/mixins/zaCurrencyMixin.js` — Vue mixin exposing `formatZAR`.

### Frontend (modify)

- `resources/js/store/index.js` — register the 6 `za*` modules.
- `resources/js/store/modules/jurisdiction.js` — extend `MODULES_BY_JURISDICTION` with `za` list.
- `resources/js/router/index.js` — add `/za/savings` route with `requiresJurisdiction: 'za'` meta + jurisdiction guard in `router.beforeEach`.
- `resources/js/components/SideMenu.vue` — add `v-if="hasJurisdiction('za')"` ZA section with TFSA and Emergency Fund links.

---

## Conventions locked in for later SA workstreams

These hold across WS 1.3c / 1.4d / 1.5b / 1.6b / 1.7 UI. Honour them or the scaffold leaks:

1. **Directory layout:** `resources/js/components/ZA/{Module}/` for components, `resources/js/views/ZA/Za{Module}Dashboard.vue` for page views.
2. **Routes:** `/za/{module}` for top-level page. Always include `meta: { requiresAuth: true, requiresJurisdiction: 'za' }` and `() => import(...)` lazy loader.
3. **Store modules:** one Vuex module per SA module, lowercase camelCase with `za` prefix (`zaSavings`, `zaInvestment`, …). Namespaced. Mirror field naming from the API (`annualCapMinor`, `lifetimeCapMinor`, `remainingAnnualMinor`).
4. **Services:** `resources/js/services/za{Module}Service.js`, one per ZA module. Wraps `api` (the project's axios instance).
5. **API namespacing:** all ZA endpoints live under `/api/za/*`. Always behind `['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za']` middleware.
6. **Controllers:** `app/Http/Controllers/Api/Za/Za{Module}Controller.php`, namespace `App\Http\Controllers\Api\Za`. Resolves pack bindings via `app('pack.za.{module}')` or constructor-injected contract (`SavingsEngine $savings`).
7. **Minor-unit money:** all wire values in minor units (cents for ZAR, pence for GBP). Conversion to major happens in Vue formatters only.
8. **Design system v1.4.0:** NO icons inside cards, detail views, or the TFSA dashboard body. Icons allowed only in sidebar items. Colours: raspberry CTAs, horizon text, spring success, violet warnings, savannah subtle hover.
9. **Currency:** ZAR formatted via `formatZAR` — `R 1 234 567.89` (U+00A0 narrow no-break space thousands, period decimal for parsing; display uses locale `en-ZA`).
10. **No acronyms except ISA/TFSA** — TFSA added as the second exception in CLAUDE.md Rule 10 (amended this workstream; rationale: SA Research § 7 + WS 1.2a PRD both use TFSA as the canonical term; spelling it out as "Tax-Free Savings Account" everywhere is tedious and fights SA user expectations). ISA remains the UK exception. Other SA acronyms (RA, PF, SDA, FIA, AIT, DTA) must still be spelled out on first use.

---

## Task 0: Update CLAUDE.md Rule 10 to allow TFSA acronym

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Edit Rule 10**

Change:
```
The only exception is **ISA**, which may remain abbreviated.
```
to:
```
The only exceptions are **ISA** (UK) and **TFSA** (South Africa), which may remain abbreviated. Other SA acronyms (RA, PF, PvF, SDA, FIA, AIT, DTA, QROPS, POPIA, FAIS) must still be spelled out on first use.
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: allow TFSA acronym in user-facing text (WS 1.2b policy amendment)"
```

---

## Task 1: Add `/api/za/*` route group wired to middleware

**Files:**
- Modify: `routes/api.php` (append after the last auth-gated group, before the catch-alls if any)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/Za/ZaSavingsControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('requires authentication for /api/za/savings/dashboard', function () {
    $response = $this->getJson('/api/za/savings/dashboard');
    $response->assertStatus(401);
});

it('returns 403 for users without ZA jurisdiction', function () {
    putenv('FYNLA_ACTIVE_PACKS=GB'); // ZA not enabled at installation level

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/za/savings/dashboard');
    $response->assertStatus(404)
        ->assertJson(['code' => 'PACK_NOT_FOUND']);
});
```

- [ ] **Step 2: Run test to verify they fail**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php
```
Expected: FAIL with 404 / "Route not found" because the `/api/za/*` group doesn't exist yet.

- [ ] **Step 3: Add the route group in `routes/api.php`**

Append near the end of the file (keep existing content above):

```php
/*
 |-----------------------------------------------------------------------
 | ZA Pack Routes (WS 1.2b)
 |-----------------------------------------------------------------------
 |
 | All SA-specific endpoints are grouped under /api/za/*. The
 | active.jurisdiction middleware validates pack registration and (when
 | authenticated) user entitlement against FYNLA_ACTIVE_PACKS. The
 | pack.enabled:za middleware is a belt-and-braces check that the pack
 | has booted — useful for routes that don't have {cc} in the URL.
 |
 | Contracts resolved via pack.za.* container bindings registered in
 | packs/country-za/src/Providers/ZaPackServiceProvider.php.
 */
Route::middleware(['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za'])
    ->prefix('za')
    ->as('za.')
    ->group(function () {
        Route::prefix('savings')->as('savings.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\Za\ZaSavingsController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('contributions', [\App\Http\Controllers\Api\Za\ZaSavingsController::class, 'listContributions'])
                ->name('contributions.index');
            Route::post('contributions', [\App\Http\Controllers\Api\Za\ZaSavingsController::class, 'storeContribution'])
                ->name('contributions.store');
            Route::post('emergency-fund/assess', [\App\Http\Controllers\Api\Za\ZaSavingsController::class, 'assessEmergencyFund'])
                ->name('emergency-fund.assess');
            Route::get('accounts', [\App\Http\Controllers\Api\Za\ZaSavingsController::class, 'listAccounts'])
                ->name('accounts.index');
            Route::post('accounts', [\App\Http\Controllers\Api\Za\ZaSavingsController::class, 'storeAccount'])
                ->name('accounts.store');
        });
    });
```

Note: `active.jurisdiction` middleware is a no-op on this group because there's no `{cc}` in the URL (see `core/app/Core/Http/Middleware/ActiveJurisdictionMiddleware.php` L42-46 — early return when `$countryCode === null`). Only `pack.enabled:za` is enforcing at the framework layer. Phase 0 is safe because `EnsurePackEnabled` and `ActiveJurisdictionMiddleware::userHasJurisdiction` both read the same `FYNLA_ACTIVE_PACKS` env var, so user-vs-installation entitlement are equivalent. When WS D lands the `user_jurisdictions` row-based check, this group must migrate to `/api/{cc}/*` so per-user entitlement fires. Add the machine-scannable TODO below in the route group — do NOT remove it.

```php
// TODO(WS-D): /api/za/* currently has installation-level gating only
// (pack.enabled:za). active.jurisdiction is a no-op without {cc} in the URL
// (ActiveJurisdictionMiddleware L42-46). When user_jurisdictions becomes a
// row-based check, refactor this group to /api/{cc=za}/* so per-user
// entitlement enforces. See architect audit §2 (2026-04-18).
```

- [ ] **Step 4: Run test to verify it now fails differently**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='requires authentication'
```
Expected: PASS (401 now returned by the auth middleware).

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='without ZA jurisdiction'
```
Expected: FAIL with "Class ZaSavingsController not found" — route resolves to a missing controller. This is the correct failure pointing us to Task 2.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/Api/Za/ZaSavingsControllerTest.php
git commit -m "feat(za-frontend): route group /api/za/* behind active.jurisdiction + pack.enabled:za (WS 1.2b)"
```

---

## Task 2: `ZaSavingsController` — dashboard + list contributions

**Files:**
- Create: `app/Http/Controllers/Api/Za/ZaSavingsController.php`
- Create: `app/Http/Resources/Za/TfsaContributionResource.php`
- Modify: `tests/Feature/Api/Za/ZaSavingsControllerTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Api/Za/ZaSavingsControllerTest.php`:

```php
it('returns a TFSA dashboard snapshot for a ZA-active user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/za/savings/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'tfsa' => [
                    'annual_cap_minor',
                    'lifetime_cap_minor',
                    'annual_used_minor',
                    'lifetime_used_minor',
                    'annual_remaining_minor',
                    'lifetime_remaining_minor',
                ],
                'contributions',
            ],
        ]);

    expect($response->json('data.tfsa.annual_cap_minor'))->toBe(4_600_000); // R46,000 in cents
    expect($response->json('data.tfsa.lifetime_cap_minor'))->toBe(50_000_000); // R500,000 in cents
});

it('lists the authenticated user TFSA contributions', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    app('pack.za.tfsa.tracker')->record(
        userId: $user->id,
        beneficiaryId: null,
        savingsAccountId: null,
        taxYear: '2026/27',
        amountMinor: 1_000_000, // R10,000
        contributionDate: now()->toDateString(),
    );

    $response = $this->getJson('/api/za/savings/contributions?tax_year=2026/27');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
    expect($response->json('data.0.amount_minor'))->toBe(1_000_000);
});
```

- [ ] **Step 2: Run test to verify it fails**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='dashboard|lists'
```
Expected: FAIL with "Class ZaSavingsController not found".

- [ ] **Step 3: Create the resource**

`app/Http/Resources/Za/TfsaContributionResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class TfsaContributionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_id' => $this->beneficiary_id,
            'savings_account_id' => $this->savings_account_id,
            'tax_year' => $this->tax_year,
            'amount_minor' => (int) $this->amount_minor,
            'amount_ccy' => $this->amount_ccy,
            'source_type' => $this->source_type,
            'contribution_date' => $this->contribution_date,
            'notes' => $this->notes,
        ];
    }
}
```

- [ ] **Step 4: Create the controller (first two endpoints) — using Money VO for arithmetic**

`app/Http/Controllers/Api/Za/ZaSavingsController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Resources\Za\TfsaContributionResource;
use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;
use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZaSavingsController extends Controller
{
    public function __construct(
        private readonly ZaTfsaContributionTracker $tfsaTracker,
    ) {}

    /**
     * TFSA + emergency fund dashboard snapshot.
     * Resolves SavingsEngine via the pack.za.savings binding.
     *
     * Internal arithmetic uses Money VO per ADR-005. Wire format stays
     * integer minor units (ADR-005 doesn't require JSON exposure of the VO).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $taxYear = $request->query('tax_year', $this->currentZaTaxYear());

        /** @var SavingsEngine $engine */
        $engine = app('pack.za.savings');

        $zar = Currency::ZAR();
        $annualCap = new Money($engine->getAnnualContributionCap($taxYear), $zar);
        $lifetimeCap = new Money($engine->getLifetimeContributionCap($taxYear) ?? 0, $zar);
        $annualUsed = new Money($this->tfsaTracker->sumForTaxYear($user->id, null, $taxYear), $zar);
        $lifetimeUsed = new Money($this->tfsaTracker->sumLifetime($user->id, null), $zar);

        $annualRemaining = $annualCap->minus($annualUsed);
        if ($annualRemaining->isNegative()) $annualRemaining = new Money(0, $zar);
        $lifetimeRemaining = $lifetimeCap->minus($lifetimeUsed);
        if ($lifetimeRemaining->isNegative()) $lifetimeRemaining = new Money(0, $zar);

        $contributions = ZaTfsaContribution::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->orderByDesc('contribution_date')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'tfsa' => [
                    'annual_cap_minor' => $annualCap->minor,
                    'lifetime_cap_minor' => $lifetimeCap->minor,
                    'annual_used_minor' => $annualUsed->minor,
                    'lifetime_used_minor' => $lifetimeUsed->minor,
                    'annual_remaining_minor' => $annualRemaining->minor,
                    'lifetime_remaining_minor' => $lifetimeRemaining->minor,
                ],
                'contributions' => TfsaContributionResource::collection($contributions),
            ],
        ]);
    }

    public function listContributions(Request $request): JsonResponse
    {
        $user = $request->user();
        $taxYear = $request->query('tax_year', $this->currentZaTaxYear());

        $contributions = ZaTfsaContribution::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->orderByDesc('contribution_date')
            ->get();

        return response()->json([
            'data' => TfsaContributionResource::collection($contributions),
        ]);
    }

    /**
     * SA tax year = March 1 to February 28/29. E.g. '2026/27' = 1 Mar 2026 to 28 Feb 2027.
     */
    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;
        return sprintf('%d/%d', $startYear, ($startYear + 1) % 100);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='dashboard|lists'
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaSavingsController.php app/Http/Resources/Za/TfsaContributionResource.php tests/Feature/Api/Za/ZaSavingsControllerTest.php
git commit -m "feat(za-frontend): ZaSavingsController dashboard + list contributions (WS 1.2b)"
```

---

## Task 3: Store TFSA contribution endpoint

**Files:**
- Create: `app/Http/Requests/Za/Savings/StoreTfsaContributionRequest.php`
- Modify: `app/Http/Controllers/Api/Za/ZaSavingsController.php`
- Modify: `tests/Feature/Api/Za/ZaSavingsControllerTest.php`

- [ ] **Step 1: Write the failing test**

Append to the test file:

```php
it('stores a TFSA contribution and returns updated caps', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/contributions', [
        'tax_year' => '2026/27',
        'amount_minor' => 500_000, // R5,000
        'contribution_date' => '2026-04-15',
        'source_type' => 'contribution',
        'notes' => 'Monthly top-up',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'tax_year',
                'amount_minor',
                'penalty_minor',
                'excess_minor',
                'annual_remaining_minor',
                'lifetime_remaining_minor',
            ],
        ]);

    expect($response->json('data.amount_minor'))->toBe(500_000);
    expect($response->json('data.penalty_minor'))->toBe(0);
    expect($response->json('data.annual_remaining_minor'))->toBe(4_600_000 - 500_000);
});

it('flags over-contribution penalty when annual cap breached', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/contributions', [
        'tax_year' => '2026/27',
        'amount_minor' => 5_000_000, // R50,000 — breaches R46,000 annual
        'contribution_date' => '2026-04-15',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.excess_minor'))->toBe(400_000); // R4,000 excess
    expect($response->json('data.penalty_minor'))->toBe(160_000); // 40% of R4,000
    expect($response->json('data.breached_cap'))->toBe('annual');
});
```

- [ ] **Step 2: Run test to verify it fails**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='stores|flags over'
```
Expected: FAIL — storeContribution method doesn't exist.

- [ ] **Step 3: Create the request**

`app/Http/Requests/Za/Savings/StoreTfsaContributionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Savings;

use Illuminate\Foundation\Http\FormRequest;

class StoreTfsaContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'contribution_date' => ['required', 'date_format:Y-m-d'],
            'source_type' => ['sometimes', 'in:contribution,transfer_in'],
            'beneficiary_id' => ['nullable', 'integer', 'exists:family_members,id'],
            'savings_account_id' => ['nullable', 'integer', 'exists:savings_accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 4: Add the storeContribution method**

In `ZaSavingsController.php`, update the constructor and add the method:

```php
use App\Http\Requests\Za\Savings\StoreTfsaContributionRequest;
use Fynla\Core\Contracts\SavingsEngine;

// ... add to existing controller:

public function storeContribution(StoreTfsaContributionRequest $request): JsonResponse
{
    $user = $request->user();
    $data = $request->validated();
    $taxYear = $data['tax_year'];

    /** @var SavingsEngine $engine */
    $engine = app('pack.za.savings');

    // Pre-record: compute remaining caps for the penalty/excess calc.
    $annualPrior = $this->tfsaTracker->sumForTaxYear($user->id, $data['beneficiary_id'] ?? null, $taxYear);
    $lifetimePrior = $this->tfsaTracker->sumLifetime($user->id, $data['beneficiary_id'] ?? null);

    $penaltyAssessment = $engine->calculateTaxFreeWrapperPenalty(
        contributionMinor: $data['amount_minor'],
        annualPriorMinor: $annualPrior,
        lifetimePriorMinor: $lifetimePrior,
        taxYear: $taxYear,
    );

    $id = $this->tfsaTracker->record(
        userId: $user->id,
        beneficiaryId: $data['beneficiary_id'] ?? null,
        savingsAccountId: $data['savings_account_id'] ?? null,
        taxYear: $taxYear,
        amountMinor: $data['amount_minor'],
        contributionDate: $data['contribution_date'],
        sourceType: $data['source_type'] ?? 'contribution',
        notes: $data['notes'] ?? null,
    );

    return response()->json([
        'data' => [
            'id' => $id,
            'tax_year' => $taxYear,
            'amount_minor' => $data['amount_minor'],
            'penalty_minor' => $penaltyAssessment['penalty_minor'],
            'excess_minor' => $penaltyAssessment['excess_minor'],
            'breached_cap' => $penaltyAssessment['breached_cap'],
            'annual_remaining_minor' => $penaltyAssessment['annual_remaining_minor'],
            'lifetime_remaining_minor' => $penaltyAssessment['lifetime_remaining_minor'],
        ],
    ], 201);
}
```

- [ ] **Step 5: Run tests**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php
```
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaSavingsController.php app/Http/Requests/Za/Savings/StoreTfsaContributionRequest.php tests/Feature/Api/Za/ZaSavingsControllerTest.php
git commit -m "feat(za-frontend): POST /api/za/savings/contributions with penalty assessment (WS 1.2b)"
```

---

## Task 4: Emergency fund assessment endpoint

**Files:**
- Create: `app/Http/Requests/Za/Savings/EmergencyFundAssessmentRequest.php`
- Modify: `app/Http/Controllers/Api/Za/ZaSavingsController.php`
- Modify: `tests/Feature/Api/Za/ZaSavingsControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('assesses emergency fund adequacy with SA weighting', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/emergency-fund/assess', [
        'current_balance_minor' => 3_000_000, // R30,000
        'essential_monthly_expenditure_minor' => 1_500_000, // R15,000
        'income_stability' => 'stable',
        'household_income_earners' => 1, // single earner → 6 months
        'uif_eligible' => false, // +1 month
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'status',
                'shortfall_minor',
                'months_covered',
                'target_months',
                'target_minor',
                'weighting_reason',
            ],
        ]);

    // Single earner + UIF-ineligible → 7 months → R105,000 target
    expect($response->json('data.target_months'))->toBe(7);
    expect($response->json('data.target_minor'))->toBe(1_500_000 * 7);
    expect($response->json('data.status'))->toBe('shortfall');
    expect($response->json('data.months_covered'))->toBe(2.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='emergency'
```
Expected: FAIL.

- [ ] **Step 3: Create the request**

`app/Http/Requests/Za/Savings/EmergencyFundAssessmentRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Savings;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyFundAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_balance_minor' => ['required', 'integer', 'min:0'],
            'essential_monthly_expenditure_minor' => ['required', 'integer', 'min:0'],
            'income_stability' => ['required', 'in:stable,variable,volatile'],
            'household_income_earners' => ['required', 'integer', 'min:1', 'max:10'],
            'uif_eligible' => ['required', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Add the method**

```php
use App\Http\Requests\Za\Savings\EmergencyFundAssessmentRequest;
use Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator;

// add via DI: update constructor to accept ZaEmergencyFundCalculator
public function __construct(
    private readonly ZaTfsaContributionTracker $tfsaTracker,
    private readonly ZaEmergencyFundCalculator $emergencyFund,
) {}

public function assessEmergencyFund(EmergencyFundAssessmentRequest $request): JsonResponse
{
    $data = $request->validated();

    $assessment = $this->emergencyFund->assess(
        currentBalanceMinor: $data['current_balance_minor'],
        essentialMonthlyExpenditureMinor: $data['essential_monthly_expenditure_minor'],
        incomeStability: $data['income_stability'],
        householdIncomeEarners: $data['household_income_earners'],
        uifEligible: $data['uif_eligible'],
    );

    return response()->json(['data' => $assessment]);
}
```

- [ ] **Step 5: Run tests**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php
```
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaSavingsController.php app/Http/Requests/Za/Savings/EmergencyFundAssessmentRequest.php tests/Feature/Api/Za/ZaSavingsControllerTest.php
git commit -m "feat(za-frontend): emergency fund assessment endpoint (WS 1.2b)"
```

---

## Task 5: ZA savings account list + store (TFSA flag)

**Files:**
- Create: `app/Http/Requests/Za/Savings/StoreZaSavingsAccountRequest.php`
- Create: `app/Http/Resources/Za/ZaSavingsAccountResource.php`
- Modify: `app/Http/Controllers/Api/Za/ZaSavingsController.php`
- Modify: `tests/Feature/Api/Za/ZaSavingsControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('stores a ZA savings account with TFSA flag', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/za/savings/accounts', [
        'bank_name' => 'Investec',
        'account_name' => 'TFSA — Investec Cash',
        'account_type' => 'tfsa',
        'current_balance' => 12_500.50,
        'interest_rate' => 7.5,
        'is_tfsa' => true,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.is_tfsa'))->toBeTrue();
    expect($response->json('data.bank_name'))->toBe('Investec');

    $this->assertDatabaseHas('savings_accounts', [
        'user_id' => $user->id,
        'is_tfsa' => true,
        'bank_name' => 'Investec',
    ]);
});

it('lists the authenticated user ZA savings accounts', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    \App\Models\SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'bank_name' => 'Standard Bank',
        'is_tfsa' => false,
    ]);

    $response = $this->getJson('/api/za/savings/accounts');
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});
```

- [ ] **Step 2: Run test to verify it fails**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php --filter='stores a ZA|lists the authenticated'
```
Expected: FAIL — endpoints missing.

- [ ] **Step 3: Create the resource**

`app/Http/Resources/Za/ZaSavingsAccountResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class ZaSavingsAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'current_balance' => (float) $this->current_balance,
            'interest_rate' => (float) $this->interest_rate,
            'is_tfsa' => (bool) $this->is_tfsa,
            'tfsa_subscription_year' => $this->tfsa_subscription_year,
            'tfsa_subscription_amount_minor' => $this->tfsa_subscription_amount_minor,
            'tfsa_lifetime_contributed_minor' => $this->tfsa_lifetime_contributed_minor,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'country_code' => 'ZA',
        ];
    }
}
```

- [ ] **Step 4: Create the form request**

`app/Http/Requests/Za/Savings/StoreZaSavingsAccountRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Savings;

use Illuminate\Foundation\Http\FormRequest;

class StoreZaSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'in:current,savings,tfsa,notice,money_market,fixed_deposit'],
            'current_balance' => ['required', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_tfsa' => ['sometimes', 'boolean'],
            'tfsa_subscription_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'ownership_type' => ['sometimes', 'in:individual,joint,tenants_in_common,trust'],
            'ownership_percentage' => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }
}
```

- [ ] **Step 5: Add methods to controller**

```php
use App\Http\Requests\Za\Savings\StoreZaSavingsAccountRequest;
use App\Http\Resources\Za\ZaSavingsAccountResource;
use App\Models\SavingsAccount;

public function listAccounts(Request $request): JsonResponse
{
    $user = $request->user();
    $accounts = SavingsAccount::query()
        ->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('joint_owner_id', $user->id);
        })
        ->orderBy('bank_name')
        ->get();

    return response()->json([
        'data' => ZaSavingsAccountResource::collection($accounts),
    ]);
}

public function storeAccount(StoreZaSavingsAccountRequest $request): JsonResponse
{
    $data = $request->validated();
    $data['user_id'] = $request->user()->id;
    $data['country_code'] = 'ZA'; // confirmed: column exists per migration 2026_04_17_300001
    $data['ownership_type'] = $data['ownership_type'] ?? 'individual';
    $data['currency'] = 'ZAR';

    // Note: joint_owner_id deliberately not accepted. ZA savings are
    // individual-only in v1; SA family/spouse model ships in WS 1.7.
    // JurisdictionDetectionObserver will fire on create (country_code = ZA)
    // but is idempotent for users already ZA-active.

    $account = SavingsAccount::create($data);

    return response()->json([
        'data' => new ZaSavingsAccountResource($account),
    ], 201);
}
```

- [ ] **Step 6: Run tests**

```
./vendor/bin/pest tests/Feature/Api/Za/ZaSavingsControllerTest.php
```
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaSavingsController.php app/Http/Requests/Za/Savings/StoreZaSavingsAccountRequest.php app/Http/Resources/Za/ZaSavingsAccountResource.php tests/Feature/Api/Za/ZaSavingsControllerTest.php
git commit -m "feat(za-frontend): ZA savings account list + store (WS 1.2b)"
```

---

## Task 6: Extend `MODULES_BY_JURISDICTION` with sidebar config objects

**Files:**
- Modify: `resources/js/store/modules/jurisdiction.js`

**Why change shape:** the sidebar must render ZA entries data-driven (architect audit §5). Bare strings aren't enough — each entry needs label, route, icon. This is the foundation pattern WS 1.3c/1.4d/1.5b/1.6b will extend by adding one line each. UK entries stay bare strings (hardcoded UK sidebar isn't refactored in this plan).

- [ ] **Step 1: Update the registry shape**

In `jurisdiction.js`, replace `MODULES_BY_JURISDICTION` with:

```js
/**
 * Module registry, keyed by jurisdiction code. UK entries are bare
 * module-name strings (UK sidebar composition is hardcoded today).
 * ZA entries carry the full sidebar config needed by the data-driven
 * ZA section in SideMenu.vue — added this way from the start so later
 * SA workstreams (WS 1.3c / 1.4d / 1.5b / 1.6b) only need to append
 * one entry here and their sidebar item appears.
 *
 * ZA entry shape: { key, label, route, icon, section }
 *   - key: stable identifier, prefix 'za-' to avoid UK name collision
 *   - label: user-facing label (British spelling, TFSA abbreviation allowed)
 *   - route: absolute SPA path under /za/*
 *   - icon: name from resources/js/components/SideMenuIcon.vue allow-list
 *   - section: section key (must exist in SideMenu.vue expandedSections)
 */
const MODULES_BY_JURISDICTION = {
  gb: [
    'protection',
    'savings',
    'investment',
    'retirement',
    'estate',
    'goals',
    'coordination',
  ],
  za: [
    {
      key: 'za-savings',
      label: 'Savings (TFSA)',
      route: '/za/savings',
      icon: 'banknotes',
      section: 'zaSection',
    },
    // WS 1.3c will add za-investment here
    // WS 1.3c will add za-exchange-control here
    // WS 1.4d will add za-retirement here
    // WS 1.5b will add za-protection here
    // WS 1.6b will add za-estate here
  ],
};
```

- [ ] **Step 2: Add a `zaModules` getter**

Next to the existing `sidebarModules` getter in `jurisdiction.js`, add:

```js
/**
 * The ZA sidebar-config objects for the current user. Empty array when
 * the user isn't ZA-active. Consumed by SideMenu.vue to render the
 * ZA section via v-for (architect audit §5).
 */
zaModules: (state) => {
  if (!state.activeJurisdictions.includes('za')) return [];
  const entries = MODULES_BY_JURISDICTION.za || [];
  return entries.filter((e) => typeof e === 'object');
},
```

- [ ] **Step 3: `sidebarModules` getter fix — handle object entries**

The existing `sidebarModules` getter pushes raw entries into a deduped array. With ZA entries now being objects, the Set-based dedupe silently breaks (object identity !== name). Update the loop to normalise:

```js
sidebarModules: (state) => {
  const modules = [];
  for (const code of state.activeJurisdictions) {
    const jurisdictionModules = MODULES_BY_JURISDICTION[code];
    if (jurisdictionModules) {
      for (const entry of jurisdictionModules) {
        modules.push(typeof entry === 'string' ? entry : entry.key);
      }
    }
  }
  if (state.crossBorder) {
    modules.push(...CROSS_BORDER_MODULES);
  }
  return [...new Set(modules)];
},
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/store/modules/jurisdiction.js
git commit -m "feat(za-frontend): ZA module registry with sidebar config + zaModules getter (WS 1.2b)"
```

---

## Task 7: ZA Vuex modules — scaffolds + functional `zaSavings`

**Files:**
- Create: `resources/js/store/modules/zaSavings.js`
- Create: `resources/js/store/modules/zaInvestment.js`
- Create: `resources/js/store/modules/zaRetirement.js`
- Create: `resources/js/store/modules/zaProtection.js`
- Create: `resources/js/store/modules/zaEstate.js`
- Create: `resources/js/store/modules/zaExchangeControl.js`
- Modify: `resources/js/store/index.js`

- [ ] **Step 1: Create the placeholder template**

Each placeholder follows the same shape. Create `resources/js/store/modules/zaInvestment.js`:

```js
/**
 * ZA Investment store module. Placeholder until WS 1.3c frontend lands.
 * Shape mirrors zaSavings so parallel workstreams (1.3c, 1.4d, 1.5b, 1.6b)
 * can extend without breaking the registration contract in store/index.js.
 */
const state = () => ({
  loaded: false,
  error: null,
});

const getters = {
  isLoaded: (state) => state.loaded,
};

const actions = {
  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  RESET(state) {
    state.loaded = false;
    state.error = null;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
```

- [ ] **Step 2: Create the other four placeholders**

Copy the same file for `zaRetirement.js`, `zaProtection.js`, `zaEstate.js`, `zaExchangeControl.js`. Update the doc header on each to name the module. No other differences.

- [ ] **Step 3: Create the real `zaSavings.js`**

`resources/js/store/modules/zaSavings.js`:

```js
import zaSavingsService from '@/services/zaSavingsService';

/**
 * ZA Savings store: TFSA caps, contributions list, emergency fund
 * assessment, and ZA savings accounts (user_id = primary owner, joint
 * supported via joint_owner_id just like UK savings).
 *
 * Pattern established in WS 1.2b; later SA modules follow the same shape
 * (state, service, fetch/store actions keyed by API endpoint).
 */
const state = () => ({
  taxYear: null,
  tfsa: {
    annualCapMinor: 0,
    lifetimeCapMinor: 0,
    annualUsedMinor: 0,
    lifetimeUsedMinor: 0,
    annualRemainingMinor: 0,
    lifetimeRemainingMinor: 0,
  },
  contributions: [],
  accounts: [],
  emergencyFund: null, // assessment payload, populated on demand
  loading: false,
  error: null,
});

const getters = {
  taxYear: (s) => s.taxYear,
  tfsa: (s) => s.tfsa,
  contributions: (s) => s.contributions,
  accounts: (s) => s.accounts,
  tfsaAccounts: (s) => s.accounts.filter((a) => a.is_tfsa),
  annualRemainingMinor: (s) => s.tfsa.annualRemainingMinor,
  lifetimeRemainingMinor: (s) => s.tfsa.lifetimeRemainingMinor,
  emergencyFund: (s) => s.emergencyFund,
  isLoading: (s) => s.loading,
  error: (s) => s.error,
};

const actions = {
  async fetchDashboard({ commit }, taxYear = null) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const payload = await zaSavingsService.getDashboard(taxYear);
      commit('SET_DASHBOARD', payload.data);
    } catch (err) {
      commit('SET_ERROR', err.response?.data?.message || err.message);
      throw err;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async storeContribution({ dispatch }, data) {
    const payload = await zaSavingsService.storeContribution(data);
    await dispatch('fetchDashboard', data.tax_year);
    return payload.data;
  },

  async fetchAccounts({ commit }) {
    commit('SET_LOADING', true);
    try {
      const payload = await zaSavingsService.listAccounts();
      commit('SET_ACCOUNTS', payload.data);
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async storeAccount({ dispatch }, data) {
    const payload = await zaSavingsService.storeAccount(data);
    await dispatch('fetchAccounts');
    return payload.data;
  },

  async assessEmergencyFund({ commit }, data) {
    const payload = await zaSavingsService.assessEmergencyFund(data);
    commit('SET_EMERGENCY_FUND', payload.data);
    return payload.data;
  },

  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  SET_DASHBOARD(state, data) {
    state.taxYear = data.tax_year;
    state.tfsa = {
      annualCapMinor: data.tfsa.annual_cap_minor,
      lifetimeCapMinor: data.tfsa.lifetime_cap_minor,
      annualUsedMinor: data.tfsa.annual_used_minor,
      lifetimeUsedMinor: data.tfsa.lifetime_used_minor,
      annualRemainingMinor: data.tfsa.annual_remaining_minor,
      lifetimeRemainingMinor: data.tfsa.lifetime_remaining_minor,
    };
    state.contributions = data.contributions || [];
  },
  SET_ACCOUNTS(state, accounts) {
    state.accounts = accounts;
  },
  SET_EMERGENCY_FUND(state, payload) {
    state.emergencyFund = payload;
  },
  SET_LOADING(state, v) {
    state.loading = v;
  },
  SET_ERROR(state, e) {
    state.error = e;
  },
  RESET(state) {
    state.taxYear = null;
    state.tfsa = {
      annualCapMinor: 0,
      lifetimeCapMinor: 0,
      annualUsedMinor: 0,
      lifetimeUsedMinor: 0,
      annualRemainingMinor: 0,
      lifetimeRemainingMinor: 0,
    };
    state.contributions = [];
    state.accounts = [];
    state.emergencyFund = null;
    state.loading = false;
    state.error = null;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
```

- [ ] **Step 4: Register the six modules in the root store**

Modify `resources/js/store/index.js` — add imports and register in the `modules: {}` block alphabetically (after `whatIf`, before the existing `jurisdiction` entry is fine since they sort near each other):

```js
import zaSavings from './modules/zaSavings';
import zaInvestment from './modules/zaInvestment';
import zaRetirement from './modules/zaRetirement';
import zaProtection from './modules/zaProtection';
import zaEstate from './modules/zaEstate';
import zaExchangeControl from './modules/zaExchangeControl';

// ... in the modules block:
modules: {
  // ... existing modules ...
  jurisdiction,
  zaSavings,
  zaInvestment,
  zaRetirement,
  zaProtection,
  zaEstate,
  zaExchangeControl,
},
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/store/modules/zaSavings.js resources/js/store/modules/zaInvestment.js resources/js/store/modules/zaRetirement.js resources/js/store/modules/zaProtection.js resources/js/store/modules/zaEstate.js resources/js/store/modules/zaExchangeControl.js resources/js/store/index.js
git commit -m "feat(za-frontend): ZA Vuex modules (zaSavings functional, 5 placeholders) (WS 1.2b)"
```

---

## Task 8: `zaSavingsService` API wrapper

**Files:**
- Create: `resources/js/services/zaSavingsService.js`

- [ ] **Step 1: Create the service**

```js
import api from './api';

/**
 * WS 1.2b — API wrapper for /api/za/savings/*. All wire values in minor units.
 * Resolves pack.za.savings / pack.za.tfsa.tracker / pack.za.savings.emergency_fund
 * bindings server-side.
 */
const zaSavingsService = {
  async getDashboard(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const response = await api.get('/za/savings/dashboard', { params });
    return response.data;
  },

  async listContributions(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const response = await api.get('/za/savings/contributions', { params });
    return response.data;
  },

  async storeContribution(data) {
    const response = await api.post('/za/savings/contributions', data);
    return response.data;
  },

  async assessEmergencyFund(data) {
    const response = await api.post('/za/savings/emergency-fund/assess', data);
    return response.data;
  },

  async listAccounts() {
    const response = await api.get('/za/savings/accounts');
    return response.data;
  },

  async storeAccount(data) {
    const response = await api.post('/za/savings/accounts', data);
    return response.data;
  },
};

export default zaSavingsService;
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/services/zaSavingsService.js
git commit -m "feat(za-frontend): zaSavingsService axios wrapper (WS 1.2b)"
```

---

## Task 9: ZAR formatter util + mixin

**Files:**
- Create: `resources/js/utils/zaCurrency.js`
- Create: `resources/js/mixins/zaCurrencyMixin.js`

- [ ] **Step 1: Create the util**

```js
/**
 * ZAR formatter. SA Research § 17: 'R 1 234 567.89' — narrow no-break
 * space thousands, period decimal. Renders with en-ZA locale where
 * supported; falls back to explicit formatting when the browser returns
 * different group characters (older Safari).
 */

export function formatZAR(value, { showDecimals = true } = {}) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return 'R —';
  }
  const n = Number(value);
  const opts = {
    minimumFractionDigits: showDecimals ? 2 : 0,
    maximumFractionDigits: showDecimals ? 2 : 0,
    useGrouping: true,
  };
  try {
    const formatted = new Intl.NumberFormat('en-ZA', opts).format(n);
    // Normalise any non-breaking / narrow-no-break space variants to
    // U+00A0 for consistency with visual tests.
    return `R\u00a0${formatted.replace(/[\s\u202f]/g, '\u00a0')}`;
  } catch (e) {
    // Fallback: manual thousands grouping
    const [int, frac] = n.toFixed(showDecimals ? 2 : 0).split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
    return frac ? `R\u00a0${grouped}.${frac}` : `R\u00a0${grouped}`;
  }
}

export function formatZARMinor(valueMinor, opts = {}) {
  return formatZAR((Number(valueMinor) || 0) / 100, opts);
}

export function toMinorZAR(valueMajor) {
  if (valueMajor === null || valueMajor === undefined || valueMajor === '') return 0;
  return Math.round(Number(valueMajor) * 100);
}
```

- [ ] **Step 2: Create the mixin**

```js
import { formatZAR, formatZARMinor, toMinorZAR } from '@/utils/zaCurrency';

export default {
  methods: {
    formatZAR,
    formatZARMinor,
    toMinorZAR,
  },
};
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/utils/zaCurrency.js resources/js/mixins/zaCurrencyMixin.js
git commit -m "feat(za-frontend): ZAR formatter util + mixin (WS 1.2b)"
```

---

## Task 10: Router entries + jurisdiction guard

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Add lazy imports**

Near the top where other lazy imports live (after the Authenticated pages block):

```js
// ZA pages — lazy-loaded; only fetched when user has 'za' jurisdiction (see guard below).
const ZaSavingsDashboard = () => import('@/views/ZA/ZaSavingsDashboard.vue');
```

- [ ] **Step 2: Register the route**

Inside the authenticated routes section (find the block around `/savings` to co-locate), add:

```js
{
  path: '/za/savings',
  name: 'za-savings',
  component: ZaSavingsDashboard,
  meta: {
    requiresAuth: true,
    requiresJurisdiction: 'za',
  },
},
```

- [ ] **Step 3: Add jurisdiction guard — with boot-race fix**

The guard reads `jurisdiction/activeJurisdictions` from the Vuex store. On a **hard reload directly to `/za/savings`**, persisted state restores `auth.user` (so `isAuthenticated` is true) but the jurisdiction store is NOT persisted — it's hydrated by `auth/fetchUser` via `jurisdiction/hydrateFromSession`. If the guard runs before `fetchUser` resolves, `activeJurisdictions` is `[]` and the guard incorrectly redirects a valid ZA user to `/dashboard`.

Fix: **await** `auth/fetchUser` when the store says the user is authenticated but jurisdiction state hasn't been hydrated yet (empty array). This is safe — `fetchUser` is idempotent.

In the existing `router.beforeEach(async (to, from, next) => { ... })` block — **after** the `requiresAuth` check (so unauthenticated users never hit the jurisdiction check) — add:

```js
// Jurisdiction guard — per-route `meta.requiresJurisdiction` must be in
// the user's active jurisdictions. Uses `to.matched.some` to handle
// nested route inheritance (Vue Router doesn't inherit meta by default).
//
// Boot-race handling: on hard reload, persistedState restores auth.user
// but NOT the jurisdiction store, which is hydrated by auth/fetchUser.
// If isAuthenticated but jurisdictions haven't hydrated yet, await
// fetchUser before gating (it's idempotent; safe to call again).
const requiredJurisdiction = to.matched
  .map((r) => r.meta?.requiresJurisdiction)
  .find((j) => !!j);
if (requiredJurisdiction) {
  let active = store.getters['jurisdiction/activeJurisdictions'] || [];
  const authed = store.getters['auth/isAuthenticated'];
  if (authed && active.length === 0) {
    try {
      await store.dispatch('auth/fetchUser');
      active = store.getters['jurisdiction/activeJurisdictions'] || [];
    } catch {
      // fetchUser failed — treat as unauthenticated, let the requiresAuth
      // re-check (or next navigation) handle it. Fall through to the
      // dashboard redirect below.
    }
  }
  if (!active.includes(requiredJurisdiction)) {
    return next({ path: '/dashboard' });
  }
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(za-frontend): /za/savings route with jurisdiction guard (WS 1.2b)"
```

---

## Task 11: Sidebar ZA section — data-driven, gated on `zaModules` getter

**Files:**
- Modify: `resources/js/components/SideMenu.vue`

**Why data-driven:** architect audit §5 — if we hardcode a `<SideMenuSection v-if="hasZa">` block here, WS 1.3c / 1.4d / 1.5b / 1.6b will each add a parallel `v-if` block, and by WS 1.6b the sidebar has five duplicated conditionals against a store that already knows how to compose itself. Render the ZA section via `v-for` over `jurisdiction/zaModules` from the start. UK entries stay hardcoded — that refactor is out of scope.

Important facts about `SideMenu.vue` to honour:
- It's **Composition API** (`setup()` returning refs). Use `computed()` / `storeToRefs`-style patterns, NOT Options API.
- `SideMenuSection` takes `label` (not `title`), `collapsed`, `expanded`, and emits `toggle`.
- Existing sections have a section key (`cashManagement`, `finances`, `family`, `planning`, `account`, `advisorPanel`, `adminPanel`). `expandedSections` is keyed by those. `activeSectionKey` maps the current route back to a key so the active section auto-expands. Add `zaSection` to both.

- [ ] **Step 1: Add `zaModules` computed in setup()**

In `SideMenu.vue` `setup()`, alongside the other `computed()` declarations that read from the store, add:

```js
const zaModules = computed(() => store.getters['jurisdiction/zaModules']);
const hasZa = computed(() => zaModules.value.length > 0);
```

Return both from `setup()` (add to the returned object).

- [ ] **Step 2: Extend `activeSectionKey` to map /za/* to zaSection**

In the existing `activeSectionKey` computed (around L390), add a branch BEFORE the final `return null`:

```js
if (path.startsWith('/za/')) {
  return 'zaSection';
}
```

This ensures the ZA section auto-expands when the user is on a `/za/*` route.

- [ ] **Step 3: Ensure `zaSection` is a known expanded-sections key**

The `expandedSections` ref is a plain object. No schema — any key works. No change needed here; `toggleSection('zaSection')` and `isSectionExpanded('zaSection')` just work. But verify the `STORAGE_KEY` persistence doesn't choke on an unknown key (it shouldn't — it's a plain JSON dict).

- [ ] **Step 4: Add the ZA section to the template**

Inside the `<nav>` / sections area, after the existing sections (a good location is after `family` / before `account`, but anywhere inside the authenticated sections block works), add:

```vue
<!-- ZA section — data-driven from jurisdiction store so later SA UI
     workstreams (1.3c, 1.4d, 1.5b, 1.6b) only extend the registry. -->
<SideMenuSection
  v-if="hasZa"
  label="South Africa"
  :collapsed="effectiveCollapsed"
  :expanded="isSectionExpanded('zaSection')"
  @toggle="toggleSection('zaSection')"
>
  <SideMenuItem
    v-for="mod in zaModules"
    :key="mod.key"
    :icon="mod.icon"
    :label="mod.label"
    :to="{ path: mod.route }"
    :collapsed="effectiveCollapsed"
    :active="currentPath.startsWith(mod.route)"
    :active-colour="currentStage ? stageColour : ''"
    @navigate="closeMobile"
  />
</SideMenuSection>
```

Notes:
- `label` (not `title`) is the required prop on `SideMenuSection` (see `SideMenuSection.vue` L38).
- `:expanded` and `@toggle` are mandatory — omitting either leaves the section stuck collapsed or unresponsive to click.
- `icon="banknotes"` is allow-listed in `SideMenuIcon.vue` (and already in use at SideMenu.vue line 63 for "Bank Accounts").
- The `v-for` loop means WS 1.3c just appends an entry to `MODULES_BY_JURISDICTION.za` — zero changes to this file.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/SideMenu.vue
git commit -m "feat(za-frontend): data-driven ZA sidebar section via jurisdiction/zaModules (WS 1.2b)"
```

---

## Task 12: `TfsaDashboard.vue` — main dashboard component

**Files:**
- Create: `resources/js/components/ZA/Savings/TfsaDashboard.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <section class="space-y-6">
    <!-- Header (no icons — design rule) -->
    <header class="flex items-end justify-between">
      <div>
        <h1 class="text-3xl font-black text-horizon-700">Tax-Free Savings</h1>
        <p class="text-sm text-horizon-500 mt-1">
          Tax year {{ taxYear || '2026/27' }} — annual cap R46&nbsp;000, lifetime cap R500&nbsp;000.
        </p>
      </div>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg transition-colors"
        @click="showContributionModal = true"
      >
        Record contribution
      </button>
    </header>

    <!-- Allowance cards — no icons inside cards per design v1.4.0 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="card p-6">
        <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Annual allowance</div>
        <div class="text-3xl font-black text-horizon-700 mt-2">
          {{ formatZARMinor(tfsa.annualRemainingMinor) }} remaining
        </div>
        <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
          <div
            class="h-full bg-spring-500 transition-all duration-500"
            :style="{ width: annualProgressPercent + '%' }"
          />
        </div>
        <div class="mt-2 text-xs text-horizon-400">
          {{ formatZARMinor(tfsa.annualUsedMinor) }} used of {{ formatZARMinor(tfsa.annualCapMinor) }}
        </div>
      </div>

      <div class="card p-6">
        <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Lifetime allowance</div>
        <div class="text-3xl font-black text-horizon-700 mt-2">
          {{ formatZARMinor(tfsa.lifetimeRemainingMinor) }} remaining
        </div>
        <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
          <div
            class="h-full bg-spring-500 transition-all duration-500"
            :style="{ width: lifetimeProgressPercent + '%' }"
          />
        </div>
        <div class="mt-2 text-xs text-horizon-400">
          {{ formatZARMinor(tfsa.lifetimeUsedMinor) }} used of {{ formatZARMinor(tfsa.lifetimeCapMinor) }}
        </div>
      </div>
    </div>

    <!-- Contributions list -->
    <TfsaContributionTracker
      :contributions="contributions"
      :loading="isLoading"
    />

    <!-- Record contribution modal -->
    <TfsaContributionModal
      v-if="showContributionModal"
      :tax-year="taxYear"
      :annual-remaining-minor="tfsa.annualRemainingMinor"
      :lifetime-remaining-minor="tfsa.lifetimeRemainingMinor"
      @save="handleContribution"
      @close="showContributionModal = false"
    />
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import TfsaContributionTracker from './TfsaContributionTracker.vue';
import TfsaContributionModal from './TfsaContributionModal.vue';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'TfsaDashboard',
  components: { TfsaContributionTracker, TfsaContributionModal },
  mixins: [zaCurrencyMixin],
  data() {
    return { showContributionModal: false };
  },
  computed: {
    ...mapGetters('zaSavings', ['tfsa', 'contributions', 'taxYear', 'isLoading']),
    annualProgressPercent() {
      const cap = this.tfsa.annualCapMinor;
      if (!cap) return 0;
      return Math.min(100, (this.tfsa.annualUsedMinor / cap) * 100);
    },
    lifetimeProgressPercent() {
      const cap = this.tfsa.lifetimeCapMinor;
      if (!cap) return 0;
      return Math.min(100, (this.tfsa.lifetimeUsedMinor / cap) * 100);
    },
  },
  async mounted() {
    await this.fetchDashboard();
  },
  methods: {
    ...mapActions('zaSavings', ['fetchDashboard', 'storeContribution']),
    async handleContribution(payload) {
      await this.storeContribution(payload);
      this.showContributionModal = false;
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/ZA/Savings/TfsaDashboard.vue
git commit -m "feat(za-frontend): TfsaDashboard component (WS 1.2b)"
```

---

## Task 13: `TfsaContributionTracker.vue` — recent contributions list

**Files:**
- Create: `resources/js/components/ZA/Savings/TfsaContributionTracker.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <div class="card p-6">
    <div class="flex items-end justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Recent contributions</h2>
      <span class="text-sm text-horizon-400">{{ contributions.length }} this tax year</span>
    </div>

    <div v-if="loading" class="py-10 flex justify-center">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else-if="contributions.length === 0" class="py-10 text-center text-horizon-400">
      No contributions yet this tax year. Record your first to start tracking.
    </div>

    <table v-else class="w-full text-sm">
      <thead>
        <tr class="text-left text-horizon-400 uppercase tracking-wide text-xs border-b border-light-gray">
          <th class="py-2 font-semibold">Date</th>
          <th class="py-2 font-semibold">Type</th>
          <th class="py-2 font-semibold text-right">Amount</th>
          <th class="py-2 font-semibold">Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="c in contributions"
          :key="c.id"
          class="border-b border-light-gray last:border-0"
        >
          <td class="py-3 text-horizon-700">{{ c.contribution_date }}</td>
          <td class="py-3 text-horizon-500 capitalize">{{ c.source_type.replace('_', ' ') }}</td>
          <td class="py-3 text-horizon-700 font-semibold text-right">
            {{ formatZARMinor(c.amount_minor) }}
          </td>
          <td class="py-3 text-horizon-500 truncate max-w-xs">{{ c.notes || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'TfsaContributionTracker',
  mixins: [zaCurrencyMixin],
  props: {
    contributions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/ZA/Savings/TfsaContributionTracker.vue
git commit -m "feat(za-frontend): TfsaContributionTracker (WS 1.2b)"
```

---

## Task 14: `TfsaContributionModal.vue` — record contribution form

**Files:**
- Create: `resources/js/components/ZA/Savings/TfsaContributionModal.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <div class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record a TFSA contribution</h2>
        <p class="text-sm text-horizon-500 mt-1">Tax year {{ taxYear || '2026/27' }}</p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (ZAR)</label>
          <input
            v-model.number="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          />
          <p v-if="willBreachAnnual" class="mt-1 text-sm text-violet-600">
            Warning: this exceeds your annual allowance by {{ formatZARMinor(amountMinor - annualRemainingMinor) }}. A 40% penalty applies to the excess.
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Contribution date</label>
          <input
            v-model="form.contribution_date"
            type="date"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Type</label>
          <select
            v-model="form.source_type"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          >
            <option value="contribution">New contribution</option>
            <option value="transfer_in">Transfer in from another TFSA</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Notes (optional)</label>
          <input
            v-model="form.notes"
            type="text"
            maxlength="500"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save contribution' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'TfsaContributionModal',
  mixins: [zaCurrencyMixin],
  props: {
    taxYear: { type: String, default: '2026/27' },
    annualRemainingMinor: { type: Number, default: 0 },
    lifetimeRemainingMinor: { type: Number, default: 0 },
  },
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        amount: null,
        contribution_date: new Date().toISOString().slice(0, 10),
        source_type: 'contribution',
        notes: '',
      },
      submitting: false,
    };
  },
  computed: {
    amountMinor() {
      return this.toMinorZAR(this.form.amount || 0);
    },
    willBreachAnnual() {
      return this.amountMinor > this.annualRemainingMinor && this.annualRemainingMinor >= 0;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          tax_year: this.taxYear,
          amount_minor: this.amountMinor,
          contribution_date: this.form.contribution_date,
          source_type: this.form.source_type,
          notes: this.form.notes || null,
        });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/ZA/Savings/TfsaContributionModal.vue
git commit -m "feat(za-frontend): TfsaContributionModal with pre-flight penalty warning (WS 1.2b)"
```

---

## Task 15: `ZaSavingsForm.vue` — account form with TFSA toggle

**Files:**
- Create: `resources/js/components/ZA/Savings/ZaSavingsForm.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Bank name</label>
        <input v-model="form.bank_name" type="text" required maxlength="100"
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Account name</label>
        <input v-model="form.account_name" type="text" required maxlength="100"
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Account type</label>
        <select v-model="form.account_type" required
                class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
          <option value="current">Current (cheque)</option>
          <option value="savings">Savings</option>
          <option value="tfsa">Tax-Free Savings (TFSA)</option>
          <option value="notice">Notice deposit</option>
          <option value="money_market">Money market</option>
          <option value="fixed_deposit">Fixed deposit</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Current balance (ZAR)</label>
        <input v-model.number="form.current_balance" type="number" step="0.01" min="0" required
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Interest rate (%)</label>
        <input v-model.number="form.interest_rate" type="number" step="0.01" min="0" max="100"
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div class="flex items-end">
        <label class="inline-flex items-center gap-3">
          <input v-model="form.is_tfsa" type="checkbox"
                 class="h-5 w-5 rounded border-horizon-300 text-raspberry-500 focus:ring-violet-500" />
          <span class="text-sm font-semibold text-horizon-700">This is a TFSA account</span>
        </label>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4">
      <button type="button" @click="$emit('close')"
              class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold">Cancel</button>
      <button type="submit" :disabled="submitting"
              class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50">
        {{ submitting ? 'Saving…' : 'Save account' }}
      </button>
    </div>
  </form>
</template>

<script>
export default {
  name: 'ZaSavingsForm',
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        bank_name: '',
        account_name: '',
        account_type: 'savings',
        current_balance: null,
        interest_rate: null,
        is_tfsa: false,
      },
      submitting: false,
    };
  },
  watch: {
    'form.account_type'(v) {
      // Auto-flag TFSA when type selected.
      if (v === 'tfsa') this.form.is_tfsa = true;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', { ...this.form });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/ZA/Savings/ZaSavingsForm.vue
git commit -m "feat(za-frontend): ZaSavingsForm with TFSA toggle (WS 1.2b)"
```

---

## Task 16: `ZaEmergencyFundGauge.vue` — emergency fund adequacy gauge

**Files:**
- Create: `resources/js/components/ZA/Savings/ZaEmergencyFundGauge.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <div class="card p-6">
    <h2 class="text-xl font-bold text-horizon-700 mb-4">Emergency fund</h2>

    <form @submit.prevent="assess" class="space-y-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Current balance (ZAR)</label>
        <input v-model.number="form.current_balance" type="number" step="0.01" min="0" required
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Essential monthly expenditure (ZAR)</label>
        <input v-model.number="form.monthly" type="number" step="0.01" min="0" required
               class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Household earners</label>
          <select v-model.number="form.earners"
                  class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
            <option :value="1">1 (single earner)</option>
            <option :value="2">2 (dual earner)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">UIF eligible</label>
          <select v-model="form.uif"
                  class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
            <option :value="true">Yes</option>
            <option :value="false">No</option>
          </select>
        </div>
      </div>
      <button type="submit"
              class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg">
        Assess adequacy
      </button>
    </form>

    <div v-if="result" class="mt-6 pt-6 border-t border-light-gray">
      <div class="flex items-end justify-between mb-3">
        <div>
          <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Target</div>
          <div class="text-2xl font-black text-horizon-700">{{ formatZARMinor(result.target_minor) }}</div>
          <div class="text-xs text-horizon-400">{{ result.target_months }} months</div>
        </div>
        <div class="text-right">
          <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Covered</div>
          <div class="text-2xl font-black" :class="statusColor">
            {{ result.months_covered }} months
          </div>
        </div>
      </div>
      <div class="h-3 bg-horizon-100 rounded-full overflow-hidden">
        <div class="h-full transition-all duration-500"
             :class="result.status === 'adequate' ? 'bg-spring-500' : 'bg-violet-500'"
             :style="{ width: progressPercent + '%' }" />
      </div>
      <p class="mt-3 text-sm text-horizon-500">{{ result.weighting_reason }}</p>
      <p v-if="result.status === 'shortfall'" class="mt-2 text-sm font-semibold text-raspberry-600">
        Shortfall: {{ formatZARMinor(result.shortfall_minor) }}
      </p>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaEmergencyFundGauge',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        current_balance: null,
        monthly: null,
        earners: 1,
        uif: true,
      },
    };
  },
  computed: {
    ...mapGetters('zaSavings', ['emergencyFund']),
    result() {
      return this.emergencyFund;
    },
    progressPercent() {
      if (!this.result || !this.result.target_minor) return 0;
      return Math.min(100, (this.form.current_balance * 100 / (this.result.target_minor / 100)) * 100 / 100) || 0;
    },
    statusColor() {
      if (!this.result) return 'text-horizon-700';
      return this.result.status === 'adequate' ? 'text-spring-600' : 'text-violet-600';
    },
  },
  methods: {
    ...mapActions('zaSavings', ['assessEmergencyFund']),
    async assess() {
      await this.assessEmergencyFund({
        current_balance_minor: this.toMinorZAR(this.form.current_balance || 0),
        essential_monthly_expenditure_minor: this.toMinorZAR(this.form.monthly || 0),
        income_stability: 'stable',
        household_income_earners: this.form.earners,
        uif_eligible: this.form.uif,
      });
    },
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/ZA/Savings/ZaEmergencyFundGauge.vue
git commit -m "feat(za-frontend): ZaEmergencyFundGauge (WS 1.2b)"
```

---

## Task 17: `ZaSavingsDashboard.vue` — page composition

**Files:**
- Create: `resources/js/views/ZA/ZaSavingsDashboard.vue`

- [ ] **Step 1: Create the view**

```vue
<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto space-y-8 py-6">
      <TfsaDashboard />
      <ZaEmergencyFundGauge />
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import TfsaDashboard from '@/components/ZA/Savings/TfsaDashboard.vue';
import ZaEmergencyFundGauge from '@/components/ZA/Savings/ZaEmergencyFundGauge.vue';

export default {
  name: 'ZaSavingsDashboard',
  components: { AppLayout, TfsaDashboard, ZaEmergencyFundGauge },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/ZA/ZaSavingsDashboard.vue
git commit -m "feat(za-frontend): ZaSavingsDashboard view (WS 1.2b)"
```

---

## Task 18: Regression test + manual browser verification

**Files:** none created/modified (verification only)

- [ ] **Step 1: Full Pest suite**

```
./vendor/bin/pest
```
Expected: ≥ 2695 passing, same 5 pre-existing failures (4 × `ProtectionWorkflowTest` adequacy_score + 1 × `InvestmentControllerTest` flake). No new failures.

- [ ] **Step 2: Make a ZA-active test user**

```bash
php artisan tinker --execute="
\$u = \App\Models\User::factory()->create(['email' => 'za-test@example.com', 'password' => bcrypt('password')]);
\$za = \App\Models\Jurisdiction::updateOrCreate(['code' => 'ZA'], ['name' => 'South Africa', 'currency' => 'ZAR', 'locale' => 'en_ZA', 'is_active' => true]);
\$u->jurisdictions()->attach(\$za->id, ['is_primary' => false, 'activated_at' => now()]);
\$gb = \App\Models\Jurisdiction::where('code','GB')->first();
if (\$gb) \$u->jurisdictions()->attach(\$gb->id, ['is_primary' => true, 'activated_at' => now()]);
echo 'user id: '.\$u->id.PHP_EOL;
"
```

- [ ] **Step 3: Browser test (Playwright)**

Set `FYNLA_ACTIVE_PACKS=GB,ZA` in `.env`, clear config cache (`php artisan config:clear`), then:

1. Launch browser, navigate to `http://localhost:8000`.
2. Log in as `za-test@example.com` / `password` (fetch local verification code via tinker per CLAUDE.md § Authentication for Testing).
3. Verify the sidebar now shows a "South Africa" section with a "Savings (TFSA)" item.
4. Click it → `/za/savings` loads.
5. Click "Record contribution", fill amount `R5 000`, today's date, submit → verify:
   - Contribution appears in the contributions table.
   - Annual remaining decreases by R5 000.
   - Annual progress bar advances.
6. Fill emergency fund form (balance `R30 000`, monthly `R15 000`, single earner, UIF ineligible) → assess → verify 7-month target, shortfall displayed, violet progress bar (not spring).
7. Try a deliberate over-contribution (`R50 000` → R46 000 cap breached) → verify the violet penalty warning appears in the modal pre-flight AND the breach is persisted with `penalty_minor` on the response.
8. Log out, log in as a GB-only user → verify `/za/savings` redirects to `/dashboard` and no "South Africa" sidebar section is visible.

If any step fails, FIX and re-run from Step 1 per CLAUDE.md § Testing.

- [ ] **Step 4: Final commit**

```bash
git add .
git commit -m "test(za-frontend): WS 1.2b browser-verified green end-to-end"
```

---

## Self-Review

**Spec coverage:**

- `resources/js/components/ZA/` scaffold → Tasks 12–17 ✓
- ZA route lazy-loading + jurisdiction guard → Task 10 ✓
- Jurisdiction-aware sidebar → Task 11 (leveraging Task 6's registry) ✓
- ZA Vuex module organisation → Task 7 (6 modules registered) ✓
- `/api/za/*` route group behind middleware → Task 1 ✓
- TFSA dashboard + contribution tracker + savings form → Tasks 12–15 ✓
- ZAR formatter → Task 9 ✓
- WS 1.2a backend consumption (`pack.za.savings`, `pack.za.tfsa.tracker`, `pack.za.savings.emergency_fund`) → Tasks 2–5 ✓

**Placeholder scan:** No TBD/TODO/"add appropriate X" — each step has concrete code. The one `TODO` in `ActiveJurisdictionMiddleware` is a pre-existing marker for WS D (user_jurisdictions table check) and is out of scope here.

**Type / name consistency:**

- `tfsa.annual_cap_minor` (API snake_case) ↔ `tfsa.annualCapMinor` (Vuex camelCase) — mapping explicit in `SET_DASHBOARD` mutation ✓
- `storeContribution` action ↔ `storeContribution` controller method ↔ `zaSavingsService.storeContribution` ✓
- Jurisdiction code `'za'` lowercase in frontend getters ↔ `'ZA'` uppercase in backend middleware — each uses the correct case for its layer ✓
- `is_tfsa` boolean flag consistent: migration, model cast, API request/resource, Vue form ✓

**Audit resolutions (2026-04-18):**

1. ✅ **Sidebar refactor.** Architect §5 flagged hardcoded `v-if` as long-term blocker. Task 11 now renders the ZA section via `v-for` over `jurisdiction/zaModules`. UK sections stay hardcoded (out of scope). Later SA UI workstreams append entries to `MODULES_BY_JURISDICTION.za` — zero `SideMenu.vue` edits needed.
2. ✅ **Jurisdiction guard boot race.** Explorer caveat 2 confirmed the race on hard reload. Task 10 now `await store.dispatch('auth/fetchUser')` when `isAuthenticated && activeJurisdictions.length === 0`.
3. ✅ **`Intl.NumberFormat('en-ZA')`.** Explorer confirmed consistent with existing `utils/currency.js` pattern. Plan's `formatZAR` fallback handles older browsers.
4. ✅ **`savings_accounts.country_code`.** Explorer confirmed the column exists (migration `2026_04_17_300001`). Task 5 no longer has the conditional.
5. ✅ **Vuex hydration wiring.** Explorer confirmed `auth.js` L162 already dispatches `jurisdiction/hydrateFromSession`. Full chain works — only boot-order race remained (see item 2).

**Additional amendments from architect audit:**

6. ✅ **Controller location (`app/Http/Controllers/Api/Za/`)** — architect confirmed as acceptable thin-proxy pattern. ADR-003 governs pack-to-pack isolation, not HTTP routing. GB precedent: UK routes also live in `app/Http/` with an empty `routes` array in `GbPackServiceProvider`.
7. ✅ **`active.jurisdiction` no-op on `/api/za/*`** — TODO comment added to route group for WS D migration.
8. ✅ **Money VO in controller** — `dashboard()` method now uses `Money::fromMinor` / `subtract` / `toMinor` per ADR-005. Other methods keep raw integers pending a broader sweep.
9. ✅ **Eager Vuex registration** — architect confirmed; matches 33-module existing pattern. `vuex-persistedstate` paths don't include `za*` so no session persistence.
10. ✅ **Five placeholder Vuex modules** — architect confirmed pre-registration is correct. Each placeholder now carries a workstream comment.
11. ✅ **`banknotes` icon allow-list** — explorer confirmed available in `SideMenuIcon.vue` L24.

**Additional fixes from explorer findings:**

12. ✅ **`SideMenuSection` prop `label` not `title`** — Task 11 fixed.
13. ✅ **`SideMenuSection` requires `:expanded`/`@toggle` wiring** — Task 11 now wires via `isSectionExpanded('zaSection')` / `toggleSection('zaSection')`.
14. ✅ **Composition API only (not Options API)** — Task 11 rewritten for Composition API matching existing file.
15. ✅ **`activeSectionKey` missing `/za/*` mapping** — Task 11 Step 2 adds it.
16. ✅ **TFSA acronym policy** — added as second exception in CLAUDE.md Rule 10 (alongside ISA). Implementation step for this is in Task 0 below.
17. ✅ **Joint ownership on ZA savings** — explicitly deferred to post-WS 1.7; documented in Task 5 + Out of Scope.
18. ✅ **Savings agent coordination** — explicitly deferred to WS 1.7 Coordination; documented in Out of Scope.

**Residual (non-blockers):**
- `previewModeMixin` not wired in ZA components. `PreviewWriteInterceptor` already blocks writes server-side. Adding `v-preview-disabled` for visual consistency is nice-to-have — implementer may add if straightforward.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-18-ws-1-2b-za-frontend-foundation.md`.

**Next step per the memorised workflow:** invoke `/prd-writer` to audit this plan against the live codebase, resolve the 5 caveats above in a rolling interview, and emit the canonical PRD at `April/April18Updates/PRD-ws-1-2b-za-frontend-foundation.md`. Only after the PRD is written do we implement.
