# WS 1.3c — SA Investment + Exchange Control Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **MANDATORY GATE:** Before implementation begins, this plan MUST be passed through `/prd-writer` to produce a canonical PRD. The PRD audit routinely surfaces 2-5 codebase findings the plan author missed. Do NOT skip — `feedback_workflow_spec_plan_prd.md` memory rule.

**Status:** Amended — 18 April 2026 — conflicts resolved against codebase audit (code-explorer + code-architect). Changes: test bootstrap fixed (`putenv` + drop non-existent jurisdiction columns); `holdings.cost_basis` post-purchase sync via new `ZaBaseCostTracker::syncCostBasis()` helper; controller `try/catch InvalidArgumentException → 422`; `country = 'South Africa'` (human-readable per existing convention); `Money` VO adopted in both new `dashboard()` methods (ADR-005 + WS 1.2b precedent); SA acronym spell-outs (CLAUDE.md Rule 10) for SDA/FIA/AIT/SARB on first use in every component; `/check-approval` added to `PreviewWriteInterceptor::EXCLUDED_PATTERNS`; new `ZaSdaSummaryWidget` embedded on Investment dashboard (SA Research § 8.2); 'Provider' kept as primary label with separate `platform` field for LISP; AIT 4-item generic checklist kept for v1 (matches shipped WS 1.3b backend) with IT14SD/IT77C/TCS deferred to v1.1; `ZaCgtProjectionPanel` kept as labelled empty-state placeholder.

**Goal:** Ship the second SA frontend surface — a SA Investment dashboard + an Exchange Control dashboard — consuming the WS 1.3a (`pack.za.investment` / `.cgt` / `.lot_tracker`) and WS 1.3b (`pack.za.exchange_control` / `.ledger`) backends. Reuse the WS 1.2b scaffold by appending two entries to `MODULES_BY_JURISDICTION.za` and replacing the `zaInvestment` + `zaExchangeControl` placeholder Vuex modules.

**Architecture:** Two new top-level SA module surfaces under the existing `/za/*` route tree, each rendered from its own page view, backed by its own Vuex module + axios service + controller. Backend controllers are thin HTTP adapters over the pack container bindings (`pack.za.investment`, `pack.za.investment.cgt`, `pack.za.investment.lot_tracker`, `pack.za.exchange_control`, `pack.za.exchange_control.ledger`); business logic stays in the pack. Routes extend the existing `/api/za/*` middleware group (`auth:sanctum + active.jurisdiction + pack.enabled:za`). Sidebar composition stays data-driven via `MODULES_BY_JURISDICTION.za` — zero `SideMenu.vue` edits.

**Tech Stack:** Laravel 10 (PHP 8.2), Vue 3, Vuex 4, Vue Router 4, Tailwind, Pest, Playwright.

**Spec sources:**
- `Plans/Implementation_Plan_v2.md` — WS 1.3 amendments (split into 1.3a / 1.3b / 1.3c).
- `Plans/SA_Research_and_Mapping.md` — § 8 (CGT + discretionary), § 11 (Endowments), § 13 (Exchange Control + SDA/FIA/AIT/SARB).
- `April/April18Updates/handover-ws-1-2b.md` — § 3 (patterns locked in), § 5 (WS 1.3c brief), § 8 (NOT-do list).
- `April/April18Updates/PRD-ws-1-3a-za-investment.md` — the resolved Investment backend shape.
- `April/April18Updates/PRD-ws-1-3b-za-exchange-control.md` — the resolved Exchange Control backend shape.
- `fynlaDesignGuide.md` v1.4.0 — **CRITICAL: no icons inside cards / detail views / dashboards**. Sidebar icons only. Colours: raspberry CTAs, horizon text, spring success, violet warnings, savannah hover, eggshell background.
- `app/Http/Controllers/Api/Za/ZaSavingsController.php` — controller pattern to mirror.
- `resources/js/store/modules/zaSavings.js` — Vuex module shape to mirror.
- `resources/js/components/ZA/Savings/*` — component patterns to mirror.

**Non-goals / deferrals:**
- WS 1.4d / 1.5b / 1.6b / 1.7 / 1.8 — separate workstreams.
- SA preview personas + onboarding — WS 1.7.
- POPIA / FAIS legal copy — WS 1.8.
- Joint ownership on ZA investment accounts — deferred to WS 1.7 (SA family/spouse model).
- Specific-identification base-cost tracking — pack only supports weighted-average in v1; UI reflects that.
- Foreign-currency ledger entries — pack is ZAR-only in v1; UI accepts ZAR amounts only and shows the destination as informational metadata.
- Full Eighth-Schedule FX translation — UI accepts pre-translated ZAR gains for offshore disposals.
- Foreign-inheritance / foreign-earnings AIT row types — pack enum locked to `'sda'/'fia'`; deferred to WS 1.7 (emigration life event).
- Replacing UK sidebar with a fully data-driven loop — UK section stays hardcoded; ZA stays data-driven.
- Migration of `/api/za/*` → `/api/{cc=za}/*` — deferred to WS D (per `TODO(WS-D)` in `routes/api.php`).
- `SavingsAgent`-style coordination across SA modules for the main dashboard — deferred to WS 1.7 Coordination.
- `previewModeMixin` + `v-preview-disabled` styling on ZA components — server-side `PreviewWriteInterceptor` already blocks writes; visual polish is a nice-to-have.

**Resolved questions (all closed by audit + user interview 18 April 2026):**
1. ✅ **Wrapper code storage** — Reuse `investment_accounts.account_type` (column is `varchar(255)`, no enum constraint per `database/schema/mysql-schema.sql:883`). No migration needed.
2. ✅ **Holdings + lot recording UX** — Purchase modal writes BOTH a new lot ledger row AND syncs `holdings.cost_basis`. Sync happens via new `ZaBaseCostTracker::syncCostBasis(int $holdingId)` helper called from both `recordPurchase` and `recordDisposal` (closes the documented WS 1.3a § 5 tech-debt).
3. ✅ **CGT panel scope** — Ship both: what-if calculator + labelled empty-state projection panel ("Realised disposals will appear here once recorded"). Functional projection deferred to v1.1.
4. ✅ **AIT documents schema** — Keep the 4-item generic checklist (`tax_clearance_issued`, `source_of_funds_documented`, `recipient_kyc_complete`, `dealer_notified`) for v1 — matches shipped WS 1.3b backend integration test. Add IT14SD/IT77C/TCS via JSON column extensibility in v1.1 follow-up; tracked as known v1 limitation in PRD § 8.
5. ✅ **Sidebar icon** — `map` (confirmed exists at `SideMenuIcon.vue:146`).
6. ✅ **Two surfaces vs one** — Two separate routes (`/za/investments`, `/za/exchange-control`) PLUS a small `ZaSdaSummaryWidget` embedded on the Investment dashboard (SA Research § 8.2 — "SDA used: R450,000 of R2,000,000" expectation).

---

## Audit amendments — apply these on top of the task bodies below

The task bodies are kept as originally written for context. **These amendments override conflicting instructions in the tasks.** Implementation agent: apply each item as you reach the corresponding task.

### A1 (impacts Tasks 1, 2 — test bootstrap)

Replace every test file's `beforeEach` block. The plan's original block sets non-existent user columns and an ineffective config key. Use this canonical block instead (mirrors `tests/Feature/Api/Za/ZaSavingsControllerTest.php:13-23`):

```php
beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = \App\Models\User::factory()->create();
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});
```

Drop `'active_jurisdictions' => ['za']` and `'primary_jurisdiction' => 'za'` from the user factory call — these columns don't exist. Pack gating is installation-level via the env var, not per-user.

### A2 (impacts Task 1 — `holdings.cost_basis` sync gap, Pack-level)

Add a new method to `packs/country-za/src/Investment/ZaBaseCostTracker.php` and call it from both `recordPurchase` and `recordDisposal`. This closes the WS 1.3a § 5 documented tech-debt and prevents `ZaHoldingsList.vue` from showing R 0.00 cost basis after a purchase.

Add this method to `ZaBaseCostTracker`:

```php
/**
 * Write the current open cost basis from the lot ledger back to
 * the main-app holdings row. Called from both recordPurchase and
 * recordDisposal so the holdings.cost_basis column never drifts
 * from the authoritative ledger.
 *
 * holdings.cost_basis is decimal(15,2) storing major units (rand, not cents),
 * so divide minor units by 100 before writing.
 */
public function syncCostBasis(int $holdingId): void
{
    $openCostMinor = $this->openCostBasisMinor($holdingId);
    \Illuminate\Support\Facades\DB::table('holdings')
        ->where('id', $holdingId)
        ->update(['cost_basis' => round($openCostMinor / 100, 2)]);
}
```

Then in `recordPurchase`, after `ZaHoldingLot::create([...])` and before `return (int) $lot->id;`, add:

```php
$this->syncCostBasis($holdingId);
```

In `recordDisposal`, replace the inline `DB::table('holdings')->where('id', $holdingId)->update(['cost_basis' => round($openCostMinor / 100, 2)]);` block (the trailing 4-line section that already does this) with a single call to `$this->syncCostBasis($holdingId);` — keeps the writeback logic in one place.

Add a Pest test to `tests/Unit/Packs/Za/Investment/ZaBaseCostTrackerTest.php` (or wherever the existing tracker tests live):

```php
it('syncs holdings.cost_basis after a purchase', function () {
    $holding = \DB::table('holdings')->insertGetId([
        'holdable_id' => 1, 'holdable_type' => 'X',
        'asset_type' => 'equity', 'security_name' => 'Test',
        'quantity' => 0, 'cost_basis' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $tracker = app(\Fynla\Packs\Za\Investment\ZaBaseCostTracker::class);
    $tracker->recordPurchase(userId: 1, holdingId: $holding, quantity: 10, costMinor: 5_000_000, acquisitionDate: '2026-04-01');

    expect(\DB::table('holdings')->where('id', $holding)->value('cost_basis'))->toEqual(50000.00);
});
```

### A3 (impacts Tasks 1, 2 — controller exception handling)

Wrap every `ZaBaseCostTracker::recordDisposal` and `ZaExchangeControlLedger::record` controller call in `try/catch (\InvalidArgumentException $e)` and return 422 instead of letting the 500 propagate.

Pattern to apply in `ZaInvestmentController::recordDisposal`:

```php
try {
    $result = $this->lots->recordDisposal(
        userId: $request->user()->id,
        holdingId: $data['holding_id'],
        quantity: (float) $data['quantity'],
        disposalDate: $data['disposal_date'],
    );
} catch (\InvalidArgumentException $e) {
    return response()->json(['message' => $e->getMessage()], 422);
}
```

Same pattern in `ZaExchangeControlController::storeTransfer` around the `$this->ledger->record(...)` call.

Also wrap `recordPurchase` in `ZaInvestmentController::storePurchase` (it can throw on `quantity <= 0` or `costMinor < 0` even though Form Request should catch most of it).

Add a test case to each controller test:

```php
it('returns 422 when disposal exceeds open quantity', function () {
    Sanctum::actingAs($this->user);
    $account = \App\Models\Investment\InvestmentAccount::factory()->create([
        'user_id' => $this->user->id, 'country_code' => 'ZA', 'account_type' => 'discretionary',
    ]);
    $holding = \App\Models\Investment\Holding::create([
        'holdable_id' => $account->id, 'holdable_type' => \App\Models\Investment\InvestmentAccount::class,
        'asset_type' => 'equity', 'security_name' => 'X', 'quantity' => 0, 'cost_basis' => 0,
    ]);
    $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id, 'quantity' => 5, 'cost_minor' => 1_000_000, 'acquisition_date' => '2026-04-01',
    ]);
    $response = $this->postJson('/api/za/investments/holdings/disposal', [
        'holding_id' => $holding->id, 'quantity' => 999, 'disposal_date' => '2026-04-15',
    ]);
    $response->assertStatus(422);
});
```

### A4 (impacts Task 1 — `country` column value)

In `ZaInvestmentController::storeAccount`, change:
```php
$data['country'] = 'ZA';
```
to:
```php
$data['country'] = 'South Africa';
```
The `country` column conventionally stores human-readable country names (e.g. 'United Kingdom'); `country_code` is the 2-letter ISO. Writing 'ZA' to `country` would create a display-side data-hygiene issue for any UK component that surfaces the column.

### A5 (impacts Tasks 1, 2 — Money VO in dashboard methods)

Adopt `Fynla\Core\Money\Money` + `Fynla\Core\Money\Currency::ZAR()` for arithmetic in both new `dashboard()` methods to match WS 1.2b precedent (`ZaSavingsController::dashboard()`) and ADR-005.

`ZaInvestmentController::dashboard()` — replace the `$totalOpenCostMinor` accumulation:

```php
$zar = \Fynla\Core\Money\Currency::ZAR();
$totalOpenCost = new \Fynla\Core\Money\Money(0, $zar);
$lotCount = 0;
if (! empty($userHoldingIds)) {
    $lotCount = (int) ZaHoldingLot::query()->whereIn('holding_id', $userHoldingIds)->where('quantity_open', '>', 0)->count();
    foreach ($userHoldingIds as $hid) {
        $totalOpenCost = $totalOpenCost->plus(new \Fynla\Core\Money\Money($this->lots->openCostBasisMinor((int) $hid), $zar));
    }
}
// In response: 'total_open_cost_basis_minor' => $totalOpenCost->minor,
```

`ZaExchangeControlController::dashboard()` — replace remaining-capacity computation:

```php
$zar = \Fynla\Core\Money\Currency::ZAR();
$sdaCapMoney = new \Fynla\Core\Money\Money($sdaCap, $zar);
$fiaCapMoney = new \Fynla\Core\Money\Money($fiaCap, $zar);
$sdaConsumedMoney = new \Fynla\Core\Money\Money($sdaConsumed, $zar);
$fiaConsumedMoney = new \Fynla\Core\Money\Money($fiaConsumed, $zar);

$sdaRemaining = $sdaCapMoney->minus($sdaConsumedMoney);
if ($sdaRemaining->isNegative()) { $sdaRemaining = new \Fynla\Core\Money\Money(0, $zar); }
$fiaRemaining = $fiaCapMoney->minus($fiaConsumedMoney);
if ($fiaRemaining->isNegative()) { $fiaRemaining = new \Fynla\Core\Money\Money(0, $zar); }

// In response:
// 'remaining' => [
//     'sda_minor' => $sdaRemaining->minor,
//     'fia_minor' => $fiaRemaining->minor,
// ],
// 'consumed' => [
//     'sda_minor' => $sdaConsumedMoney->minor,
//     'fia_minor' => $fiaConsumedMoney->minor,
//     'total_minor' => $sdaConsumedMoney->plus($fiaConsumedMoney)->minor,
// ],
```

Wire format stays integer minor units. The VO is internal arithmetic only.

### A6 (impacts Tasks 8, 9 — SA acronym spell-outs per CLAUDE.md Rule 10)

Every component must spell out SDA/FIA/AIT/SARB on first use within that component. Existing fixes:

**`ZaTransferModal.vue`** — radio labels currently `'SDA (≤ R2m)'` and `'FIA (R2m – R10m, AIT required)'`. Change to:
```javascript
allowanceOptions: [
  { value: 'sda', label: 'Single Discretionary Allowance — under R2m' },
  { value: 'fia', label: 'Foreign Investment Allowance — R2m – R10m, requires SARS Approval for International Transfer (AIT)' },
],
```
And the AIT reference field label: `<label>Approval for International Transfer (AIT) reference</label>`.

**`ZaApprovalCheckCard.vue`** — result text:
```vue
<span v-if="approvalCheck.requires_approval">Submit your South African Revenue Service Approval for International Transfer (AIT) request, or contact your authorised dealer for South African Reserve Bank (SARB) approval.</span>
```

**`ZaCombinedThresholdBanner.vue`** — banner heading and body:
```vue
<div class="font-bold text-violet-700">South African Reserve Bank (SARB) special approval required</div>
<p>You've moved {{ formatZARMinor(consumed.totalMinor) }} offshore in {{ calendarYear }} — above the South African Reserve Bank combined threshold of {{ formatZARMinor(sarbThresholdMinor) }}. Further transfers this calendar year require special approval through your authorised dealer.</p>
```

**`ZaTransferLedger.vue`** — table header:
```vue
<th class="text-left py-2">Approval reference</th>
```
(replaces "AIT ref.")

**`ZaSdaFiaGauges.vue`** is already compliant — both card headers spell out the acronyms.

### A7 (impacts no plan task — middleware fix; new sub-task added)

Add `/check-approval` to `app/Http/Middleware/PreviewWriteInterceptor.php`'s `EXCLUDED_PATTERNS` so preview users can use the what-if approval check.

In `EXCLUDED_PATTERNS`, append:
```php
'#/check-approval$#',
```

Add a Pest test confirming preview users can hit the endpoint and get a real response (not a fake-success interception).

Commit message: `fix(middleware): exempt /check-approval from PreviewWriteInterceptor (WS 1.3c)`.

### A8 (impacts Task 8 — new `ZaSdaSummaryWidget` on Investment dashboard)

Create `resources/js/components/ZA/Investment/ZaSdaSummaryWidget.vue` — a small inline widget on the Investment dashboard showing SDA + FIA consumption summary, with a "Manage offshore transfers →" link to `/za/exchange-control`. SA Research § 8.2 expectation.

```vue
<template>
  <section v-if="hasZa" class="card p-6">
    <header class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-horizon-700">Offshore allowances</h2>
      <router-link to="/za/exchange-control" class="text-sm text-raspberry-500 hover:text-raspberry-700 font-semibold">
        Manage offshore transfers
      </router-link>
    </header>
    <div class="grid grid-cols-2 gap-4 text-sm">
      <div>
        <div class="text-horizon-400 uppercase tracking-wide text-xs">Single Discretionary Allowance</div>
        <div class="text-horizon-700 font-bold mt-1">{{ formatZARMinor(consumed.sdaMinor) }} of {{ formatZARMinor(allowances.sda?.annual_limit || 0) }} used</div>
      </div>
      <div>
        <div class="text-horizon-400 uppercase tracking-wide text-xs">Foreign Investment Allowance</div>
        <div class="text-horizon-700 font-bold mt-1">{{ formatZARMinor(consumed.fiaMinor) }} of {{ formatZARMinor(allowances.fia?.annual_limit || 0) }} used</div>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaSdaSummaryWidget',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('jurisdiction', ['hasJurisdiction']),
    ...mapGetters('zaExchangeControl', ['allowances', 'consumed']),
    hasZa() { return this.hasJurisdiction('za'); },
  },
  async mounted() {
    if (this.hasZa && !this.allowances.sda) {
      await this.fetchDashboard();
    }
  },
  methods: {
    ...mapActions('zaExchangeControl', ['fetchDashboard']),
  },
};
</script>
```

Then in `resources/js/views/ZA/ZaInvestmentDashboard.vue`:
- Import `ZaSdaSummaryWidget`
- Render it just below `ZaInvestmentSummary` and above the `ZaCgtCalculatorCard` row

### A9 (impacts Task 8 — `ZaInvestmentForm` keep "Provider", add `platform` for LISP)

The form already has a `platform` nullable field in the request. Surface it in the modal as a separate optional field labelled "Linked Investment Service Provider (LISP)":

```vue
<div>
  <label class="block text-sm font-semibold text-horizon-700 mb-1">Linked Investment Service Provider (LISP)
    <span class="text-horizon-400 font-normal">— optional</span>
  </label>
  <input v-model="form.platform" type="text" maxlength="255"
         placeholder="e.g. Allan Gray Platform, Investec Investment Management Services"
         class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
</div>
```

Add `platform: ''` to `data().form` and include it in the `save` emit payload.

Primary "Provider" field stays as-is (the company that manages the fund — Allan Gray, Sygnia, etc.). LISP is the platform/wrap infrastructure (often the same name + " Platform" suffix in SA).

### A10 (impacts Task 8 — `ZaCgtProjectionPanel` empty-state copy)

Replace the empty-state message in `ZaCgtProjectionPanel.vue` with explicit forward-pointing copy:

```vue
<div v-if="!hasDisposals" class="py-6 text-center text-horizon-400">
  Realised disposals will appear here once you record them. Use the disposal action on the holdings list to start tracking.
</div>
```

(Already close — confirm wording matches.)

---

## File Structure

### Backend (new)

- `app/Http/Controllers/Api/Za/ZaInvestmentController.php` — 6 endpoints: dashboard, list accounts, store account, list holdings, store purchase (via `ZaBaseCostTracker`), record disposal, open lots, calculate CGT (what-if).
- `app/Http/Controllers/Api/Za/ZaExchangeControlController.php` — 4 endpoints: dashboard (allowances + consumption + remaining), list transfers, store transfer (via `ZaExchangeControlLedger`), check approval requirement (what-if).
- `app/Http/Requests/Za/Investment/StoreZaInvestmentAccountRequest.php` — validates `account_type` ∈ {`tfsa`, `discretionary`, `endowment`}, current_value, etc.
- `app/Http/Requests/Za/Investment/StoreHoldingPurchaseRequest.php` — validates holding metadata + quantity + cost.
- `app/Http/Requests/Za/Investment/RecordHoldingDisposalRequest.php` — validates holding_id + quantity + disposal_date.
- `app/Http/Requests/Za/Investment/CalculateCgtRequest.php` — validates gain + income + age + wrapper.
- `app/Http/Requests/Za/ExchangeControl/StoreTransferRequest.php` — validates allowance_type + amount + date + AIT metadata.
- `app/Http/Requests/Za/ExchangeControl/CheckApprovalRequest.php` — validates amount + transfer type for what-if.
- `app/Http/Resources/Za/ZaInvestmentAccountResource.php` — wire shape.
- `app/Http/Resources/Za/ZaHoldingResource.php` — wire shape with open lot count + open quantity.
- `app/Http/Resources/Za/ZaHoldingLotResource.php` — wire shape for individual lot rows.
- `app/Http/Resources/Za/ZaExchangeControlEntryResource.php` — wire shape with AIT metadata.

### Backend (modify)

- `routes/api.php` — extend the `/api/za/*` group with `/investments/*` and `/exchange-control/*` sub-groups.

### Backend tests (new)

- `tests/Feature/Api/Za/ZaInvestmentControllerTest.php` — 8+ Pest tests:
  - 401 for unauth.
  - 403 for non-ZA users (via `EnsurePackEnabled` belt-and-braces; depending on jurisdiction config).
  - Dashboard returns wrappers + allowances + open lots summary.
  - Store ZA investment account writes `country_code='ZA'`.
  - Store holding purchase writes a lot ledger row + `holdings.cost_basis`.
  - Record disposal draws down lots and updates `holdings.cost_basis`.
  - List open lots returns ordered shape.
  - Calculate CGT (what-if) returns `tax_due_minor` matching `ZaCgtCalculator` directly.
- `tests/Feature/Api/Za/ZaExchangeControlControllerTest.php` — 6+ Pest tests:
  - 401 for unauth.
  - Dashboard returns SDA + FIA caps + consumed + remaining for current calendar year.
  - Store SDA transfer increments `sumConsumed('sda')`.
  - Store FIA transfer with AIT metadata round-trips reference + documents.
  - Calendar-year isolation (2025 entry doesn't leak into 2026 dashboard).
  - Check approval (what-if) — below SDA cap returns `false`, above SARB threshold returns `true`.

### Frontend Vuex (new — replaces placeholders)

- `resources/js/store/modules/zaInvestment.js` — full functional module (state, getters, actions, mutations) replacing the placeholder shipped in WS 1.2b. State: `taxYear`, `wrappers`, `allowances`, `accounts`, `holdings`, `openLots`, `cgtScenario`, `loading`, `error`. Actions: `fetchDashboard`, `fetchAccounts`, `storeAccount`, `fetchHoldings`, `storePurchase`, `recordDisposal`, `fetchLots`, `calculateCgt`, `reset`.
- `resources/js/store/modules/zaExchangeControl.js` — full functional module replacing placeholder. State: `calendarYear`, `allowances` (sda + fia), `consumed`, `remaining`, `transfers`, `loading`, `error`. Actions: `fetchDashboard`, `fetchTransfers`, `storeTransfer`, `checkApproval`, `reset`.

### Frontend services (new)

- `resources/js/services/zaInvestmentService.js` — axios wrapper for `/api/za/investments/*`.
- `resources/js/services/zaExchangeControlService.js` — axios wrapper for `/api/za/exchange-control/*`.

### Frontend components (new)

**Investment** — `resources/js/components/ZA/Investment/`:
- `ZaInvestmentSummary.vue` — top-of-page card: TFSA/discretionary/endowment wrapper summary cards, with annual contribution context.
- `ZaInvestmentAccountsList.vue` — list of ZA investment accounts (filtered by `country_code='ZA'`) with wrapper badges.
- `ZaInvestmentForm.vue` — add/edit ZA investment account modal. Wrapper radio (TFSA / Discretionary / Endowment), provider, current_value, etc.
- `ZaHoldingsList.vue` — table of holdings under selected account, showing security_name, quantity, cost_basis, current_value, open lot count.
- `ZaPurchaseModal.vue` — record a purchase: holding_id (or new), quantity, cost (ZAR), acquisition_date, notes. Calls `recordPurchase`.
- `ZaDisposalModal.vue` — record a disposal: holding_id, quantity, disposal_date. Pre-flight: shows weighted-average cost basis to be removed and projected gain.
- `ZaCgtCalculatorCard.vue` — what-if CGT calculator (gain, taxable income, age, wrapper). Live `calculateCgt` call with debounce; shows tax_due_minor, exclusion_applied, marginal_rate.
- `ZaCgtProjectionPanel.vue` — YTD CGT projection panel (computed from actual disposals across all the user's ZA holdings; uses `calculateInvestmentTax` over aggregated gains). May ship "no realised disposals yet" empty state.

**Exchange Control** — `resources/js/components/ZA/ExchangeControl/`:
- `ZaSdaFiaGauges.vue` — two stacked gauges: SDA (R2m) consumed/remaining, FIA (R10m) consumed/remaining. Spring fill, violet at >75%, raspberry at >100% (= breach territory).
- `ZaCombinedThresholdBanner.vue` — banner shown when SDA + FIA combined consumed > R12m: "SARB special approval required for further transfers in 2026."
- `ZaTransferLedger.vue` — table of transfers in current calendar year: date, allowance, amount, destination_country, purpose, AIT reference (if FIA).
- `ZaTransferModal.vue` — record a new transfer. Allowance type (SDA / FIA radio), amount (ZAR), date, destination country (free text), purpose, authorised dealer (optional). FIA branch adds AIT reference + 4-item checklist (tax clearance issued, source of funds documented, recipient KYC complete, dealer notified). Pre-flight warning if amount + YTD consumed exceeds the cap.
- `ZaApprovalCheckCard.vue` — what-if approval checker: enter amount + transfer type, see whether SARS AIT or SARB approval is required.

### Frontend page views (new)

- `resources/js/views/ZA/ZaInvestmentDashboard.vue` — composes `ZaInvestmentSummary`, `ZaInvestmentAccountsList`, `ZaCgtCalculatorCard`, `ZaCgtProjectionPanel`, `ZaHoldingsList` (tabbed or sectioned). `mounted()` calls `fetchDashboard`.
- `resources/js/views/ZA/ZaExchangeControlDashboard.vue` — composes `ZaSdaFiaGauges`, `ZaCombinedThresholdBanner`, `ZaTransferLedger`, `ZaApprovalCheckCard`. `mounted()` calls `fetchDashboard`.

### Frontend (modify)

- `resources/js/store/modules/jurisdiction.js` — append two entries to `MODULES_BY_JURISDICTION.za`.
- `resources/js/store/index.js` — no change (modules already eagerly registered in WS 1.2b).
- `resources/js/router/index.js` — add `/za/investments` and `/za/exchange-control` lazy routes with `meta.requiresJurisdiction = 'za'`.

---

## Conventions inherited from WS 1.2b

These are locked. Honour them:

1. Components live under `resources/js/components/ZA/{Module}/`. Page views under `resources/js/views/ZA/Za{Module}Dashboard.vue`.
2. Routes: `/za/{module}` with `meta: { requiresAuth: true, requiresJurisdiction: 'za' }` and `() => import(...)` lazy loader.
3. Vuex store: namespaced `za{Module}`. State shape mirrors API snake_case → store camelCase on mutation.
4. Services: `resources/js/services/za{Module}Service.js`, axios wrapper.
5. API namespacing: `/api/za/{module}/*`. Always behind `['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za']`.
6. Controllers: `app/Http/Controllers/Api/Za/Za{Module}Controller.php`. Inject pack contracts via constructor or resolve via `app('pack.za.{module}')`.
7. Wire format: integer minor units. Major-unit conversion in Vue formatters only.
8. `Money` VO + `Currency::ZAR()` for internal arithmetic in controllers (ADR-005); keeps wire integer.
9. **NO icons inside cards, detail views, or dashboard bodies.** Sidebar only.
10. ZAR via `formatZAR` / `formatZARMinor` / `toMinorZAR` from `zaCurrencyMixin`. Never hardcode "R " or thousand separators.
11. Sidebar: append entries to `MODULES_BY_JURISDICTION.za` only. Never edit `SideMenu.vue`.
12. Acronyms: only ISA + TFSA may stay abbreviated. SDA, FIA, AIT, DTA, RA, PF must be spelled on first use, then can use the acronym.
13. Form modals emit `save` (not `submit`) — parent handles API call + close on success / keep open on error.
14. Tests: Pest with `RefreshDatabase` + explicit `$this->seed(ZaTaxConfigurationSeeder::class)` for ZA tests in `beforeEach`.

---

## Task 0: Add sidebar entries for Investment + Exchange Control

**Files:**
- Modify: `resources/js/store/modules/jurisdiction.js`

- [ ] **Step 1: Verify icon allow-list**

Confirm `trending-up` and `map` exist in `resources/js/components/SideMenuIcon.vue`.

```bash
grep -E "name === 'trending-up'|name === 'map'" resources/js/components/SideMenuIcon.vue
```

Expected: both lines present. (Both confirmed during plan authoring.)

- [ ] **Step 2: Append entries**

Edit `resources/js/store/modules/jurisdiction.js`. In the `MODULES_BY_JURISDICTION.za` array, replace the placeholder comment lines with two new entries:

```javascript
  za: [
    {
      key: 'za-savings',
      label: 'Savings (TFSA)',
      route: '/za/savings',
      icon: 'banknotes',
      section: 'zaSection',
    },
    {
      key: 'za-investment',
      label: 'Investments',
      route: '/za/investments',
      icon: 'trending-up',
      section: 'zaSection',
    },
    {
      key: 'za-exchange-control',
      label: 'Exchange Control',
      route: '/za/exchange-control',
      icon: 'map',
      section: 'zaSection',
    },
    // WS 1.4d will add za-retirement here
    // WS 1.5b will add za-protection here
    // WS 1.6b will add za-estate here
  ],
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/store/modules/jurisdiction.js
git commit -m "feat(za-frontend): add sidebar entries for Investment + Exchange Control (WS 1.3c)"
```

---

## Task 1: Backend — ZaInvestmentController + Form Requests + Resources (TDD)

**Files:**
- Create: `app/Http/Controllers/Api/Za/ZaInvestmentController.php`
- Create: `app/Http/Requests/Za/Investment/StoreZaInvestmentAccountRequest.php`
- Create: `app/Http/Requests/Za/Investment/StoreHoldingPurchaseRequest.php`
- Create: `app/Http/Requests/Za/Investment/RecordHoldingDisposalRequest.php`
- Create: `app/Http/Requests/Za/Investment/CalculateCgtRequest.php`
- Create: `app/Http/Resources/Za/ZaInvestmentAccountResource.php`
- Create: `app/Http/Resources/Za/ZaHoldingResource.php`
- Create: `app/Http/Resources/Za/ZaHoldingLotResource.php`
- Test: `tests/Feature/Api/Za/ZaInvestmentControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/Za/ZaInvestmentControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['fynla.active_packs' => ['za', 'gb']]);
    $this->seed(ZaTaxConfigurationSeeder::class);

    $this->user = User::factory()->create([
        'active_jurisdictions' => ['za'],
        'primary_jurisdiction' => 'za',
    ]);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/za/investments/dashboard')->assertStatus(401);
});

it('returns wrappers + allowances + open lots summary on dashboard', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/za/investments/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'wrappers' => [['code', 'name', 'description', 'tax_treatment']],
                'allowances' => ['tfsa', 'discretionary', 'endowment'],
                'open_lot_summary' => ['total_open_cost_basis_minor', 'lot_count'],
            ],
        ]);

    expect($response->json('data.wrappers'))->toHaveCount(3);
});

it('stores a ZA investment account with country_code=ZA', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/investments/accounts', [
        'account_type' => 'discretionary',
        'provider' => 'Allan Gray',
        'current_value' => 100000,
        'tax_year' => '2026/27',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('investment_accounts', [
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
});

it('records a purchase, writes a lot, and updates holdings.cost_basis', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'ticker' => 'NPN',
        'quantity' => 0,
        'cost_basis' => 0,
    ]);

    $response = $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id,
        'quantity' => 10,
        'cost_minor' => 5_000_000, // R50,000
        'acquisition_date' => '2026-04-01',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('za_holding_lots', [
        'user_id' => $this->user->id,
        'holding_id' => $holding->id,
        'quantity_acquired' => 10,
        'acquisition_cost_minor' => 5_000_000,
    ]);
});

it('records a disposal and updates holdings.cost_basis to remaining open cost', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'quantity' => 0,
        'cost_basis' => 0,
    ]);

    // Two purchases via the API
    $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id,
        'quantity' => 10,
        'cost_minor' => 5_000_000,
        'acquisition_date' => '2026-04-01',
    ])->assertStatus(201);

    // Disposal of half
    $response = $this->postJson('/api/za/investments/holdings/disposal', [
        'holding_id' => $holding->id,
        'quantity' => 5,
        'disposal_date' => '2026-04-15',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['units_disposed', 'cost_basis_removed_minor']]);

    expect($holding->fresh()->cost_basis)->toBeFloat()->and($holding->fresh()->cost_basis)->toEqual(250.0); // R250
});

it('lists open lots ordered by acquisition_date', function () {
    Sanctum::actingAs($this->user);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'country_code' => 'ZA',
        'account_type' => 'discretionary',
    ]);
    $holding = Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => 'equity',
        'security_name' => 'Naspers',
        'quantity' => 0,
        'cost_basis' => 0,
    ]);
    $this->postJson('/api/za/investments/holdings/purchase', [
        'holding_id' => $holding->id, 'quantity' => 10, 'cost_minor' => 5_000_000, 'acquisition_date' => '2026-04-01',
    ]);

    $response = $this->getJson("/api/za/investments/holdings/{$holding->id}/lots");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'quantity_open', 'acquisition_cost_minor', 'acquisition_date']]]);
});

it('calculates discretionary CGT for a what-if scenario', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/investments/cgt/calculate', [
        'wrapper_code' => 'discretionary',
        'gain_minor' => 10_000_000, // R100,000
        'income_minor' => 50_000_000, // R500,000
        'age' => 40,
        'tax_year' => '2026/27',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['tax_due_minor', 'exclusion_applied_minor', 'included_minor', 'marginal_rate']]);

    expect($response->json('data.tax_due_minor'))->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaInvestmentControllerTest.php
```

Expected: FAIL — controller and routes don't exist yet.

- [ ] **Step 3: Create Form Requests**

Create `app/Http/Requests/Za/Investment/StoreZaInvestmentAccountRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZaInvestmentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_type' => ['required', Rule::in(['tfsa', 'discretionary', 'endowment'])],
            'provider' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'current_value' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'tax_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'platform' => ['nullable', 'string', 'max:255'],
            'risk_preference' => ['nullable', 'string', 'max:50'],
        ];
    }
}
```

Create `app/Http/Requests/Za/Investment/StoreHoldingPurchaseRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;

class StoreHoldingPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holding_id' => ['required', 'integer', 'exists:holdings,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'cost_minor' => ['required', 'integer', 'min:0'],
            'acquisition_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

Create `app/Http/Requests/Za/Investment/RecordHoldingDisposalRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;

class RecordHoldingDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holding_id' => ['required', 'integer', 'exists:holdings,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'disposal_date' => ['required', 'date'],
        ];
    }
}
```

Create `app/Http/Requests/Za/Investment/CalculateCgtRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateCgtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wrapper_code' => ['required', Rule::in(['discretionary', 'endowment', 'tfsa'])],
            'gain_minor' => ['required', 'integer', 'min:0'],
            'income_minor' => ['required_if:wrapper_code,discretionary', 'integer', 'min:0'],
            'age' => ['required_if:wrapper_code,discretionary', 'integer', 'min:18', 'max:120'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
```

- [ ] **Step 4: Create Resources**

Create `app/Http/Resources/Za/ZaInvestmentAccountResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaInvestmentAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_type' => $this->account_type,
            'provider' => $this->provider,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'current_value' => $this->current_value,
            'tax_year' => $this->tax_year,
            'country_code' => 'ZA',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

Create `app/Http/Resources/Za/ZaHoldingResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaHoldingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'holdable_id' => $this->holdable_id,
            'holdable_type' => $this->holdable_type,
            'asset_type' => $this->asset_type,
            'security_name' => $this->security_name,
            'ticker' => $this->ticker,
            'isin' => $this->isin,
            'quantity' => $this->quantity,
            'cost_basis' => $this->cost_basis,
            'current_value' => $this->current_value,
            'open_quantity' => $this->additional['open_quantity'] ?? null,
            'open_lot_count' => $this->additional['open_lot_count'] ?? null,
        ];
    }
}
```

Create `app/Http/Resources/Za/ZaHoldingLotResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaHoldingLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'quantity_acquired' => (float) $this->quantity_acquired,
            'quantity_open' => (float) $this->quantity_open,
            'acquisition_cost_minor' => (int) $this->acquisition_cost_minor,
            'acquisition_cost_ccy' => $this->acquisition_cost_ccy,
            'acquisition_date' => $this->acquisition_date?->format('Y-m-d'),
            'disposed_at' => $this->disposed_at?->format('Y-m-d'),
            'notes' => $this->notes,
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/Za/ZaInvestmentController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Investment\CalculateCgtRequest;
use App\Http\Requests\Za\Investment\RecordHoldingDisposalRequest;
use App\Http\Requests\Za\Investment\StoreHoldingPurchaseRequest;
use App\Http\Requests\Za\Investment\StoreZaInvestmentAccountRequest;
use App\Http\Resources\Za\ZaHoldingLotResource;
use App\Http\Resources\Za\ZaHoldingResource;
use App\Http\Resources\Za\ZaInvestmentAccountResource;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Fynla\Packs\Za\Investment\ZaCgtCalculator;
use Fynla\Packs\Za\Models\ZaHoldingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter over the ZA pack's investment domain (WS 1.3c).
 *
 * Thin proxy: every method resolves a pack.za.investment* container binding
 * and delegates. No business logic here. Pack owns the calculations; app
 * owns HTTP + auth + validation.
 */
class ZaInvestmentController extends Controller
{
    public function __construct(
        private readonly ZaBaseCostTracker $lots,
        private readonly ZaCgtCalculator $cgt,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        /** @var InvestmentEngine $engine */
        $engine = app('pack.za.investment');

        $userHoldingIds = Holding::query()
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->pluck('id')
            ->all();

        $totalOpenCostMinor = 0;
        $lotCount = 0;
        if (! empty($userHoldingIds)) {
            $lotCount = (int) ZaHoldingLot::query()->whereIn('holding_id', $userHoldingIds)->where('quantity_open', '>', 0)->count();
            foreach ($userHoldingIds as $hid) {
                $totalOpenCostMinor += $this->lots->openCostBasisMinor((int) $hid);
            }
        }

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'wrappers' => $engine->getTaxWrappers(),
                'allowances' => $engine->getAnnualAllowances($taxYear),
                'open_lot_summary' => [
                    'total_open_cost_basis_minor' => $totalOpenCostMinor,
                    'lot_count' => $lotCount,
                ],
            ],
        ]);
    }

    public function listAccounts(Request $request): JsonResponse
    {
        $accounts = InvestmentAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('country_code', 'ZA')
            ->orderByDesc('current_value')
            ->get();

        return response()->json(['data' => ZaInvestmentAccountResource::collection($accounts)]);
    }

    public function storeAccount(StoreZaInvestmentAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['country_code'] = 'ZA';
        $data['country'] = 'ZA';
        $data['ownership_type'] = 'individual';

        $account = InvestmentAccount::create($data);

        return response()->json(['data' => new ZaInvestmentAccountResource($account)], 201);
    }

    public function listHoldings(Request $request): JsonResponse
    {
        $accountId = $request->query('account_id');

        $query = Holding::query()
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request, $accountId) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
                if ($accountId) {
                    $q->where('id', $accountId);
                }
            });

        $holdings = $query->get()->map(function (Holding $h) {
            $h->additional = [
                'open_quantity' => $this->lots->openQuantity((int) $h->id),
                'open_lot_count' => count($this->lots->openLots((int) $h->id)),
            ];
            return $h;
        });

        return response()->json(['data' => ZaHoldingResource::collection($holdings)]);
    }

    public function listLots(Request $request, int $holdingId): JsonResponse
    {
        // Authorisation: the holding must belong to the user via an InvestmentAccount.
        $owns = Holding::query()
            ->where('id', $holdingId)
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Holding not found'], 404);
        }

        $lots = ZaHoldingLot::query()
            ->where('holding_id', $holdingId)
            ->where('quantity_open', '>', 0)
            ->orderBy('acquisition_date')
            ->get();

        return response()->json(['data' => ZaHoldingLotResource::collection($lots)]);
    }

    public function storePurchase(StoreHoldingPurchaseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $owns = Holding::query()
            ->where('id', $data['holding_id'])
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Holding not found'], 404);
        }

        $lotId = $this->lots->recordPurchase(
            userId: $request->user()->id,
            holdingId: $data['holding_id'],
            quantity: (float) $data['quantity'],
            costMinor: (int) $data['cost_minor'],
            acquisitionDate: $data['acquisition_date'],
            notes: $data['notes'] ?? null,
        );

        return response()->json([
            'data' => [
                'lot_id' => $lotId,
                'open_cost_basis_minor' => $this->lots->openCostBasisMinor((int) $data['holding_id']),
                'open_quantity' => $this->lots->openQuantity((int) $data['holding_id']),
            ],
        ], 201);
    }

    public function recordDisposal(RecordHoldingDisposalRequest $request): JsonResponse
    {
        $data = $request->validated();

        $owns = Holding::query()
            ->where('id', $data['holding_id'])
            ->whereHasMorph('holdable', [InvestmentAccount::class], function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)->where('country_code', 'ZA');
            })
            ->exists();

        if (! $owns) {
            return response()->json(['message' => 'Holding not found'], 404);
        }

        $result = $this->lots->recordDisposal(
            userId: $request->user()->id,
            holdingId: $data['holding_id'],
            quantity: (float) $data['quantity'],
            disposalDate: $data['disposal_date'],
        );

        return response()->json(['data' => $result]);
    }

    public function calculateCgt(CalculateCgtRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wrapper = $data['wrapper_code'];
        $gain = (int) $data['gain_minor'];
        $taxYear = $data['tax_year'];

        $result = match ($wrapper) {
            'tfsa' => [
                'tax_due_minor' => 0,
                'exclusion_applied_minor' => 0,
                'included_minor' => 0,
                'marginal_rate' => 0.0,
                'note' => 'Tax-Free Savings Account: no CGT.',
            ],
            'endowment' => $this->cgt->calculateEndowmentCgt($gain, $taxYear),
            'discretionary' => $this->cgt->calculateDiscretionaryCgt(
                gainMinor: $gain,
                otherTaxableIncomeMinor: (int) $data['income_minor'],
                age: (int) $data['age'],
                taxYear: $taxYear,
            ),
        };

        return response()->json(['data' => $result]);
    }

    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
```

- [ ] **Step 6: Wire routes (covered in Task 3 — skip for now if running tasks linearly)**

If running tasks out of order, jump to Task 3 to add the routes, then come back.

- [ ] **Step 7: Run the tests until green**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaInvestmentControllerTest.php
```

Expected: all 7 tests pass once routes (Task 3) are in place.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaInvestmentController.php app/Http/Requests/Za/Investment/ app/Http/Resources/Za/ZaInvestment*.php app/Http/Resources/Za/ZaHolding*.php tests/Feature/Api/Za/ZaInvestmentControllerTest.php
git commit -m "feat(za-frontend): ZaInvestmentController + form requests + resources + tests (WS 1.3c)"
```

---

## Task 2: Backend — ZaExchangeControlController + Form Requests + Resource (TDD)

**Files:**
- Create: `app/Http/Controllers/Api/Za/ZaExchangeControlController.php`
- Create: `app/Http/Requests/Za/ExchangeControl/StoreTransferRequest.php`
- Create: `app/Http/Requests/Za/ExchangeControl/CheckApprovalRequest.php`
- Create: `app/Http/Resources/Za/ZaExchangeControlEntryResource.php`
- Test: `tests/Feature/Api/Za/ZaExchangeControlControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/Za/ZaExchangeControlControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['fynla.active_packs' => ['za', 'gb']]);
    $this->seed(ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'active_jurisdictions' => ['za'],
        'primary_jurisdiction' => 'za',
    ]);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/za/exchange-control/dashboard')->assertStatus(401);
});

it('returns SDA + FIA caps with consumed and remaining for current calendar year', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/za/exchange-control/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'calendar_year',
                'allowances' => [
                    'sda' => ['type', 'annual_limit', 'currency', 'description'],
                    'fia' => ['type', 'annual_limit', 'currency', 'description'],
                ],
                'consumed' => ['sda_minor', 'fia_minor', 'total_minor'],
                'remaining' => ['sda_minor', 'fia_minor'],
                'sarb_threshold_minor',
            ],
        ]);

    expect($response->json('data.allowances.sda.annual_limit'))->toBe(200_000_000); // R2m
    expect($response->json('data.allowances.fia.annual_limit'))->toBe(1_000_000_000); // R10m
});

it('records an SDA transfer and increments consumed', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'sda',
        'amount_minor' => 50_000_000, // R500,000
        'transfer_date' => '2026-04-15',
        'destination_country' => 'United Kingdom',
        'purpose' => 'Property purchase',
    ]);

    $response->assertStatus(201);
    $dashboard = $this->getJson('/api/za/exchange-control/dashboard')->json();
    expect($dashboard['data']['consumed']['sda_minor'])->toBe(50_000_000);
});

it('records an FIA transfer with AIT metadata', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'fia',
        'amount_minor' => 300_000_000, // R3m
        'transfer_date' => '2026-04-20',
        'destination_country' => 'United States',
        'purpose' => 'Investment portfolio diversification',
        'authorised_dealer' => 'Investec',
        'recipient_account' => 'US-IBAN-123',
        'ait_reference' => 'AIT-2026-0042',
        'ait_documents' => [
            'tax_clearance_issued' => true,
            'source_of_funds_documented' => true,
            'recipient_kyc_complete' => true,
            'dealer_notified' => true,
        ],
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('za_exchange_control_ledger', [
        'user_id' => $this->user->id,
        'allowance_type' => 'fia',
        'amount_minor' => 300_000_000,
        'ait_reference' => 'AIT-2026-0042',
    ]);
});

it('isolates calendar year consumption', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/za/exchange-control/transfers', [
        'allowance_type' => 'sda',
        'amount_minor' => 100_000_000,
        'transfer_date' => '2025-12-15',
    ])->assertStatus(201);

    $dashboard = $this->getJson('/api/za/exchange-control/dashboard?calendar_year=2026')->json();
    expect($dashboard['data']['consumed']['sda_minor'])->toBe(0);
});

it('checks approval requirement for what-if scenario', function () {
    Sanctum::actingAs($this->user);

    $small = $this->postJson('/api/za/exchange-control/check-approval', [
        'amount_minor' => 100_000_000, // R1m — under SDA
        'type' => 'investment',
    ]);
    $small->assertOk();
    expect($small->json('data.requires_approval'))->toBeFalse();

    $large = $this->postJson('/api/za/exchange-control/check-approval', [
        'amount_minor' => 1_500_000_000, // R15m — above SARB
        'type' => 'investment',
    ]);
    expect($large->json('data.requires_approval'))->toBeTrue();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaExchangeControlControllerTest.php
```

Expected: FAIL — controller does not exist.

- [ ] **Step 3: Create Form Requests**

Create `app/Http/Requests/Za/ExchangeControl/StoreTransferRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\ExchangeControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allowance_type' => ['required', Rule::in(['sda', 'fia'])],
            'amount_minor' => ['required', 'integer', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'destination_country' => ['nullable', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'authorised_dealer' => ['nullable', 'string', 'max:255'],
            'recipient_account' => ['nullable', 'string', 'max:255'],
            'ait_reference' => ['nullable', 'string', 'max:120'],
            'ait_documents' => ['nullable', 'array'],
            'ait_documents.tax_clearance_issued' => ['nullable', 'boolean'],
            'ait_documents.source_of_funds_documented' => ['nullable', 'boolean'],
            'ait_documents.recipient_kyc_complete' => ['nullable', 'boolean'],
            'ait_documents.dealer_notified' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

Create `app/Http/Requests/Za/ExchangeControl/CheckApprovalRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\ExchangeControl;

use Illuminate\Foundation\Http\FormRequest;

class CheckApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'gt:0'],
            'type' => ['required', 'string', 'max:50'],
        ];
    }
}
```

- [ ] **Step 4: Create Resource**

Create `app/Http/Resources/Za/ZaExchangeControlEntryResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaExchangeControlEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'calendar_year' => (int) $this->calendar_year,
            'allowance_type' => $this->allowance_type,
            'amount_minor' => (int) $this->amount_minor,
            'amount_ccy' => $this->amount_ccy,
            'destination_country' => $this->destination_country,
            'purpose' => $this->purpose,
            'authorised_dealer' => $this->authorised_dealer,
            'recipient_account' => $this->recipient_account,
            'ait_reference' => $this->ait_reference,
            'ait_documents' => $this->ait_documents,
            'transfer_date' => $this->transfer_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'country_code' => 'ZA',
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Api/Za/ZaExchangeControlController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\ExchangeControl\CheckApprovalRequest;
use App\Http\Requests\Za\ExchangeControl\StoreTransferRequest;
use App\Http\Resources\Za\ZaExchangeControlEntryResource;
use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter over the ZA pack's exchange control domain (WS 1.3c).
 *
 * Calendar-year keyed (NOT tax-year). Resolves pack.za.exchange_control
 * + pack.za.exchange_control.ledger via container.
 */
class ZaExchangeControlController extends Controller
{
    public function __construct(
        private readonly ZaExchangeControlLedger $ledger,
        private readonly ZaTaxConfigService $config,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $year = (int) $request->query('calendar_year', date('Y'));

        /** @var ExchangeControl $engine */
        $engine = app('pack.za.exchange_control');

        $allowances = $engine->getAnnualAllowances();

        $sdaConsumed = $this->ledger->sumConsumed($request->user()->id, $year, 'sda');
        $fiaConsumed = $this->ledger->sumConsumed($request->user()->id, $year, 'fia');

        $sdaCap = (int) ($allowances['sda']['annual_limit'] ?? 0);
        $fiaCap = (int) ($allowances['fia']['annual_limit'] ?? 0);

        $sarbThreshold = (int) $this->config->get('2026/27', 'excon.sarb_special_approval_threshold_minor', 0);

        return response()->json([
            'data' => [
                'calendar_year' => $year,
                'allowances' => $allowances,
                'consumed' => [
                    'sda_minor' => $sdaConsumed,
                    'fia_minor' => $fiaConsumed,
                    'total_minor' => $sdaConsumed + $fiaConsumed,
                ],
                'remaining' => [
                    'sda_minor' => max(0, $sdaCap - $sdaConsumed),
                    'fia_minor' => max(0, $fiaCap - $fiaConsumed),
                ],
                'sarb_threshold_minor' => $sarbThreshold,
            ],
        ]);
    }

    public function listTransfers(Request $request): JsonResponse
    {
        $year = (int) $request->query('calendar_year', date('Y'));

        $entries = ZaExchangeControlEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('calendar_year', $year)
            ->orderByDesc('transfer_date')
            ->get();

        return response()->json(['data' => ZaExchangeControlEntryResource::collection($entries)]);
    }

    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $year = (int) date('Y', strtotime($data['transfer_date']));

        $id = $this->ledger->record(
            userId: $request->user()->id,
            calendarYear: $year,
            allowanceType: $data['allowance_type'],
            amountMinor: (int) $data['amount_minor'],
            transferDate: $data['transfer_date'],
            destinationCountry: $data['destination_country'] ?? null,
            purpose: $data['purpose'] ?? null,
            authorisedDealer: $data['authorised_dealer'] ?? null,
            recipientAccount: $data['recipient_account'] ?? null,
            aitReference: $data['ait_reference'] ?? null,
            aitDocuments: $data['ait_documents'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $id,
                'calendar_year' => $year,
            ],
        ], 201);
    }

    public function checkApproval(CheckApprovalRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var ExchangeControl $engine */
        $engine = app('pack.za.exchange_control');

        return response()->json([
            'data' => [
                'requires_approval' => $engine->requiresApproval((int) $data['amount_minor'], $data['type']),
                'amount_minor' => (int) $data['amount_minor'],
                'type' => $data['type'],
            ],
        ]);
    }
}
```

- [ ] **Step 6: Run the tests until green (after Task 3 routes)**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaExchangeControlControllerTest.php
```

Expected: all 6 tests pass once routes are wired in Task 3.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaExchangeControlController.php app/Http/Requests/Za/ExchangeControl/ app/Http/Resources/Za/ZaExchangeControlEntryResource.php tests/Feature/Api/Za/ZaExchangeControlControllerTest.php
git commit -m "feat(za-frontend): ZaExchangeControlController + form requests + resource + tests (WS 1.3c)"
```

---

## Task 3: Wire routes for both controllers

**Files:**
- Modify: `routes/api.php` lines ~1242-1260

- [ ] **Step 1: Extend the `/api/za/*` group**

Edit `routes/api.php`. Inside the existing `Route::middleware(['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za'])->prefix('za')->as('za.')->group(function () {…})` block, after the existing `Route::prefix('savings')->…` sub-group, add:

```php
        Route::prefix('investments')->as('investments.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('accounts', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'listAccounts'])
                ->name('accounts.index');
            Route::post('accounts', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'storeAccount'])
                ->name('accounts.store');
            Route::get('holdings', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'listHoldings'])
                ->name('holdings.index');
            Route::get('holdings/{holdingId}/lots', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'listLots'])
                ->name('holdings.lots');
            Route::post('holdings/purchase', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'storePurchase'])
                ->name('holdings.purchase');
            Route::post('holdings/disposal', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'recordDisposal'])
                ->name('holdings.disposal');
            Route::post('cgt/calculate', [\App\Http\Controllers\Api\Za\ZaInvestmentController::class, 'calculateCgt'])
                ->name('cgt.calculate');
        });

        Route::prefix('exchange-control')->as('exchange-control.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\Za\ZaExchangeControlController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('transfers', [\App\Http\Controllers\Api\Za\ZaExchangeControlController::class, 'listTransfers'])
                ->name('transfers.index');
            Route::post('transfers', [\App\Http\Controllers\Api\Za\ZaExchangeControlController::class, 'storeTransfer'])
                ->name('transfers.store');
            Route::post('check-approval', [\App\Http\Controllers\Api\Za\ZaExchangeControlController::class, 'checkApproval'])
                ->name('check-approval');
        });
```

- [ ] **Step 2: Verify routes are registered**

```bash
php artisan route:list --path=za/investments
php artisan route:list --path=za/exchange-control
```

Expected: 8 investment routes, 4 exchange-control routes.

- [ ] **Step 3: Run both controller test suites — they should now pass**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaInvestmentControllerTest.php tests/Feature/Api/Za/ZaExchangeControlControllerTest.php
```

Expected: 13 passing.

- [ ] **Step 4: Commit**

```bash
git add routes/api.php
git commit -m "feat(za-frontend): wire /api/za/investments + /api/za/exchange-control routes (WS 1.3c)"
```

---

## Task 4: Vuex — replace `zaInvestment` placeholder with functional module

**Files:**
- Modify: `resources/js/store/modules/zaInvestment.js`

- [ ] **Step 1: Replace placeholder with functional module**

Overwrite `resources/js/store/modules/zaInvestment.js`:

```javascript
import zaInvestmentService from '@/services/zaInvestmentService';

/**
 * ZA Investment store (WS 1.3c). Mirrors zaSavings shape — namespaced,
 * snake_case API → camelCase state on mutation.
 */
const state = () => ({
  taxYear: null,
  wrappers: [],
  allowances: { tfsa: 0, discretionary: 0, endowment: 0 },
  openLotSummary: { totalOpenCostBasisMinor: 0, lotCount: 0 },
  accounts: [],
  holdings: [],
  lotsByHoldingId: {},
  cgtScenario: null,
  loading: false,
  error: null,
});

const getters = {
  taxYear: (s) => s.taxYear,
  wrappers: (s) => s.wrappers,
  allowances: (s) => s.allowances,
  openLotSummary: (s) => s.openLotSummary,
  accounts: (s) => s.accounts,
  holdings: (s) => s.holdings,
  lotsForHolding: (s) => (id) => s.lotsByHoldingId[id] || [],
  cgtScenario: (s) => s.cgtScenario,
  isLoading: (s) => s.loading,
  error: (s) => s.error,
};

const actions = {
  async fetchDashboard({ commit }, taxYear = null) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const payload = await zaInvestmentService.getDashboard(taxYear);
      commit('SET_DASHBOARD', payload.data);
    } catch (err) {
      commit('SET_ERROR', err.response?.data?.message || err.message);
      throw err;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchAccounts({ commit }) {
    const payload = await zaInvestmentService.listAccounts();
    commit('SET_ACCOUNTS', payload.data);
  },

  async storeAccount({ dispatch }, data) {
    const payload = await zaInvestmentService.storeAccount(data);
    await dispatch('fetchAccounts');
    return payload.data;
  },

  async fetchHoldings({ commit }, accountId = null) {
    const payload = await zaInvestmentService.listHoldings(accountId);
    commit('SET_HOLDINGS', payload.data);
  },

  async fetchLots({ commit }, holdingId) {
    const payload = await zaInvestmentService.listLots(holdingId);
    commit('SET_LOTS_FOR_HOLDING', { holdingId, lots: payload.data });
    return payload.data;
  },

  async storePurchase({ dispatch }, data) {
    const payload = await zaInvestmentService.storePurchase(data);
    await dispatch('fetchHoldings');
    await dispatch('fetchLots', data.holding_id);
    return payload.data;
  },

  async recordDisposal({ dispatch }, data) {
    const payload = await zaInvestmentService.recordDisposal(data);
    await dispatch('fetchHoldings');
    await dispatch('fetchLots', data.holding_id);
    return payload.data;
  },

  async calculateCgt({ commit }, data) {
    const payload = await zaInvestmentService.calculateCgt(data);
    commit('SET_CGT_SCENARIO', payload.data);
    return payload.data;
  },

  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  SET_DASHBOARD(state, data) {
    state.taxYear = data.tax_year;
    state.wrappers = data.wrappers || [];
    state.allowances = data.allowances || { tfsa: 0, discretionary: 0, endowment: 0 };
    state.openLotSummary = {
      totalOpenCostBasisMinor: data.open_lot_summary?.total_open_cost_basis_minor ?? 0,
      lotCount: data.open_lot_summary?.lot_count ?? 0,
    };
  },
  SET_ACCOUNTS(state, accounts) {
    state.accounts = accounts;
  },
  SET_HOLDINGS(state, holdings) {
    state.holdings = holdings;
  },
  SET_LOTS_FOR_HOLDING(state, { holdingId, lots }) {
    state.lotsByHoldingId = { ...state.lotsByHoldingId, [holdingId]: lots };
  },
  SET_CGT_SCENARIO(state, scenario) {
    state.cgtScenario = scenario;
  },
  SET_LOADING(state, v) {
    state.loading = v;
  },
  SET_ERROR(state, e) {
    state.error = e;
  },
  RESET(state) {
    state.taxYear = null;
    state.wrappers = [];
    state.allowances = { tfsa: 0, discretionary: 0, endowment: 0 };
    state.openLotSummary = { totalOpenCostBasisMinor: 0, lotCount: 0 };
    state.accounts = [];
    state.holdings = [];
    state.lotsByHoldingId = {};
    state.cgtScenario = null;
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

- [ ] **Step 2: Commit**

```bash
git add resources/js/store/modules/zaInvestment.js
git commit -m "feat(za-frontend): functional zaInvestment Vuex module (WS 1.3c)"
```

---

## Task 5: Vuex — replace `zaExchangeControl` placeholder with functional module

**Files:**
- Modify: `resources/js/store/modules/zaExchangeControl.js`

- [ ] **Step 1: Replace placeholder**

Overwrite `resources/js/store/modules/zaExchangeControl.js`:

```javascript
import zaExchangeControlService from '@/services/zaExchangeControlService';

/**
 * ZA Exchange Control store (WS 1.3c). Calendar-year keyed (NOT tax-year).
 */
const state = () => ({
  calendarYear: null,
  allowances: { sda: null, fia: null },
  consumed: { sdaMinor: 0, fiaMinor: 0, totalMinor: 0 },
  remaining: { sdaMinor: 0, fiaMinor: 0 },
  sarbThresholdMinor: 0,
  transfers: [],
  approvalCheck: null,
  loading: false,
  error: null,
});

const getters = {
  calendarYear: (s) => s.calendarYear,
  allowances: (s) => s.allowances,
  consumed: (s) => s.consumed,
  remaining: (s) => s.remaining,
  sarbThresholdMinor: (s) => s.sarbThresholdMinor,
  transfers: (s) => s.transfers,
  approvalCheck: (s) => s.approvalCheck,
  combinedThresholdBreached: (s) => s.consumed.totalMinor > s.sarbThresholdMinor,
  isLoading: (s) => s.loading,
  error: (s) => s.error,
};

const actions = {
  async fetchDashboard({ commit }, calendarYear = null) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const payload = await zaExchangeControlService.getDashboard(calendarYear);
      commit('SET_DASHBOARD', payload.data);
    } catch (err) {
      commit('SET_ERROR', err.response?.data?.message || err.message);
      throw err;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchTransfers({ commit }, calendarYear = null) {
    const payload = await zaExchangeControlService.listTransfers(calendarYear);
    commit('SET_TRANSFERS', payload.data);
  },

  async storeTransfer({ dispatch }, data) {
    const payload = await zaExchangeControlService.storeTransfer(data);
    await dispatch('fetchDashboard');
    await dispatch('fetchTransfers');
    return payload.data;
  },

  async checkApproval({ commit }, data) {
    const payload = await zaExchangeControlService.checkApproval(data);
    commit('SET_APPROVAL_CHECK', payload.data);
    return payload.data;
  },

  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  SET_DASHBOARD(state, data) {
    state.calendarYear = data.calendar_year;
    state.allowances = data.allowances || { sda: null, fia: null };
    state.consumed = {
      sdaMinor: data.consumed?.sda_minor ?? 0,
      fiaMinor: data.consumed?.fia_minor ?? 0,
      totalMinor: data.consumed?.total_minor ?? 0,
    };
    state.remaining = {
      sdaMinor: data.remaining?.sda_minor ?? 0,
      fiaMinor: data.remaining?.fia_minor ?? 0,
    };
    state.sarbThresholdMinor = data.sarb_threshold_minor ?? 0;
  },
  SET_TRANSFERS(state, transfers) {
    state.transfers = transfers;
  },
  SET_APPROVAL_CHECK(state, payload) {
    state.approvalCheck = payload;
  },
  SET_LOADING(state, v) {
    state.loading = v;
  },
  SET_ERROR(state, e) {
    state.error = e;
  },
  RESET(state) {
    state.calendarYear = null;
    state.allowances = { sda: null, fia: null };
    state.consumed = { sdaMinor: 0, fiaMinor: 0, totalMinor: 0 };
    state.remaining = { sdaMinor: 0, fiaMinor: 0 };
    state.sarbThresholdMinor = 0;
    state.transfers = [];
    state.approvalCheck = null;
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

- [ ] **Step 2: Commit**

```bash
git add resources/js/store/modules/zaExchangeControl.js
git commit -m "feat(za-frontend): functional zaExchangeControl Vuex module (WS 1.3c)"
```

---

## Task 6: Frontend services

**Files:**
- Create: `resources/js/services/zaInvestmentService.js`
- Create: `resources/js/services/zaExchangeControlService.js`

- [ ] **Step 1: Create the Investment service**

Create `resources/js/services/zaInvestmentService.js`:

```javascript
import api from './api';

/**
 * WS 1.3c — API wrapper for /api/za/investments/*. All wire values in minor units.
 */
const zaInvestmentService = {
  async getDashboard(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const response = await api.get('/za/investments/dashboard', { params });
    return response.data;
  },

  async listAccounts() {
    const response = await api.get('/za/investments/accounts');
    return response.data;
  },

  async storeAccount(data) {
    const response = await api.post('/za/investments/accounts', data);
    return response.data;
  },

  async listHoldings(accountId = null) {
    const params = accountId ? { account_id: accountId } : {};
    const response = await api.get('/za/investments/holdings', { params });
    return response.data;
  },

  async listLots(holdingId) {
    const response = await api.get(`/za/investments/holdings/${holdingId}/lots`);
    return response.data;
  },

  async storePurchase(data) {
    const response = await api.post('/za/investments/holdings/purchase', data);
    return response.data;
  },

  async recordDisposal(data) {
    const response = await api.post('/za/investments/holdings/disposal', data);
    return response.data;
  },

  async calculateCgt(data) {
    const response = await api.post('/za/investments/cgt/calculate', data);
    return response.data;
  },
};

export default zaInvestmentService;
```

- [ ] **Step 2: Create the Exchange Control service**

Create `resources/js/services/zaExchangeControlService.js`:

```javascript
import api from './api';

/**
 * WS 1.3c — API wrapper for /api/za/exchange-control/*. Calendar-year keyed.
 * All wire values in ZAR minor units.
 */
const zaExchangeControlService = {
  async getDashboard(calendarYear = null) {
    const params = calendarYear ? { calendar_year: calendarYear } : {};
    const response = await api.get('/za/exchange-control/dashboard', { params });
    return response.data;
  },

  async listTransfers(calendarYear = null) {
    const params = calendarYear ? { calendar_year: calendarYear } : {};
    const response = await api.get('/za/exchange-control/transfers', { params });
    return response.data;
  },

  async storeTransfer(data) {
    const response = await api.post('/za/exchange-control/transfers', data);
    return response.data;
  },

  async checkApproval(data) {
    const response = await api.post('/za/exchange-control/check-approval', data);
    return response.data;
  },
};

export default zaExchangeControlService;
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/services/zaInvestmentService.js resources/js/services/zaExchangeControlService.js
git commit -m "feat(za-frontend): zaInvestmentService + zaExchangeControlService (WS 1.3c)"
```

---

## Task 7: Router — add `/za/investments` and `/za/exchange-control` routes

**Files:**
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Add lazy imports near the existing `ZaSavingsDashboard` import (line ~110)**

Find the line `const ZaSavingsDashboard = () => import('@/views/ZA/ZaSavingsDashboard.vue');` and add immediately below:

```javascript
const ZaInvestmentDashboard = () => import('@/views/ZA/ZaInvestmentDashboard.vue');
const ZaExchangeControlDashboard = () => import('@/views/ZA/ZaExchangeControlDashboard.vue');
```

- [ ] **Step 2: Add route definitions after the existing `/za/savings` block (line ~692)**

After the closing brace of the `/za/savings` route entry, insert:

```javascript
  {
    path: '/za/investments',
    name: 'za-investments',
    component: ZaInvestmentDashboard,
    meta: {
      requiresAuth: true,
      requiresJurisdiction: 'za',
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'South Africa — Investments', path: '/za/investments' },
      ],
    },
  },
  {
    path: '/za/exchange-control',
    name: 'za-exchange-control',
    component: ZaExchangeControlDashboard,
    meta: {
      requiresAuth: true,
      requiresJurisdiction: 'za',
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'South Africa — Exchange Control', path: '/za/exchange-control' },
      ],
    },
  },
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(za-frontend): /za/investments + /za/exchange-control routes (WS 1.3c)"
```

---

## Task 8: Investment dashboard components

**Files:**
- Create: `resources/js/components/ZA/Investment/ZaInvestmentSummary.vue`
- Create: `resources/js/components/ZA/Investment/ZaInvestmentAccountsList.vue`
- Create: `resources/js/components/ZA/Investment/ZaInvestmentForm.vue`
- Create: `resources/js/components/ZA/Investment/ZaHoldingsList.vue`
- Create: `resources/js/components/ZA/Investment/ZaPurchaseModal.vue`
- Create: `resources/js/components/ZA/Investment/ZaDisposalModal.vue`
- Create: `resources/js/components/ZA/Investment/ZaCgtCalculatorCard.vue`
- Create: `resources/js/components/ZA/Investment/ZaCgtProjectionPanel.vue`
- Create: `resources/js/views/ZA/ZaInvestmentDashboard.vue`

- [ ] **Step 1: ZaInvestmentSummary.vue** — wrapper summary with allowance context

Create `resources/js/components/ZA/Investment/ZaInvestmentSummary.vue`:

```vue
<template>
  <section class="space-y-4">
    <header class="flex items-end justify-between">
      <div>
        <h1 class="text-3xl font-black text-horizon-700">Investments</h1>
        <p class="text-sm text-horizon-500 mt-1">
          Tax year {{ taxYear || '2026/27' }} — Tax-Free Savings Account, Discretionary, and Endowment wrappers.
        </p>
      </div>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg transition-colors"
        @click="$emit('add-account')"
      >
        Add account
      </button>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-for="w in wrappers" :key="w.code" class="card p-6">
        <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">{{ w.name }}</div>
        <div class="text-2xl font-black text-horizon-700 mt-2">
          {{ formatAllowance(w.code) }}
        </div>
        <p class="mt-2 text-xs text-horizon-500">{{ w.tax_treatment }}</p>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaInvestmentSummary',
  mixins: [zaCurrencyMixin],
  emits: ['add-account'],
  computed: {
    ...mapGetters('zaInvestment', ['wrappers', 'allowances', 'taxYear']),
  },
  methods: {
    formatAllowance(code) {
      const a = this.allowances?.[code];
      if (a === undefined || a === null) return '—';
      // PHP_INT_MAX (=9223372036854775807) signals "no cap" for discretionary/endowment
      if (a > 1_000_000_000_000) return 'No cap';
      return `${this.formatZARMinor(a)} annual cap`;
    },
  },
};
</script>
```

- [ ] **Step 2: ZaInvestmentAccountsList.vue** — list view

Create `resources/js/components/ZA/Investment/ZaInvestmentAccountsList.vue`:

```vue
<template>
  <section class="card p-6">
    <header class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Your accounts</h2>
      <span class="text-sm text-horizon-400">{{ accounts.length }} account{{ accounts.length === 1 ? '' : 's' }}</span>
    </header>
    <div v-if="isLoading" class="py-8 text-center text-horizon-400">Loading…</div>
    <div v-else-if="!accounts.length" class="py-8 text-center text-horizon-400">
      No South African investment accounts yet. Click "Add account" above to record one.
    </div>
    <ul v-else class="divide-y divide-light-gray">
      <li v-for="account in accounts" :key="account.id" class="py-3 flex items-center justify-between">
        <div>
          <div class="font-semibold text-horizon-700">
            {{ account.account_name || account.provider }}
          </div>
          <div class="text-xs text-horizon-400">
            <span :class="badgeClass(account.account_type)" class="inline-block px-2 py-0.5 rounded font-bold uppercase tracking-wide mr-2">
              {{ wrapperLabel(account.account_type) }}
            </span>
            {{ account.provider }}
          </div>
        </div>
        <div class="text-right">
          <div class="font-bold text-horizon-700">{{ formatZAR(account.current_value) }}</div>
        </div>
      </li>
    </ul>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaInvestmentAccountsList',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaInvestment', ['accounts', 'isLoading']),
  },
  methods: {
    wrapperLabel(code) {
      return { tfsa: 'TFSA', discretionary: 'Discretionary', endowment: 'Endowment' }[code] || code;
    },
    badgeClass(code) {
      return {
        tfsa: 'bg-spring-100 text-spring-700',
        discretionary: 'bg-horizon-100 text-horizon-700',
        endowment: 'bg-violet-100 text-violet-700',
      }[code] || 'bg-light-gray text-horizon-700';
    },
  },
};
</script>
```

- [ ] **Step 3: ZaInvestmentForm.vue** — add account modal

Create `resources/js/components/ZA/Investment/ZaInvestmentForm.vue`:

```vue
<template>
  <div class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Add a South African investment account</h2>
        <p class="text-sm text-horizon-500 mt-1">Tax year {{ form.tax_year }}</p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-2">Wrapper type</label>
          <div class="grid grid-cols-3 gap-2">
            <label
              v-for="opt in wrapperOptions"
              :key="opt.value"
              :class="[
                'border-2 rounded-lg px-3 py-2 cursor-pointer text-center',
                form.account_type === opt.value
                  ? 'border-raspberry-500 bg-raspberry-50 text-raspberry-700 font-bold'
                  : 'border-light-gray text-horizon-700 hover:border-horizon-300'
              ]"
            >
              <input type="radio" v-model="form.account_type" :value="opt.value" class="sr-only" />
              {{ opt.label }}
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Provider</label>
          <input
            v-model="form.provider"
            type="text"
            required
            placeholder="e.g. Allan Gray, Investec, Sygnia"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Account name (optional)</label>
          <input
            v-model="form.account_name"
            type="text"
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Current value (ZAR)</label>
          <input
            v-model.number="form.current_value"
            type="number"
            step="0.01"
            min="0"
            required
            class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500"
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold">Cancel</button>
          <button
            type="submit"
            :disabled="submitting || !form.account_type"
            class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50"
          >
            {{ submitting ? 'Saving…' : 'Save account' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaInvestmentForm',
  mixins: [zaCurrencyMixin],
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        account_type: 'discretionary',
        provider: '',
        account_name: '',
        current_value: null,
        tax_year: '2026/27',
      },
      submitting: false,
      wrapperOptions: [
        { value: 'tfsa', label: 'TFSA' },
        { value: 'discretionary', label: 'Discretionary' },
        { value: 'endowment', label: 'Endowment' },
      ],
    };
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

- [ ] **Step 4: ZaHoldingsList.vue** — table of holdings

Create `resources/js/components/ZA/Investment/ZaHoldingsList.vue`:

```vue
<template>
  <section class="card p-6">
    <header class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Holdings</h2>
      <button
        type="button"
        class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-4 py-2 rounded-lg text-sm"
        @click="$emit('record-purchase')"
      >
        Record purchase
      </button>
    </header>
    <div v-if="!holdings.length" class="py-8 text-center text-horizon-400">
      No holdings recorded yet. Add an account, then record purchases against it.
    </div>
    <table v-else class="w-full text-sm">
      <thead class="text-xs uppercase tracking-wide text-horizon-400 border-b border-light-gray">
        <tr>
          <th class="text-left py-2">Security</th>
          <th class="text-right py-2">Quantity</th>
          <th class="text-right py-2">Cost basis</th>
          <th class="text-right py-2">Open lots</th>
          <th class="text-right py-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="h in holdings" :key="h.id" class="border-b border-light-gray/50">
          <td class="py-3 font-semibold text-horizon-700">{{ h.security_name }}<span v-if="h.ticker" class="text-horizon-400 ml-2">{{ h.ticker }}</span></td>
          <td class="py-3 text-right text-horizon-700">{{ h.open_quantity ?? h.quantity }}</td>
          <td class="py-3 text-right text-horizon-700">{{ formatZAR(h.cost_basis) }}</td>
          <td class="py-3 text-right text-horizon-700">{{ h.open_lot_count ?? 0 }}</td>
          <td class="py-3 text-right">
            <button
              class="text-raspberry-500 hover:text-raspberry-700 font-semibold"
              @click="$emit('record-disposal', h)"
            >
              Dispose
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaHoldingsList',
  mixins: [zaCurrencyMixin],
  emits: ['record-purchase', 'record-disposal'],
  computed: {
    ...mapGetters('zaInvestment', ['holdings']),
  },
};
</script>
```

- [ ] **Step 5: ZaPurchaseModal.vue** — record a purchase

Create `resources/js/components/ZA/Investment/ZaPurchaseModal.vue`:

```vue
<template>
  <div class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record a purchase</h2>
        <p class="text-sm text-horizon-500 mt-1">Adds a new lot to the weighted-average ledger.</p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Holding</label>
          <select v-model="form.holding_id" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500">
            <option value="" disabled>Select a holding</option>
            <option v-for="h in holdings" :key="h.id" :value="h.id">{{ h.security_name }}<span v-if="h.ticker"> ({{ h.ticker }})</span></option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Units acquired</label>
          <input v-model.number="form.quantity" type="number" step="0.0001" min="0.0001" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Total cost (ZAR)</label>
          <input v-model.number="form.cost" type="number" step="0.01" min="0.01" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Acquisition date</label>
          <input v-model="form.acquisition_date" type="date" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Notes (optional)</label>
          <input v-model="form.notes" type="text" maxlength="500" class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold">Cancel</button>
          <button type="submit" :disabled="submitting" class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50">{{ submitting ? 'Saving…' : 'Save purchase' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaPurchaseModal',
  mixins: [zaCurrencyMixin],
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        holding_id: '',
        quantity: null,
        cost: null,
        acquisition_date: new Date().toISOString().slice(0, 10),
        notes: '',
      },
      submitting: false,
    };
  },
  computed: {
    ...mapGetters('zaInvestment', ['holdings']),
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          holding_id: this.form.holding_id,
          quantity: this.form.quantity,
          cost_minor: this.toMinorZAR(this.form.cost || 0),
          acquisition_date: this.form.acquisition_date,
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

- [ ] **Step 6: ZaDisposalModal.vue** — record a disposal

Create `resources/js/components/ZA/Investment/ZaDisposalModal.vue`:

```vue
<template>
  <div class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record a disposal</h2>
        <p class="text-sm text-horizon-500 mt-1">Draws down weighted-average cost basis across open lots.</p>
      </header>

      <div v-if="holding" class="mb-4 text-sm text-horizon-500">
        Holding: <strong>{{ holding.security_name }}</strong> — open quantity {{ holding.open_quantity ?? holding.quantity }}, cost basis {{ formatZAR(holding.cost_basis) }}.
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Units disposed</label>
          <input v-model.number="form.quantity" type="number" step="0.0001" min="0.0001" :max="maxUnits" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
          <p v-if="exceedsOpen" class="mt-1 text-sm text-violet-600">
            Warning: exceeds open quantity ({{ maxUnits }} available).
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Disposal date</label>
          <input v-model="form.disposal_date" type="date" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold">Cancel</button>
          <button type="submit" :disabled="submitting || exceedsOpen" class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50">{{ submitting ? 'Saving…' : 'Save disposal' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaDisposalModal',
  mixins: [zaCurrencyMixin],
  props: {
    holding: { type: Object, required: true },
  },
  emits: ['save', 'close'],
  data() {
    return {
      form: { quantity: null, disposal_date: new Date().toISOString().slice(0, 10) },
      submitting: false,
    };
  },
  computed: {
    maxUnits() {
      return this.holding?.open_quantity ?? this.holding?.quantity ?? 0;
    },
    exceedsOpen() {
      return (this.form.quantity || 0) > this.maxUnits + 1e-6;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        this.$emit('save', {
          holding_id: this.holding.id,
          quantity: this.form.quantity,
          disposal_date: this.form.disposal_date,
        });
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 7: ZaCgtCalculatorCard.vue** — what-if CGT calculator

Create `resources/js/components/ZA/Investment/ZaCgtCalculatorCard.vue`:

```vue
<template>
  <section class="card p-6">
    <header class="mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Capital Gains Tax — what-if</h2>
      <p class="text-xs text-horizon-400 mt-1">Estimates SA CGT on a one-off disposal. R40,000 annual exclusion applies to the discretionary path.</p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Wrapper</label>
        <select v-model="form.wrapper_code" class="w-full border border-light-gray rounded-lg px-3 py-2">
          <option value="discretionary">Discretionary</option>
          <option value="endowment">Endowment</option>
          <option value="tfsa">Tax-Free Savings Account</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Realised gain (ZAR)</label>
        <input v-model.number="form.gain" type="number" step="0.01" min="0" class="w-full border border-light-gray rounded-lg px-3 py-2" />
      </div>

      <template v-if="form.wrapper_code === 'discretionary'">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Other taxable income (ZAR)</label>
          <input v-model.number="form.income" type="number" step="0.01" min="0" class="w-full border border-light-gray rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Age</label>
          <input v-model.number="form.age" type="number" min="18" max="120" class="w-full border border-light-gray rounded-lg px-3 py-2" />
        </div>
      </template>
    </div>

    <button
      type="button"
      class="mt-4 bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2 rounded-lg"
      :disabled="!canCalculate || calculating"
      @click="calculate"
    >
      {{ calculating ? 'Calculating…' : 'Calculate' }}
    </button>

    <div v-if="cgtScenario" class="mt-4 p-4 bg-savannah-100 rounded-lg">
      <div class="text-sm text-horizon-500">Estimated tax due</div>
      <div class="text-2xl font-black text-horizon-700">{{ formatZARMinor(cgtScenario.tax_due_minor || 0) }}</div>
      <div v-if="cgtScenario.exclusion_applied_minor !== undefined" class="text-xs text-horizon-400 mt-1">
        Exclusion applied: {{ formatZARMinor(cgtScenario.exclusion_applied_minor) }}
        <span v-if="cgtScenario.marginal_rate"> · marginal rate {{ (cgtScenario.marginal_rate * 100).toFixed(1) }}%</span>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCgtCalculatorCard',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: {
        wrapper_code: 'discretionary',
        gain: null,
        income: null,
        age: 40,
      },
      calculating: false,
    };
  },
  computed: {
    ...mapGetters('zaInvestment', ['cgtScenario', 'taxYear']),
    canCalculate() {
      if (!this.form.gain) return false;
      if (this.form.wrapper_code === 'discretionary') {
        return this.form.income !== null && this.form.age >= 18;
      }
      return true;
    },
  },
  methods: {
    ...mapActions('zaInvestment', ['calculateCgt']),
    async calculate() {
      this.calculating = true;
      try {
        await this.calculateCgt({
          wrapper_code: this.form.wrapper_code,
          gain_minor: this.toMinorZAR(this.form.gain || 0),
          income_minor: this.toMinorZAR(this.form.income || 0),
          age: this.form.age,
          tax_year: this.taxYear || '2026/27',
        });
      } finally {
        this.calculating = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 8: ZaCgtProjectionPanel.vue** — YTD CGT projection (skeleton; see PRD open question 3)

Create `resources/js/components/ZA/Investment/ZaCgtProjectionPanel.vue`:

```vue
<template>
  <section class="card p-6">
    <header class="mb-2">
      <h2 class="text-xl font-bold text-horizon-700">Year-to-date CGT projection</h2>
      <p class="text-xs text-horizon-400 mt-1">Computed from your realised disposals in tax year {{ taxYear || '2026/27' }}.</p>
    </header>
    <div v-if="!hasDisposals" class="py-6 text-center text-horizon-400">
      No realised disposals recorded yet. Use the disposal action above to record one.
    </div>
    <div v-else class="text-2xl font-black text-horizon-700">
      {{ formatZARMinor(projectedTaxMinor) }}
    </div>
    <p v-if="hasDisposals" class="text-xs text-horizon-400 mt-1">Indicative — final CGT depends on full income and age at year end.</p>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCgtProjectionPanel',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaInvestment', ['taxYear']),
    // For v1, projection is empty — populated when disposal endpoint returns realised gains.
    // PRD will resolve whether this is computed client-side or server-side.
    hasDisposals() {
      return false;
    },
    projectedTaxMinor() {
      return 0;
    },
  },
};
</script>
```

- [ ] **Step 9: ZaInvestmentDashboard.vue** — page composition

Create `resources/js/views/ZA/ZaInvestmentDashboard.vue`:

```vue
<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto space-y-8 py-6">
      <ZaInvestmentSummary @add-account="showAccountForm = true" />

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <ZaCgtCalculatorCard />
        <ZaCgtProjectionPanel />
      </div>

      <ZaInvestmentAccountsList />
      <ZaHoldingsList @record-purchase="showPurchaseModal = true" @record-disposal="openDisposal" />

      <ZaInvestmentForm
        v-if="showAccountForm"
        @save="handleSaveAccount"
        @close="showAccountForm = false"
      />
      <ZaPurchaseModal
        v-if="showPurchaseModal"
        @save="handleSavePurchase"
        @close="showPurchaseModal = false"
      />
      <ZaDisposalModal
        v-if="disposingHolding"
        :holding="disposingHolding"
        @save="handleSaveDisposal"
        @close="disposingHolding = null"
      />
    </div>
  </AppLayout>
</template>

<script>
import { mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ZaInvestmentSummary from '@/components/ZA/Investment/ZaInvestmentSummary.vue';
import ZaInvestmentAccountsList from '@/components/ZA/Investment/ZaInvestmentAccountsList.vue';
import ZaInvestmentForm from '@/components/ZA/Investment/ZaInvestmentForm.vue';
import ZaHoldingsList from '@/components/ZA/Investment/ZaHoldingsList.vue';
import ZaPurchaseModal from '@/components/ZA/Investment/ZaPurchaseModal.vue';
import ZaDisposalModal from '@/components/ZA/Investment/ZaDisposalModal.vue';
import ZaCgtCalculatorCard from '@/components/ZA/Investment/ZaCgtCalculatorCard.vue';
import ZaCgtProjectionPanel from '@/components/ZA/Investment/ZaCgtProjectionPanel.vue';

export default {
  name: 'ZaInvestmentDashboard',
  components: {
    AppLayout,
    ZaInvestmentSummary,
    ZaInvestmentAccountsList,
    ZaInvestmentForm,
    ZaHoldingsList,
    ZaPurchaseModal,
    ZaDisposalModal,
    ZaCgtCalculatorCard,
    ZaCgtProjectionPanel,
  },
  data() {
    return {
      showAccountForm: false,
      showPurchaseModal: false,
      disposingHolding: null,
    };
  },
  async mounted() {
    await Promise.all([
      this.fetchDashboard(),
      this.fetchAccounts(),
      this.fetchHoldings(),
    ]);
  },
  methods: {
    ...mapActions('zaInvestment', ['fetchDashboard', 'fetchAccounts', 'fetchHoldings', 'storeAccount', 'storePurchase', 'recordDisposal']),
    async handleSaveAccount(data) {
      await this.storeAccount(data);
      this.showAccountForm = false;
      await this.fetchDashboard();
    },
    async handleSavePurchase(data) {
      await this.storePurchase(data);
      this.showPurchaseModal = false;
    },
    openDisposal(holding) {
      this.disposingHolding = holding;
    },
    async handleSaveDisposal(data) {
      await this.recordDisposal(data);
      this.disposingHolding = null;
    },
  },
};
</script>
```

- [ ] **Step 10: Commit**

```bash
git add resources/js/components/ZA/Investment/ resources/js/views/ZA/ZaInvestmentDashboard.vue
git commit -m "feat(za-frontend): SA Investment dashboard components + view (WS 1.3c)"
```

---

## Task 9: Exchange Control dashboard components

**Files:**
- Create: `resources/js/components/ZA/ExchangeControl/ZaSdaFiaGauges.vue`
- Create: `resources/js/components/ZA/ExchangeControl/ZaCombinedThresholdBanner.vue`
- Create: `resources/js/components/ZA/ExchangeControl/ZaTransferLedger.vue`
- Create: `resources/js/components/ZA/ExchangeControl/ZaTransferModal.vue`
- Create: `resources/js/components/ZA/ExchangeControl/ZaApprovalCheckCard.vue`
- Create: `resources/js/views/ZA/ZaExchangeControlDashboard.vue`

- [ ] **Step 1: ZaSdaFiaGauges.vue**

Create `resources/js/components/ZA/ExchangeControl/ZaSdaFiaGauges.vue`:

```vue
<template>
  <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="card p-6">
      <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Single Discretionary Allowance (SDA)</div>
      <div class="text-3xl font-black text-horizon-700 mt-2">
        {{ formatZARMinor(remaining.sdaMinor) }} remaining
      </div>
      <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
        <div :class="['h-full transition-all duration-500', sdaBarClass]" :style="{ width: sdaPct + '%' }" />
      </div>
      <div class="mt-2 text-xs text-horizon-400">
        {{ formatZARMinor(consumed.sdaMinor) }} used of {{ formatZARMinor(allowances.sda?.annual_limit || 0) }}
      </div>
      <p class="mt-3 text-xs text-horizon-500">{{ allowances.sda?.description }}</p>
    </div>

    <div class="card p-6">
      <div class="text-sm font-semibold text-horizon-400 uppercase tracking-wide">Foreign Investment Allowance (FIA)</div>
      <div class="text-3xl font-black text-horizon-700 mt-2">
        {{ formatZARMinor(remaining.fiaMinor) }} remaining
      </div>
      <div class="mt-4 h-2 bg-horizon-100 rounded-full overflow-hidden">
        <div :class="['h-full transition-all duration-500', fiaBarClass]" :style="{ width: fiaPct + '%' }" />
      </div>
      <div class="mt-2 text-xs text-horizon-400">
        {{ formatZARMinor(consumed.fiaMinor) }} used of {{ formatZARMinor(allowances.fia?.annual_limit || 0) }}
      </div>
      <p class="mt-3 text-xs text-horizon-500">{{ allowances.fia?.description }}</p>
    </div>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaSdaFiaGauges',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaExchangeControl', ['allowances', 'consumed', 'remaining']),
    sdaPct() {
      const cap = this.allowances.sda?.annual_limit || 0;
      if (!cap) return 0;
      return Math.min(100, (this.consumed.sdaMinor / cap) * 100);
    },
    fiaPct() {
      const cap = this.allowances.fia?.annual_limit || 0;
      if (!cap) return 0;
      return Math.min(100, (this.consumed.fiaMinor / cap) * 100);
    },
    sdaBarClass() {
      if (this.sdaPct >= 100) return 'bg-raspberry-500';
      if (this.sdaPct >= 75) return 'bg-violet-500';
      return 'bg-spring-500';
    },
    fiaBarClass() {
      if (this.fiaPct >= 100) return 'bg-raspberry-500';
      if (this.fiaPct >= 75) return 'bg-violet-500';
      return 'bg-spring-500';
    },
  },
};
</script>
```

- [ ] **Step 2: ZaCombinedThresholdBanner.vue**

Create `resources/js/components/ZA/ExchangeControl/ZaCombinedThresholdBanner.vue`:

```vue
<template>
  <div v-if="combinedThresholdBreached" class="card p-4 bg-violet-50 border border-violet-200">
    <div class="font-bold text-violet-700">SARB special approval required</div>
    <p class="text-sm text-horizon-600 mt-1">
      You've moved {{ formatZARMinor(consumed.totalMinor) }} offshore in {{ calendarYear }} — above the SARB combined threshold of {{ formatZARMinor(sarbThresholdMinor) }}. Further transfers this calendar year require special approval through your authorised dealer.
    </p>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaCombinedThresholdBanner',
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapGetters('zaExchangeControl', ['consumed', 'sarbThresholdMinor', 'combinedThresholdBreached', 'calendarYear']),
  },
};
</script>
```

- [ ] **Step 3: ZaTransferLedger.vue**

Create `resources/js/components/ZA/ExchangeControl/ZaTransferLedger.vue`:

```vue
<template>
  <section class="card p-6">
    <header class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Transfers in {{ calendarYear }}</h2>
      <button type="button" class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-4 py-2 rounded-lg text-sm" @click="$emit('record-transfer')">
        Record transfer
      </button>
    </header>
    <div v-if="!transfers.length" class="py-6 text-center text-horizon-400">
      No transfers recorded for this calendar year yet.
    </div>
    <table v-else class="w-full text-sm">
      <thead class="text-xs uppercase tracking-wide text-horizon-400 border-b border-light-gray">
        <tr>
          <th class="text-left py-2">Date</th>
          <th class="text-left py-2">Allowance</th>
          <th class="text-left py-2">Destination</th>
          <th class="text-left py-2">Purpose</th>
          <th class="text-right py-2">Amount</th>
          <th class="text-left py-2">AIT ref.</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in transfers" :key="t.id" class="border-b border-light-gray/50">
          <td class="py-3 text-horizon-700">{{ t.transfer_date }}</td>
          <td class="py-3"><span class="uppercase text-xs font-bold">{{ t.allowance_type }}</span></td>
          <td class="py-3 text-horizon-700">{{ t.destination_country || '—' }}</td>
          <td class="py-3 text-horizon-500">{{ t.purpose || '—' }}</td>
          <td class="py-3 text-right font-bold text-horizon-700">{{ formatZARMinor(t.amount_minor) }}</td>
          <td class="py-3 text-horizon-500">{{ t.ait_reference || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaTransferLedger',
  mixins: [zaCurrencyMixin],
  emits: ['record-transfer'],
  computed: {
    ...mapGetters('zaExchangeControl', ['transfers', 'calendarYear']),
  },
};
</script>
```

- [ ] **Step 4: ZaTransferModal.vue**

Create `resources/js/components/ZA/ExchangeControl/ZaTransferModal.vue`:

```vue
<template>
  <div class="fixed inset-0 bg-horizon-900/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
      <header class="mb-6">
        <h2 class="text-2xl font-black text-horizon-700">Record an offshore transfer</h2>
        <p class="text-sm text-horizon-500 mt-1">SDA covers any purpose under R2m. FIA above R2m requires SARS Approval for International Transfer (AIT).</p>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-2">Allowance type</label>
          <div class="grid grid-cols-2 gap-2">
            <label v-for="opt in allowanceOptions" :key="opt.value"
              :class="[
                'border-2 rounded-lg px-3 py-2 cursor-pointer text-center',
                form.allowance_type === opt.value
                  ? 'border-raspberry-500 bg-raspberry-50 text-raspberry-700 font-bold'
                  : 'border-light-gray text-horizon-700 hover:border-horizon-300'
              ]">
              <input type="radio" v-model="form.allowance_type" :value="opt.value" class="sr-only" />
              {{ opt.label }}
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (ZAR)</label>
          <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
          <p v-if="willExceedAllowance" class="mt-1 text-sm text-violet-600">
            Warning: this exceeds your remaining {{ form.allowance_type.toUpperCase() }} allowance ({{ formatZARMinor(remainingForType) }}).
          </p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Transfer date</label>
          <input v-model="form.transfer_date" type="date" required class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Destination country</label>
          <input v-model="form.destination_country" type="text" maxlength="120" class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Purpose</label>
          <input v-model="form.purpose" type="text" maxlength="255" class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-horizon-700 mb-1">Authorised dealer (optional)</label>
          <input v-model="form.authorised_dealer" type="text" maxlength="255" class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
        </div>

        <template v-if="form.allowance_type === 'fia'">
          <div>
            <label class="block text-sm font-semibold text-horizon-700 mb-1">AIT reference</label>
            <input v-model="form.ait_reference" type="text" maxlength="120" class="w-full border border-light-gray rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-horizon-700 mb-2">AIT documentation checklist</label>
            <div class="space-y-2 bg-savannah-100 p-3 rounded-lg">
              <label v-for="item in checklistItems" :key="item.key" class="flex items-start gap-2 text-sm text-horizon-700">
                <input type="checkbox" v-model="form.ait_documents[item.key]" class="mt-1" />
                <span>{{ item.label }}</span>
              </label>
            </div>
          </div>
        </template>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded-lg text-horizon-700 hover:bg-savannah-500 font-semibold">Cancel</button>
          <button type="submit" :disabled="submitting" class="bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2.5 rounded-lg disabled:opacity-50">{{ submitting ? 'Saving…' : 'Save transfer' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaTransferModal',
  mixins: [zaCurrencyMixin],
  emits: ['save', 'close'],
  data() {
    return {
      form: {
        allowance_type: 'sda',
        amount: null,
        transfer_date: new Date().toISOString().slice(0, 10),
        destination_country: '',
        purpose: '',
        authorised_dealer: '',
        ait_reference: '',
        ait_documents: {
          tax_clearance_issued: false,
          source_of_funds_documented: false,
          recipient_kyc_complete: false,
          dealer_notified: false,
        },
      },
      submitting: false,
      allowanceOptions: [
        { value: 'sda', label: 'SDA (≤ R2m)' },
        { value: 'fia', label: 'FIA (R2m – R10m, AIT required)' },
      ],
      checklistItems: [
        { key: 'tax_clearance_issued', label: 'Tax clearance certificate issued by SARS' },
        { key: 'source_of_funds_documented', label: 'Source-of-funds documentation prepared' },
        { key: 'recipient_kyc_complete', label: 'Recipient account KYC complete' },
        { key: 'dealer_notified', label: 'Authorised dealer notified' },
      ],
    };
  },
  computed: {
    ...mapGetters('zaExchangeControl', ['remaining']),
    amountMinor() {
      return this.toMinorZAR(this.form.amount || 0);
    },
    remainingForType() {
      return this.form.allowance_type === 'sda' ? this.remaining.sdaMinor : this.remaining.fiaMinor;
    },
    willExceedAllowance() {
      return this.amountMinor > this.remainingForType && this.remainingForType >= 0;
    },
  },
  methods: {
    async handleSubmit() {
      this.submitting = true;
      try {
        const payload = {
          allowance_type: this.form.allowance_type,
          amount_minor: this.amountMinor,
          transfer_date: this.form.transfer_date,
          destination_country: this.form.destination_country || null,
          purpose: this.form.purpose || null,
          authorised_dealer: this.form.authorised_dealer || null,
        };
        if (this.form.allowance_type === 'fia') {
          payload.ait_reference = this.form.ait_reference || null;
          payload.ait_documents = this.form.ait_documents;
        }
        this.$emit('save', payload);
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 5: ZaApprovalCheckCard.vue**

Create `resources/js/components/ZA/ExchangeControl/ZaApprovalCheckCard.vue`:

```vue
<template>
  <section class="card p-6">
    <header class="mb-4">
      <h2 class="text-xl font-bold text-horizon-700">Will I need approval?</h2>
      <p class="text-xs text-horizon-400 mt-1">Quick check whether a one-off offshore transfer needs SARS AIT or SARB special approval.</p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Amount (ZAR)</label>
        <input v-model.number="form.amount" type="number" step="0.01" min="0.01" class="w-full border border-light-gray rounded-lg px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-horizon-700 mb-1">Transfer type</label>
        <select v-model="form.type" class="w-full border border-light-gray rounded-lg px-3 py-2">
          <option value="investment">Investment</option>
          <option value="emigration">Emigration</option>
          <option value="gift">Gift</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <button type="button" class="mt-4 bg-raspberry-500 hover:bg-raspberry-600 text-white font-bold px-5 py-2 rounded-lg" :disabled="!form.amount || checking" @click="check">
      {{ checking ? 'Checking…' : 'Check requirement' }}
    </button>

    <div v-if="approvalCheck" class="mt-4 p-4 rounded-lg" :class="approvalCheck.requires_approval ? 'bg-violet-50' : 'bg-spring-50'">
      <div class="font-bold" :class="approvalCheck.requires_approval ? 'text-violet-700' : 'text-spring-700'">
        {{ approvalCheck.requires_approval ? 'Approval required' : 'No special approval needed' }}
      </div>
      <p class="text-sm text-horizon-600 mt-1">
        For {{ formatZARMinor(approvalCheck.amount_minor) }} ({{ approvalCheck.type }}).
        <span v-if="approvalCheck.requires_approval">Submit AIT through SARS or contact your authorised dealer for SARB approval.</span>
        <span v-else>Falls within the Single Discretionary Allowance.</span>
      </p>
    </div>
  </section>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import zaCurrencyMixin from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaApprovalCheckCard',
  mixins: [zaCurrencyMixin],
  data() {
    return {
      form: { amount: null, type: 'investment' },
      checking: false,
    };
  },
  computed: {
    ...mapGetters('zaExchangeControl', ['approvalCheck']),
  },
  methods: {
    ...mapActions('zaExchangeControl', ['checkApproval']),
    async check() {
      this.checking = true;
      try {
        await this.checkApproval({
          amount_minor: this.toMinorZAR(this.form.amount || 0),
          type: this.form.type,
        });
      } finally {
        this.checking = false;
      }
    },
  },
};
</script>
```

- [ ] **Step 6: ZaExchangeControlDashboard.vue** — page composition

Create `resources/js/views/ZA/ZaExchangeControlDashboard.vue`:

```vue
<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto space-y-6 py-6">
      <header>
        <h1 class="text-3xl font-black text-horizon-700">Exchange Control</h1>
        <p class="text-sm text-horizon-500 mt-1">
          Calendar year {{ calendarYear || currentYear }} — Single Discretionary Allowance (SDA) and Foreign Investment Allowance (FIA).
        </p>
      </header>

      <ZaCombinedThresholdBanner />
      <ZaSdaFiaGauges />
      <ZaApprovalCheckCard />
      <ZaTransferLedger @record-transfer="showTransferModal = true" />

      <ZaTransferModal v-if="showTransferModal" @save="handleSave" @close="showTransferModal = false" />
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import ZaSdaFiaGauges from '@/components/ZA/ExchangeControl/ZaSdaFiaGauges.vue';
import ZaCombinedThresholdBanner from '@/components/ZA/ExchangeControl/ZaCombinedThresholdBanner.vue';
import ZaTransferLedger from '@/components/ZA/ExchangeControl/ZaTransferLedger.vue';
import ZaTransferModal from '@/components/ZA/ExchangeControl/ZaTransferModal.vue';
import ZaApprovalCheckCard from '@/components/ZA/ExchangeControl/ZaApprovalCheckCard.vue';

export default {
  name: 'ZaExchangeControlDashboard',
  components: { AppLayout, ZaSdaFiaGauges, ZaCombinedThresholdBanner, ZaTransferLedger, ZaTransferModal, ZaApprovalCheckCard },
  data() {
    return { showTransferModal: false };
  },
  computed: {
    ...mapGetters('zaExchangeControl', ['calendarYear']),
    currentYear() {
      return new Date().getFullYear();
    },
  },
  async mounted() {
    await Promise.all([
      this.fetchDashboard(),
      this.fetchTransfers(),
    ]);
  },
  methods: {
    ...mapActions('zaExchangeControl', ['fetchDashboard', 'fetchTransfers', 'storeTransfer']),
    async handleSave(data) {
      await this.storeTransfer(data);
      this.showTransferModal = false;
    },
  },
};
</script>
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/ZA/ExchangeControl/ resources/js/views/ZA/ZaExchangeControlDashboard.vue
git commit -m "feat(za-frontend): SA Exchange Control dashboard components + view (WS 1.3c)"
```

---

## Task 10: Run full Pest suite + verify regression baseline

- [ ] **Step 1: Run new test suites first**

```bash
./vendor/bin/pest tests/Feature/Api/Za/ZaInvestmentControllerTest.php tests/Feature/Api/Za/ZaExchangeControlControllerTest.php
```

Expected: 13 passing.

- [ ] **Step 2: Run full suite**

```bash
./vendor/bin/pest
```

Expected: **2,716 passing** (2,703 baseline + 13 new), 4 pre-existing `ProtectionWorkflowTest` failures unchanged, 2 skipped, possibly 1 `InvestmentControllerTest` flake (not caused by this work).

- [ ] **Step 3: Verify route count**

```bash
php artisan route:list --path=za | wc -l
```

Expected: 18 ZA routes (6 savings + 8 investments + 4 exchange-control).

---

## Task 11: Browser smoke test in Playwright

**This task is NON-NEGOTIABLE.** "Browser tested" means clicking, filling, submitting, and verifying — not snapshots. Per CLAUDE.md § Testing.

- [ ] **Step 1: Build assets** (or rely on `npm run dev` if running)

If Vite is not running:
```bash
npm run dev &
```

- [ ] **Step 2: Log in as a ZA-active test user**

Use the Playwright MCP. Navigate to `http://localhost:8000/login`, fill `za-test@example.com` / `password` (per the ZA seeder — verify the user exists; if not, seed via `php artisan tinker` and create one with `active_jurisdictions=['za']`).

If MFA prompt appears, fetch the code:
```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','za-test@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code ?? 'none';"
```

Submit the code, land on the dashboard.

- [ ] **Step 3: Verify sidebar shows the new entries**

Click the "South Africa" sidebar section. Confirm three entries: "Savings (TFSA)", "Investments", "Exchange Control".

- [ ] **Step 4: Investment dashboard end-to-end**

Click "Investments". Verify:
- Page renders without console errors.
- Three wrapper cards show (TFSA / Discretionary / Endowment) with correct allowance text.
- "Add account" button opens the form modal.

Add a discretionary account:
- Wrapper: Discretionary
- Provider: "Investec"
- Current value: 100000

Submit. Verify:
- Modal closes.
- Account appears in the "Your accounts" list with the Discretionary badge and R 100 000.00.

Then in the CGT calculator:
- Wrapper: Discretionary
- Realised gain: 100000
- Income: 500000
- Age: 40

Click "Calculate". Verify the result panel shows a non-zero `tax_due_minor` formatted in ZAR.

- [ ] **Step 5: Exchange Control dashboard end-to-end**

Click "Exchange Control". Verify:
- Page renders without console errors.
- SDA + FIA gauges show R2 000 000 and R10 000 000 caps respectively, both at 0% used.
- Approval-check card present.
- Transfer ledger shows "No transfers recorded for this calendar year yet."

Click "Record transfer":
- Allowance type: SDA
- Amount: 500000
- Transfer date: today
- Destination country: "United Kingdom"
- Purpose: "Property purchase"

Submit. Verify:
- Modal closes.
- Ledger shows the new row.
- SDA gauge moves to 25% used (R500 000 of R2 000 000).
- Remaining SDA shows R1 500 000.

Then click the approval check:
- Amount: 1500000000 (R15m)
- Type: Investment

Click "Check requirement". Verify result panel shows "Approval required" with violet styling.

- [ ] **Step 6: Verify no regressions in Savings**

Click "Savings (TFSA)". Confirm the TFSA dashboard still loads and renders (regression check from WS 1.2b).

- [ ] **Step 7: Document the test run in handover**

Add a note to the eventual handover: "browser-verified end-to-end in Playwright on [date], all interactive paths working, no console errors".

---

## Task 12: Final consolidation commit + handover note

- [ ] **Step 1: Confirm all changes are committed**

```bash
git status
```

Expected: clean working tree (all WS 1.3c work in earlier task commits).

- [ ] **Step 2: Run pest suite one more time as the gate**

```bash
./vendor/bin/pest
```

Expected: 2,716 passing, no new failures, no regressions.

- [ ] **Step 3: Write handover to `April/April18Updates/handover-ws-1-3c.md`**

(Gitignored — for next agent.)

Use the WS 1.2b handover as the template. Cover:
1. TL;DR — what shipped
2. Patterns extended (sidebar entries, two new Vuex modules, two new dashboards)
3. Open questions still pending (cross-link to PRD and any items the PRD couldn't close)
4. What's next — WS 1.4d (Retirement frontend)

---

## Self-review checklist (run before handing to `/prd-writer`)

**1. Spec coverage**
- [x] WS 1.3c brief from `handover-ws-1-2b.md § 5` covered: sidebar entries, controllers, services, components, routes — all addressed.
- [x] Investment dashboard, holdings, CGT calculator, base-cost tracker view — all addressed.
- [x] SDA/FIA gauges, AIT checklist — all addressed.
- [ ] Wrapper code storage on `investment_accounts` — UNRESOLVED, deferred to PRD interview (open question 1).
- [ ] CGT YTD projection scope — UNRESOLVED, deferred to PRD interview (open question 3).
- [ ] AIT documents schema — UNRESOLVED, deferred to PRD interview (open question 4).

**2. Placeholder scan**
- One intentional placeholder: `ZaCgtProjectionPanel` always renders the empty-state. Documented in step 8 with the PRD pointer (open question 3). Will be amended when PRD resolves whether this is client- or server-computed.

**3. Type consistency**
- Frontend state shape mirrors API response shape (snake_case → camelCase on mutation), consistent with WS 1.2b precedent.
- Controller methods return `['data' => […]]` envelope, consistent with `ZaSavingsController` and `app/Http/CLAUDE.md` § "API Response Format".
- Form Request validation rules match the model fillable + pack engine signatures.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-04-18-ws-1-3c-za-investment-excon-frontend.md`.**

**MANDATORY NEXT STEP — `/prd-writer`.** Per `feedback_workflow_spec_plan_prd.md` memory rule: spec → plan → PRD → implement. Never skip the PRD. The codebase audit routinely surfaces 2–5 findings (this plan flags 6 known unresolved items as a starting point).

After PRD lands and amends this plan to "Status: Amended":

**1. Subagent-Driven (recommended)** — Dispatch a fresh subagent per task, review between tasks. `superpowers:subagent-driven-development`.

**2. Inline Execution** — Execute tasks in this session with batched checkpoints. `superpowers:executing-plans`.
