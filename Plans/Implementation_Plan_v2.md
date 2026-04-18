# Fynla International — Implementation Plan v2

**Date:** 16 April 2026
**Prepared for:** Chris Slater-Jones
**Supersedes:** phase_0_Implementation_Guide.md (v1.0), multi_country_architecture.md sections 15-16
**Retains:** SA_Research_and_Mapping.md (unchanged — the SA rules, rates, and product mappings remain authoritative)

---

## 1. What Changed and Why

The original plan called for a 12-week Phase 0 to physically extract UK code into a `packs/country-gb/` Composer package before any SA work could begin. Implementation revealed fundamental problems:

- **The compatibility alias pattern breaks Eloquent's type system.** Aliases (`App\Models\X extends Fynla\Packs\GB\Models\X`) create a child-extends-parent relationship. Eloquent creates instances of the class that initiates the query — pack-class instances don't pass type checks for alias classes. This caused 600+ test failures with no clean resolution.
- **18 weeks of refactoring before any SA product value.** Phase 0 (12 weeks) + Phase 1 (6 weeks) of pure restructuring for a solo developer is excessive risk for zero user-facing progress.
- **The file move violated its own discipline.** The plan specified "one module at a time, pure relocation PRs." The execution moved 293 files at once, making failures impossible to isolate.

### The new approach

- **UK code stays in `app/` untouched.** Zero risk to the live product. All 940+ tests pass from day 1.
- **SA code is built in `packs/country-za/` as a proper Composer package** implementing the 12 core contracts.
- **The contracts enforce the architecture boundary**, not file location.
- **Single domain (fynla.org)** serves all countries via geolocation.
- **Cross-border activates automatically** from user data, not manual opt-in.

### What we keep from the original work

The core infrastructure built in Workstreams A-D is valuable and stays:

| Asset | Location | Status |
|-------|----------|--------|
| 12 core contracts | `core/app/Core/Contracts/` | Done, tested |
| Money value object | `core/app/Core/Money/` | Done, tested |
| TaxYear abstraction | `core/app/Core/TaxYear/` | Done, tested |
| PackRegistry | `core/app/Core/Registry/` | Done, tested |
| ActiveJurisdictionMiddleware | `core/app/Core/Http/Middleware/` | Done, tested |
| Jurisdiction + UserJurisdiction models | `core/app/Core/Models/` | Done, tested |
| 108 core unit tests | `tests/Unit/Core/` | All passing |
| 10 ADRs | `docs/adr/` | Committed |
| Pack template | `packs/_template/` | Ready |
| Smoke test pack | `packs/country-xx-smoke/` | Ready |
| Jurisdictions migration | `database/migrations/` | Ready |
| User.jurisdictions() relationship | `app/Models/User.php` | Done |

### What we undo

The Workstream E file moves (293 files into `packs/country-gb/`, 290 compatibility aliases) are reverted. UK code returns to `app/` where it works, is tested, and poses zero risk.

---

## 2. Product Model

### 2.1 Single domain, geo-routed

Everything is served through **fynla.org**. There are no country-specific domains.

| Visitor | What they see |
|---------|--------------|
| Unauthenticated from SA IP | SA marketing pages, ZAR pricing, SA registration form |
| Unauthenticated from UK IP | UK marketing pages, GBP pricing, UK registration form |
| Unauthenticated from unsupported country | Country selector / waitlist page |
| Authenticated UK user | Pure UK app (current Fynla) |
| Authenticated SA user | Pure SA app |
| Authenticated user with foreign assets | Both country experiences + cross-border, activated automatically |

### 2.2 Registration is geo-determined

The user never selects a country. Geolocation determines the registration experience:

- **IP geolocation** (via a lightweight service like MaxMind GeoLite2 or Cloudflare headers) maps the visitor to a supported country code.
- The registration form is pre-configured for that country: correct currency, ID field type (NI number vs SA ID), compliance disclaimer (FCA vs FAIS), validation rules.
- On registration, `user_jurisdictions` is populated with the detected country as primary.
- The user can change their country from account settings if the geo-detection was wrong (e.g., VPN). This is a safety valve, not a standard flow.

### 2.3 Cross-border activates automatically from data

There is no "add another country" button. The user simply enters their assets, and the location they provide determines the jurisdiction.

**Every financial asset has a location.** When a user adds an asset, they tell us where it is:

- **Property:** The address includes the country — this already exists in the data model.
- **Everything else (pensions, investments, savings, protection, estate assets):** The add/edit form includes a **location field** where the user types a location. This is a simple text input with country resolution (e.g., typing "Cape Town" or "South Africa" resolves to ZA; typing "London" or "United Kingdom" resolves to GB). For the user's home country, this can be pre-filled and skipped.

The location field serves two purposes:
1. It tells the system which country's rules, tax treatment, and product types apply to this asset
2. It activates the cross-border layer when assets span multiple countries

**Examples of how this plays out:**

| User | Action | What happens |
|------|--------|-------------|
| UK user | Adds a retirement annuity, location: "Johannesburg" | System categorises it as an SA RA. ZA jurisdiction activates. SA retirement module appears. Cross-border section appears. |
| UK user | Adds a property, address in Cape Town | System reads country from address. ZA jurisdiction activates. SA estate duty now factors in. |
| SA user | Adds an investment account, location: "London" | System categorises it as a UK investment. GB jurisdiction activates. UK investment/ISA module appears. |
| UK user | Adds a pension, location: "Manchester" | Already GB — no change. Normal UK pension flow. |
| SA user | Adds a TFSA, no location needed | Default is their primary jurisdiction (ZA). Normal SA flow. |

**The key insight:** The user isn't thinking about jurisdictions or cross-border. They're just telling us where their stuff is, which is natural — you know where your pension is, where your property is, where your investments are held. The system does the rest.

**How it works technically:**

1. Every financial entity model gains a `country_code` field (nullable, defaults to user's primary jurisdiction).
2. Add/edit forms include a location input. For the user's home country, this is pre-filled and can be hidden or shown as a simple confirmation. For foreign assets, the user types the location.
3. A `CountryResolver` service maps location input to ISO country codes (using a lightweight geocoding lookup or a curated list of cities/countries).
4. On save, if the resolved country differs from the user's active jurisdictions, the new jurisdiction is added to `user_jurisdictions` with `auto_detected = true`.
5. The response includes the updated jurisdiction state. The frontend picks up the new modules on the next render.
6. The user sees a brief, non-blocking notification: "Your plan now includes South African financial considerations."

**Deactivation:** When all foreign assets are removed (deleted or location changed back to home country), the auto-detected jurisdiction is soft-deactivated (`deactivated_at` set). Cross-border features disappear. Data is preserved for audit.

**What the user never sees:** The word "jurisdiction." The phrase "add a country." A settings page for managing active countries. They just enter their financial life, and the app shapes itself around it.

### 2.4 What each country experience includes

**UK user (jurisdiction = GB):**
- Modules: Protection, Savings (ISA), Investment (GIA, VCT, EIS), Retirement (SIPP, workplace DC/DB, State Pension), Estate Planning (IHT, NRB/RNRB, trusts), Goals, Coordination
- Currency: GBP
- Tax year: 6 April - 5 April
- Disclaimer: FCA guidance framing
- Exactly what fynla.org serves today — no changes

**SA user (jurisdiction = ZA):**
- Modules: Protection (life, dread, disability, funeral), Savings (TFSA, fixed deposit, notice, money market), Investment (discretionary, endowment, offshore + exchange control), Retirement (RA, PF, PvF, preservation, Two-Pot, living/life annuity), Estate Planning (estate duty, donations tax, CGT on death), Goals, Coordination
- Currency: ZAR
- Tax year: 1 March - 28/29 February
- Disclaimer: FAIS guidance framing
- New SA-only modules: Two-Pot Tracker, Exchange Control Ledger, Reg 28 Monitor

**Dual user (jurisdictions = GB + ZA):**
- Both module sets, under distinct headers in the sidebar
- Cross-border section: worldwide estate view, combined net worth in primary currency, exchange control tracking for offshore transfers
- Currency: primary jurisdiction's currency by default, with per-asset currency shown
- Phase 3 additions: DTA classification, QROPS modelling, residency tracking

---

## 3. Architecture

### 3.1 What lives where

```
fynla/
  app/                          <-- UK code stays here (untouched)
    Agents/                     <-- UK module agents
    Services/{Module}/          <-- UK domain services (214 services)
    Http/Controllers/Api/       <-- UK API controllers
    Models/                     <-- UK + core models
    Constants/                  <-- UK tax defaults, estate defaults
    Traits/                     <-- Shared traits (Auditable, etc.)
    Observers/                  <-- UK observers

  core/app/Core/                <-- Country-agnostic infrastructure (exists)
    Contracts/                  <-- 12 interfaces every pack implements
    Money/                      <-- Money value object (integer minor-units)
    TaxYear/                    <-- Semantic tax year intervals
    Jurisdiction/               <-- Jurisdiction scoping, ActiveJurisdictions
    Registry/                   <-- PackRegistry, PackManifest
    Http/Middleware/             <-- ActiveJurisdictionMiddleware, EnsurePackEnabled
    Models/                     <-- Jurisdiction, UserJurisdiction
    Providers/                  <-- CoreServiceProvider

  packs/
    country-za/                 <-- SA pack (NEW — Composer package)
      composer.json
      src/
        Providers/ZaPackServiceProvider.php
        Tax/ZaTaxEngine.php, ZaTaxConfigService.php
        Retirement/ZaRetirementEngine.php, TwoPotTracker.php, ...
        Investment/ZaInvestmentEngine.php, TfsaTracker.php, ...
        Protection/ZaProtectionEngine.php
        Estate/ZaEstateEngine.php, EstateDutyCalculator.php, ...
        ExchangeControl/ZaExchangeControl.php, SdaTracker.php, ...
        Validation/ZaIdValidator.php, ZaBankingValidator.php
        Localisation/ZaLocalisation.php
        Billing/ZaPaymentProcessor.php
        Http/Controllers/Api/*.php
        Http/Requests/*.php
        Models/*.php
      database/
        migrations/             <-- za_* prefixed tables
        seeders/                <-- SA tax config, preview personas, life tables
      routes/api.php            <-- /api/za/* routes
      tests/
    country-xx-smoke/           <-- Synthetic smoke test pack (exists)
    _template/                  <-- Pack scaffold template (exists)

  resources/js/
    components/                 <-- UK components stay here
    components/ZA/              <-- SA components (NEW, lazy-loaded)
    store/modules/              <-- UK stores stay here
    store/modules/za/           <-- SA stores (NEW)
    services/                   <-- Shared + UK services
    services/za/                <-- SA API services (NEW)

  deploy/fynla-org/             <-- Single deployment target
```

### 3.2 How the service container resolves country-specific logic

The core `PackRegistry` knows which packs are enabled for the current user. Country-specific services are resolved through the container:

```php
// In ZaPackServiceProvider::register()
$this->app->bind('pack.za.tax', ZaTaxEngine::class);
$this->app->bind('pack.za.retirement', ZaRetirementEngine::class);
// ... all 12 contracts

// UK implementations are the existing services, bound as:
$this->app->bind('pack.gb.tax', \App\Services\Tax\TaxConfigService::class);
$this->app->bind('pack.gb.retirement', \App\Agents\RetirementAgent::class);
// ...
```

Controllers and services resolve the correct implementation:

```php
// In a jurisdiction-aware controller or service:
$taxEngine = app("pack.{$user->primaryJurisdictionCode()}.tax");
$liability = $taxEngine->calculateIncomeTax($income, $taxYear);
```

### 3.3 Routing

UK routes stay at their current paths (`/api/protection/*`, `/api/retirement/*`, etc.) — no breaking changes.

SA routes are mounted at `/api/za/*` by the ZA pack's service provider.

Cross-border routes (Phase 3) mount at `/api/global/*`.

The `ActiveJurisdictionMiddleware` gates SA and cross-border routes — a UK-only user cannot access `/api/za/*`.

### 3.4 Frontend jurisdiction routing

The session endpoint (`/api/core/session` or the existing auth response) returns the user's active jurisdictions. The Vue router conditionally registers routes:

```js
// After login, session returns: { active_jurisdictions: ['gb'], cross_border: false }
// or: { active_jurisdictions: ['za'], cross_border: false }
// or: { active_jurisdictions: ['gb', 'za'], cross_border: true }

if (session.active_jurisdictions.includes('za')) {
  const zaRoutes = await import('./routes/za.js');
  router.addRoute(zaRoutes.default);
}
```

SA Vue components live in `resources/js/components/ZA/` and are lazy-loaded — a UK-only user never downloads them.

The sidebar composition reads from the jurisdiction state:

```js
// store/modules/jurisdiction.js
getters: {
  sidebarModules(state) {
    let modules = [];
    if (state.active.includes('gb')) modules.push(...gbModules);
    if (state.active.includes('za')) modules.push(...zaModules);
    if (state.crossBorder) modules.push(...crossBorderModules);
    return modules;
  }
}
```

### 3.5 Database

**UK tables are unchanged.** No `gb_` prefix, no renames, no migration risk.

**SA tables use `za_` prefix** to avoid collisions:
- `za_tax_configurations`
- `za_retirement_fund_buckets`
- `za_exchange_control_ledger`
- `za_tfsa_contributions`
- `za_donations_register`
- `za_reg28_snapshots`
- `za_estate_duty_projections`
- etc.

**Core tables are unprefixed** (already exist or to be created):
- `jurisdictions` (exists — created by core migration)
- `user_jurisdictions` (exists — created by core migration)
- `tax_years` (to be created)
- `fx_rates` (to be created when cross-border activates)

**Shared tables gain a `country_code` column** where needed:
- Properties already have a `country` field — this serves as the jurisdiction signal
- Investment accounts, retirement funds, protection policies, savings accounts, estate assets gain a nullable `country_code` CHAR(2) column
- The column is nullable so existing UK records don't need backfilling (null = GB by convention during transition, explicit for all new records)
- This column is the source of truth for which country's rules apply to the asset, and the trigger for cross-border activation

### 3.6 Geolocation

**For unauthenticated visitors** (marketing pages, registration):
- Use Cloudflare `CF-IPCountry` header if behind Cloudflare, or MaxMind GeoLite2 DB for IP lookup
- Map to supported country codes (GB, ZA). Unknown → default experience or country selector
- Store detected country in session for the registration flow

**For authenticated users:**
- Jurisdiction comes from `user_jurisdictions`, not geolocation
- Geo is only used once at registration

**Implementation:** A lightweight `GeoLocationService` that returns a country code from the request. Used by the registration controller and the public page middleware. ~50 lines of code.

---

## 4. Phased Delivery

### Phase 0 — Foundation (2-3 weeks)

**Goal:** Wire up the jurisdiction infrastructure so the app is multi-country aware, without changing any UK behaviour.

**Workstream 0.1: Revert Workstream E**
- Remove `packs/country-gb/` directory (the moved UK files)
- Remove the 290 compatibility aliases from `app/`
- Restore original `app/` files from the main fynla repo
- Run `composer dump-autoload`
- Verify all 940+ tests pass
- This is the clean baseline

**Workstream 0.2: UK Pack Bindings (without moving files)**
- Create `app/Providers/GbPackServiceProvider.php`
- Bind existing UK services to the `pack.gb.*` keys:
  ```php
  $this->app->bind('pack.gb.tax', \App\Services\Tax\TaxConfigService::class);
  $this->app->bind('pack.gb.retirement', \App\Agents\RetirementAgent::class);
  // etc.
  ```
- Register with PackRegistry as the GB pack
- UK code stays in `app/`, behaviour unchanged
- Tests still pass — we're only adding bindings

**Workstream 0.3: Jurisdiction Infrastructure**
- Run the jurisdiction migrations (already created): `jurisdictions`, `user_jurisdictions`, `tax_years`
- Seed GB jurisdiction and backfill all existing users with `jurisdiction = GB, is_primary = true`
- Wire `ActiveJurisdictionMiddleware` into the API middleware stack (no-op for single-jurisdiction users — all current users are GB)
- Add `jurisdiction_id` nullable column to key models (Property already has `country`)
- Add session endpoint enhancement: return `active_jurisdictions` and `cross_border` flag

**Workstream 0.4: Geolocation Service**
- Implement `GeoLocationService` (IP → country code)
- Add geo-detection to public page middleware (stores detected country in session)
- Registration controller reads geo-detected country, sets jurisdiction on user creation
- Country selector as fallback for undetected/unsupported locations

**Workstream 0.5: Frontend Jurisdiction State**
- Add `jurisdiction` Vuex module (active jurisdictions, primary, cross-border flag)
- Session/auth response populates jurisdiction state
- Sidebar reads from jurisdiction state (currently only GB modules exist, so no visible change)
- Route guards check jurisdiction for future ZA routes

**Workstream 0.6: Location-Based Jurisdiction Detection**
- Add `country_code` column (nullable CHAR(2)) to key asset tables: investment_accounts, dc_pensions, db_pensions, protection policies, savings_accounts, estate assets. Property already has `country`.
- Create `CountryResolver` service: maps location text input to ISO country codes (curated city/country list + optional lightweight geocoding fallback)
- Add location input to add/edit forms for financial assets. Pre-filled with user's primary jurisdiction country — only needs attention when adding a foreign asset.
- Create `JurisdictionDetectionObserver` registered on all asset models
- On create/update, if `country_code` differs from user's active jurisdictions → add new jurisdiction to `user_jurisdictions` with `auto_detected = true`
- On delete, if no remaining assets in that jurisdiction → soft-deactivate with `deactivated_at`
- Return updated jurisdiction state in the API response so the frontend picks it up immediately

**Exit criteria:**
- All 940+ existing tests pass (UK behaviour unchanged)
- New tests for jurisdiction infrastructure pass
- Geo-detection works for UK and SA IPs
- A GB user who adds any asset with a South African location gets ZA added to their jurisdictions automatically
- Removing all SA assets deactivates ZA jurisdiction
- Smoke test pack (`country-xx-smoke`) loads alongside GB without errors

### Phase 1 — South Africa Pack (10-12 weeks)

**Goal:** Build the complete SA product. An SA user can register, enter their financial life, and get comprehensive planning.

The SA Research & Mapping document (SA_Research_and_Mapping.md) is the specification for this phase. Each workstream below maps to a section of that document.

**Workstream 1.1: SA Tax Engine (2 weeks)** [SA Research doc section 5]
- Create `packs/country-za/` as Composer package
- Implement `ZaTaxEngine` (implements `TaxEngine` contract):
  - 2026/27 SARS income tax brackets (7 bands)
  - Primary, secondary, tertiary rebates and thresholds
  - Section 11F retirement deduction (27.5% capped at R350k)
  - CGT at 40% inclusion rate, R40k annual exclusion
  - Dividends withholding tax (20%)
  - Interest exemption (R23,800 under-65, R34,500 65+)
  - Medical tax credits
  - Retirement lump sum tables (retirement + withdrawal)
  - Estate duty (20%/25% with R3.5m abatement)
  - Donations tax (20%/25% with R100k annual exemption)
- `ZaTaxConfigService` backed by `za_tax_configurations` table
- `ZaTaxConfigurationSeeder` with all 2026/27 values
- Full Pest test coverage of every calculation
- Section 10C tax-free annuity rollover tracking

**Amendment 17 April 2026 — decisions from Workstream 1.1 PRD codebase audit:**

*Contract extensions* (land in `core/app/Core/Contracts/TaxEngine.php` before Workstream 1.1 code begins; GB keeps no-op/table-specific overrides):
- `calculateLumpSumTax(int $amountMinor, string $taxYear, int $priorCumulativeMinor, string $tableType): array` — `$tableType` is `'retirement'` or `'withdrawal'`; ZA applies the cumulative-since-Oct-2007 table, GB stubs
- `calculateRetirementDeduction(int $grossMinor, string $taxYear, int $carryForwardMinor): array` — ZA applies Section 11F (27.5%, R350k cap, carry-forward); GB stubs
- `calculateDividendsWithholdingTax(int $amountMinor, string $taxYear, string $source): int` — `$source` is `'local'` or `'foreign'`; ZA returns 20% local, effective 20% via s10B (25/45 of gross) foreign
- `calculateMedicalCredits(int $mainPlusFirstDependant, int $additionalDependants, string $taxYear): int` — returns annual credit in cents; flat `getAnnualExemptions()` is too thin for SA's per-dependant rates
- `calculateCGT()` gains `array $options = []` — supports `['wrapper' => 'endowment']` for Workstream 1.3's 30% endowment flat rate

*Architecture:*
- `ZaTaxEngine` is a **pure calculator**. State (Section 11F carry-forward, Section 10C non-deductible pool) is held by dedicated thin trackers `ZaSection11fTracker` and `ZaSection10cTracker`, each owning its own table (`za_section_11f_carry_forward`, `za_section_10c_ledger`) in `packs/country-za/database/migrations/`. Engine takes state as a parameter. Satisfies ADR-003 and keeps the engine unit-testable without the database.
- `za_tax_configurations` stores **minor units (cents)** in **normalised rows** — `(tax_year, key_path, value_cents, effective_from, notes)`. Not JSON blob, not whole rands. The UK JSON-blob pattern predates ADR-005 and is not replicated.
- `getPersonalAllowance(string $taxYear, ?int $age = null)` returns the rebate-implied threshold for SA (R99,000 default / R148,217 age 65–74 / R165,689 age 75+); GB returns the flat allowance. Contract signature gains optional `$age`.
- `ZaTaxConfigurationSeeder` chains three inserts: (i) `$this->call(ZaJurisdictionSeeder::class)` first — inserts `{code:'za', name:'South Africa', currency:'ZAR', locale:'en_ZA', table_prefix:'za_'}` into `jurisdictions`; (ii) inserts the 2026/27 row into `tax_years` linked to the ZA jurisdiction (`calendar_type = 'tax_year'`); (iii) seeds `za_tax_configurations` rows.
- `PackManifest` for ZA uses `PackManifest::fromArray(['code'=>'za', 'name'=>'South Africa', 'currency'=>'ZAR', 'locale'=>'en_ZA', 'table_prefix'=>'za_', 'navigation'=>[...], 'routes'=>[...]])` — tax-year start/end is NOT on the manifest; it lives in `tax_years` per ADR-006.
- `ZaTaxConfigurationSeeder` SARS table format: stores **accumulated base values** from the published SARS tables (e.g. `R44,118 + 26% above R245,100` as `{rate:26, lower_bound_cents:24_510_000, accumulated_base_cents:4_411_800}`) — matches SARS wording and avoids rounding divergence from rate-only derivation.

*Cross-workstream dependencies:*
- Two-Pot savings-pot withdrawal (Workstream 1.4) uses `ZaTaxEngine::calculateIncomeTax($currentTaxableIncome + $withdrawalAmount)` and reports the delta as the withdrawal tax impact. No new method needed in 1.1.
- Endowment 30% flat CGT (Workstream 1.3) uses `calculateCGT($gain, $taxYear, ['wrapper' => 'endowment'])`.
- `UIF` and `SDL` are payroll taxes, explicitly **out of scope** for Workstream 1.1.

*Schema prerequisite (lands in Phase 0.3 addendum, not Phase 1):*
- `tax_years` table gains a `calendar_type` CHAR(16) column defaulting to `'tax_year'`. Needed so Workstream 1.3 can add calendar-year rows for SA SDA/FIA. See `April/April17Updates/Consolidated_Plan.md` § 5a.

See `April/April17Updates/PRD-za-tax-engine.md` (when written) for the full PRD.

**Workstream 1.2: SA Savings + TFSA** [SA Research doc section 7]

*Amendment 18 April 2026 — split into backend and frontend sub-workstreams after codebase audit revealed that SA has no frontend scaffold yet and the first SA-UI plan needs to establish lazy-load, routing, and jurisdiction-aware layout conventions that all of WS 1.2–1.7 frontend work will depend on.*

**Workstream 1.2a — Backend (1 week):**
- Define `core/app/Core/Contracts/SavingsEngine.php` (13th contract — closes the "Savings is the odd-one-out" gap; mirrors WS 1.1 FR-M1 prep-PR pattern)
- GB-side stub: extend `App\Services\Savings\ISATracker` (or a sibling class) to `implements SavingsEngine` with ZA-only methods stubbed
- `packs/country-za/src/Savings/ZaSavingsEngine implements SavingsEngine` — pure calculator: TFSA penalty, interest-tax-with-exemption, emergency fund target
- `ZaTfsaContributionTracker` — thin persistence over `za_tfsa_contributions` (keyed by `user_id` or `beneficiary_id` for minor TFSAs)
- `ZaEmergencyFundCalculator` — SA heuristic (3–6 months, single-earner → 6, UIF-ineligible → +1)
- `za_tfsa_contributions` table: `user_id`, nullable `beneficiary_id` (FK to `family_members` for minor TFSAs), `savings_account_id`, `tax_year`, `amount_minor`, `amount_ccy` (default `'ZAR'`), `source_type` (`contribution` | `transfer_in`), `contribution_date`, `notes`
- `savings_accounts` gains four nullable fields: `is_tfsa`, `tfsa_subscription_year`, `tfsa_subscription_amount_minor` + `tfsa_subscription_amount_ccy`, `tfsa_lifetime_contributed_minor` + `tfsa_lifetime_contributed_ccy` (follows WS 0.6 shadow-column pattern)
- Tax-config seeder additions: `tfsa.annual_limit_minor` (R46,000), `tfsa.lifetime_limit_minor` (R500,000), `tfsa.over_contribution_penalty_bps` (4000), `endowment.income_tax_rate_bps` (3000), `endowment.restriction_period_years` (5). **The endowment CGT rate stays at its existing key `cgt.endowment_wrapper_rate_bps`** — no duplicate seeded.
- Container bindings: `pack.gb.savings`, `pack.za.savings`, `pack.za.tfsa.tracker`, `pack.za.savings.emergency_fund`
- Full Pest coverage including minor-TFSA (parent + child separate caps) integration test

**Workstream 1.2b — Frontend (follow-up plan, 1 week):**
- SA frontend scaffold: `resources/js/components/ZA/` directory, ZA route lazy-loading, jurisdiction-aware sidebar composition, ZA Vuex module organisation (this is the foundation plan all later SA-frontend workstreams build on)
- Vue components: TFSA dashboard, contribution tracker, SA savings forms, SA emergency fund gauge
- Savings agent coordination (SA-aware aggregation for the dashboard)

**Workstream 1.3: SA Investment + Exchange Control** [SA Research doc sections 8, 13.2]

*Amendment 18 April 2026 — split into backend-only 1.3a/1.3b plus a deferred 1.3c frontend, mirroring the WS 1.2a/b pattern. Reg 28 Monitor moves to WS 1.4 (Retirement) because Reg 28 applies to retirement funds (RA / PF / PvF / Preservation), not to the discretionary / endowment / TFSA wrappers that `ZaInvestmentEngine` represents — confirmed by SA Research § 13.3 which locates Reg 28 inside the retirement-fund look-through. This also matches `SA_Research_and_Mapping.md` line 432.*

**Workstream 1.3a — Investment backend (1 week):**
- `ZaInvestmentEngine implements InvestmentEngine` — discretionary (unit trusts / ETFs), endowment (section 29A), TFSA-routing. Delegates interest tax to `ZaSavingsEngine` (shipped WS 1.2a).
- `ZaCgtCalculator` — discretionary (40% inclusion × marginal rate, R40,000 annual exclusion) + endowment (30% flat, no exclusion).
- `ZaBaseCostTracker` — weighted-average lot ledger over `za_holding_lots`. On disposal, writes back updated cost basis to the main-app `holdings` row so the main-app record stays in sync with the lot ledger.
- GB-side stub: `App\Services\Investment\UkInvestmentEngine implements InvestmentEngine` at `pack.gb.investment`.
- Container bindings: `pack.gb.investment`, `pack.za.investment`, `pack.za.investment.cgt`, `pack.za.investment.lot_tracker`.

**Workstream 1.3b — Exchange Control backend (1 week):**
- `ZaExchangeControl implements ExchangeControl`: SDA (R2m/calendar year), FIA (R10m/calendar year).
- `za_exchange_control_ledger` table keyed by CALENDAR year (not tax year).
- AIT workflow stubs (document checklist, tax compliance status).

**Workstream 1.3c — Frontend (follow-up plan, 1 week):** Investment dashboard, exchange control widget. Pairs with the SA-frontend scaffold from WS 1.2b.

**Workstream 1.4: SA Retirement (2 weeks)** [SA Research doc section 9]
- `ZaRetirementEngine`: RA, Pension Fund, Provident Fund, Preservation Fund
- Two-Pot system: savings (1/3), retirement (2/3), vested components
  - `za_retirement_fund_buckets` table
  - Contribution split service
  - Savings pot withdrawal simulator (marginal tax projection)
- Compulsory annuitisation rules (1/3 lump + 2/3 annuity, R165k de minimis)
- Living annuity mechanics (2.5%-17.5% drawdown band)
- Life annuity with Section 10C exemption
- Section 11F deduction carry-forward tracking
- SASSA Old Age Grant data field
- **Reg 28 Monitor (moved from WS 1.3 per 18 April 2026 split):** look-through asset allocation on retirement fund holdings, breach detection (30% offshore, 75% equity, 25% property, 15% private equity, 5% single-entity), `reg28_snapshots` table per SA Research § 13.3.
- Vue components: retirement dashboard, Two-Pot tracker, annuity planner, Reg 28 compliance view

**Workstream 1.5: SA Protection (1 week)** [SA Research doc section 6]
- `ZaProtectionEngine`: life, dread disease (severity tiers), disability (lump + income), funeral
- Coverage calculators: life (capitalise dependants' need + bond + education + estate costs), income protection (75% gross cap), dread (1-3x salary)
- Beneficiary model: estate, spouse, nominated individual, testamentary trust, inter vivos trust
- Vue components: protection dashboard, coverage gap analysis, policy forms

**Workstream 1.6: SA Estate Planning (1.5 weeks)** [SA Research doc section 10]
- `ZaEstateEngine`: estate duty (20%/25%), CGT on death (R300k exclusion, 40% inclusion)
- Executor fees (3.5% + 6% income + VAT), Master's fees
- Donations tax register with R100k annual exemption
- Spousal rollover (R3.5m abatement portability)
- Liquidity test: project whether cash covers duty + admin + debts
- Will/guardian/testamentary trust data capture
- Vue components: estate dashboard, duty calculator, donations tracker

**Workstream 1.7: SA Goals, Coordination, Personas (1.5 weeks)** [SA Research doc sections 11, 12, 3.4]
- Goals: SA defaults (bond deposit norms, SA tertiary fees, emigration life event, retrenchment event)
- Coordination: SA-aware aggregation (tri-bucketed retirement, onshore/offshore split, TFSA de-duplication)
- Preview personas (6): young professional, young family, peak earners, pre-retiree, retiree, expat
- `ZaPreviewUserSeeder`, `ZaActuarialLifeTablesSeeder` (Stats SA / ASSA data)
- SA onboarding flow
- Full Playwright testing of every SA persona journey

**Workstream 1.8: SA Localisation + Compliance (1 week)** [SA Research doc sections 17, 18]
- `ZaLocalisation`: ZAR formatting (R 1 234 567.89), DD MMM YYYY dates, en-ZA locale
- SA ID validator (13-digit Luhn with DOB + citizenship extraction)
- Banking validator (universal branch code + account number)
- FAIS disclaimer component (every module landing page)
- POPIA privacy notice
- SA-specific copy and terminology throughout

**Exit criteria:**
- All SA modules functional and tested
- Full Playwright journey for every SA preview persona
- All 940+ UK tests still pass (no UK regression)
- SA test suite green (target: 400+ SA-specific tests)
- SA and GB packs coexist (one codebase, one deployment)
- Auto-detection: UK user adding SA property triggers ZA activation
- Geo-routed registration: SA IP → SA registration flow

### Phase 2 — Cross-Border + Polish (6 weeks, can be deferred)

- Cross-border pack (`packs/cross-border/`): worldwide estate view, combined net worth, FX rates
- DTA classification (UK-SA treaty as first bilateral)
- QROPS modelling (UK pension → SA)
- Residency tracking (SRT for UK, physical presence for SA)
- Currency conversion display (show assets in primary + original currency)
- Mobile app: geo-configured single bundle (not separate apps)
- Performance: bundle analysis, SA lazy-loading verification
- Production deployment and monitoring

### Phase 3+ — Additional Countries

Each new country follows the same pattern:
1. Research document (like SA_Research_and_Mapping.md)
2. `packs/country-xx/` implementing the 12 contracts
3. Country-specific Vue components in `resources/js/components/XX/`
4. Seeders, personas, life tables
5. Auto-detection observers for country-specific products
6. No changes to core or other country packs

---

## 5. Key Technical Decisions

### 5.1 UK code stays in `app/`

**Rationale:** Moving 293 files, creating 290 aliases, and debugging cascade failures costs 12+ weeks and risks the live product. The contracts provide the architecture boundary. File location is an organisational concern that can be addressed later if the team grows.

**Trade-off:** The codebase isn't perfectly symmetrical (UK in `app/`, SA in `packs/`). For a solo developer with 2 countries, pragmatism beats purity.

**Graduation path:** If a future country team needs UK code in a pack, it can be extracted gradually, one module at a time, after SA is live and stable.

### 5.2 No table renames for UK

**Rationale:** Renaming `savings_accounts` to `gb_savings_accounts` across the schema is high-risk for zero immediate benefit. SA tables use `za_` prefix from the start. UK tables can be prefixed later if needed (Phase 3+).

### 5.3 Geolocation, not multi-domain

**Rationale:** Single domain simplifies deployment, SSL, cookies, session management, SEO, and mobile app configuration. Geolocation for initial country detection is lightweight and well-supported.

### 5.4 Automatic cross-border detection

**Rationale:** Users don't think in terms of "jurisdictions." They think in terms of "I have a property in Cape Town" or "I have a UK pension." The system should adapt to their reality, not require them to configure it.

### 5.5 Single deployment

**Rationale:** fynla.org is one Laravel app, one MySQL database, one Vite build. SA pack code ships alongside UK code. The middleware and router ensure users only access what they're entitled to. This is simpler to deploy, monitor, and debug than separate environments per country.

---

## 6. Timeline Summary

| Phase | Duration | Cumulative | Key Deliverable |
|-------|----------|-----------|-----------------|
| 0: Foundation | 2-3 weeks | 3 weeks | Jurisdiction infrastructure, geo-detection, auto-detection observers. UK unchanged. |
| 1: South Africa | 10-12 weeks | 15 weeks | Complete SA product — all 6 modules + exchange control + Two-Pot + TFSA |
| 2: Cross-border | 6 weeks | 21 weeks | DTA, QROPS, worldwide estate, FX, mobile |
| 3+: Next country | 8-10 weeks each | — | Ireland, Australia, UAE, NZ... |

**vs. original plan:** SA product live in ~15 weeks instead of 28+. That's 13 weeks saved — over 3 months.

---

## 7. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Geolocation misidentifies country | M | L | Fallback country selector in settings; jurisdiction changeable post-registration |
| Auto-detection triggers incorrectly (false positive) | L | M | Conservative detection rules; user notification with "dismiss" option; easy undo |
| SA tax rules change mid-build (2027 Budget) | M | M | All rates in seeder, never hardcoded; annual refresh process same as UK |
| Two-Pot system edge cases (divorce, housing loans) | M | L | Track SARS/FSCA guidance; model core cases, flag edge cases as "consult adviser" |
| Performance with two packs loaded | L | M | SA components lazy-loaded; bundle analysis in Phase 1.8; SA routes only for SA users |
| UK regression from jurisdiction changes | L | H | Jurisdiction middleware is no-op for single-GB users; full UK regression suite runs on every PR |

---

## 8. Acceptance Checklist (Phase 0 + Phase 1 combined)

- [ ] All 940+ existing UK Pest tests pass
- [ ] SA test suite green (400+ tests)
- [ ] Architecture tests pass: no pack references another pack; core has no country-specific knowledge
- [ ] Full Playwright journey for each UK preview persona (no regression)
- [ ] Full Playwright journey for each SA preview persona
- [ ] Geo-detection: SA IP sees SA registration, UK IP sees UK registration
- [ ] Auto-detection: UK user adds SA property → ZA jurisdiction activates → SA modules appear
- [ ] Auto-detection: removing all SA assets → ZA jurisdiction deactivates → SA modules disappear
- [ ] Single deployment to fynla.org serves both country experiences
- [ ] SA user sees zero UK-specific terminology (no ISA, no SIPP, no IHT, no NRB)
- [ ] UK user sees zero SA-specific terminology (no TFSA, no Two-Pot, no estate duty, no SDA)
- [ ] Cross-border notification is non-blocking and dismissable
- [ ] SA compliance: FAIS disclaimer on every module page
- [ ] All SA tax calculations verified against SARS 2026/27 tables
