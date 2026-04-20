---
title: WS 1.5b — SA Protection Module Design
workstream: 1.5b
date: 2026-04-20
status: draft
authors: [Claude Opus 4.7, CSJ]
predecessors:
  - 2026-04-17-ws-1-1-za-tax-engine-design
  - 2026-04-18-ws-1-2b-za-frontend-foundation-design
  - 2026-04-19-ws-1-3c-za-investment-frontend-design
  - 2026-04-20-ws-1-4d-za-retirement-frontend-design
---

# WS 1.5b — SA Protection: Design

## Sources

- **Spec:** `Plans/SA_Research_and_Mapping.md` § 6 (Module mapping: Protection) — product catalogue, tax treatment, coverage calculators, data model changes.
- **Workstream scope:** `Plans/Implementation_Plan_v2.md` § "Workstream 1.5: SA Protection (1 week)" — engine, coverage calculators, beneficiary model, Vue components.
- **Backend state:** `packs/country-za/src/Protection/ZaProtectionEngine.php` exists (3 methods: `getAvailablePolicyTypes`, `calculateCoverageNeeds`, `getPolicyTaxTreatment`). No controller, routes, form requests, resources, or models shipped yet.
- **Frontend state:** `resources/js/store/modules/zaProtection.js` is a placeholder scaffold from WS 1.2b (to be replaced).
- **Patterns reused:** WS 1.2b sidebar data-driven approach, WS 1.3c service baseURL + `response.data` convention, WS 1.4d 3-tab page structure.
- **Open tech debt to address:** `tech-debt-report.md` W1 (shared DialogContainer for modal a11y across WS 1.2b/1.3c/1.4d/1.5b) and W2 (`toMinorZAR` reuse across 15 existing inline sites).

## Decisions locked via brainstorm (2026-04-20 session 3)

| # | Question | Decision |
|---|----------|----------|
| Q1 | Scope boundary | **A — Full-stack WS 1.5b** (backend controller + routes + form requests + resources + pack migrations + Vue + Vuex + tests). Matches WS 1.3c/1.4d precedent. |
| Q2 | Data model | **A — Fresh ZA-native pack tables** (`za_protection_policies` + `za_protection_beneficiaries`). Zero UK churn. UK's split `life_insurance_policies` / `income_protection_policies` / `protection_profiles` tables untouched. |
| Q3 | Information architecture | **A — Single `/za/protection` page with 3 tabs** (Policies, Coverage gap, Beneficiaries). Matches WS 1.4d convention. |
| Q4 | Coverage-gap UX inputs | **A — Auto-pull from app state** (IncomeSource, liabilities, HouseholdMember, policy totals). No user-editable assumptions form. Empty-state deep-links to source module forms when inputs are missing. |

## 1. Architecture overview

Single-page dashboard at `/za/protection` with 3 tabs, consuming a new `ZaProtectionController` that wraps the existing `ZaProtectionEngine` plus CRUD over two fresh pack tables. Coverage-gap inputs pulled automatically from user state — no assumptions form. Full-stack workstream, estimated ~40-45 files, comparable in size to WS 1.4d.

Integration points:

- `MODULES_BY_JURISDICTION.za` in `resources/js/store/modules/jurisdiction.js` gets one appended entry → data-driven sidebar picks it up with zero `SideMenu.vue` edits.
- `zaProtection.js` Vuex placeholder replaced with functional module.
- Routes nested in the existing `['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za']` prefix group in `routes/api.php` (line 1243 area).
- `PreviewWriteInterceptor` blocks writes from preview users by pattern — no EXCLUDED_ROUTES additions needed.

## 2. Data model

Two new pack migrations under `packs/country-za/database/migrations/`:

### 2.1 `za_protection_policies`

One row per policy; all 6 product types share this table (discriminator via `product_type` enum).

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `user_id` | foreignId → users, indexed | primary owner |
| `joint_owner_id` | foreignId nullable → users, indexed | Fynla joint-ownership pattern (root CLAUDE.md rule 7) |
| `ownership_percentage` | decimal(5,2) default 100 | primary owner's share; joint uses `(100 - ownership_percentage)` for the partner |
| `product_type` | enum | `life`, `whole_of_life`, `dread`, `idisability_lump`, `idisability_income`, `funeral` |
| `provider` | string(120) | free text — SA insurers (Discovery Life, Liberty, Old Mutual, Sanlam, etc.) |
| `policy_number` | string(60) nullable | |
| `cover_amount_minor` | bigInteger | ZAR cents, 0 or positive |
| `premium_amount_minor` | bigInteger | ZAR cents per premium cycle |
| `premium_frequency` | enum | `monthly`, `quarterly`, `annual` |
| `start_date` | date | |
| `end_date` | date nullable | term policies only |
| `severity_tier` | string(1) nullable | dread only: `A` / `B` / `C` / `D` per ASISA SCIDEP |
| `waiting_period_months` | unsignedInteger nullable | income protection only |
| `benefit_term_months` | unsignedInteger nullable | income protection only (2-year or to-age-65 policies per § 6.1) |
| `group_scheme` | boolean default false | employer group life / group dread flag |
| `notes` | text nullable | |
| `created_at` / `updated_at` / `deleted_at` | timestamps + softDeletes | |

Composite index: `(user_id, product_type)` for dashboard aggregation queries.

### 2.2 `za_protection_beneficiaries`

Many-to-one with policies; one policy may have 1..N beneficiaries whose `allocation_percentage` sums to 100.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `policy_id` | foreignId → `za_protection_policies`, `cascadeOnDelete` | |
| `beneficiary_type` | enum | `estate`, `spouse`, `nominated_individual`, `testamentary_trust`, `inter_vivos_trust` |
| `name` | string(200) nullable | null when `beneficiary_type = estate` |
| `relationship` | string(80) nullable | |
| `allocation_percentage` | decimal(5,2) | 0.01–100.00; sum per policy must equal 100.00 |
| `id_number` | string(20) nullable | SA ID (13 digits) for `nominated_individual` |
| `created_at` / `updated_at` | timestamps | |

Index on `policy_id` (implicit via FK).

### 2.3 Eloquent models

- `packs/country-za/src/Models/ZaProtectionPolicy.php` — belongsTo user, belongsTo jointOwner, hasMany beneficiaries. `$casts` for minor-unit fields, softDeletes, enum casts for `product_type` and `premium_frequency`.
- `packs/country-za/src/Models/ZaProtectionBeneficiary.php` — belongsTo policy; enum cast for `beneficiary_type`.

## 3. Backend surface

### 3.1 Controller

`app/Http/Controllers/Api/Za/ZaProtectionController.php` — follows the existing `app/Http/Controllers/Api/Za/` namespace precedent (WS 1.2b/1.3c/1.4d). NOT in the pack itself. Uses `ZaProtectionEngine` via DI.

### 3.2 Endpoints (11)

All under route group `Route::middleware(['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za'])->prefix('za')->as('za.')->group(...)`, further nested under `->prefix('protection')->as('protection.')`:

| Method | Path | Purpose | Middleware |
|---|---|---|---|
| GET | `/za/protection/dashboard` | summary: policies grouped by type, total monthly premium, coverage-gap at-a-glance | auth + jurisdiction + pack |
| GET | `/za/protection/policies` | list all user's + joint policies (query: `OR user_id = ? OR joint_owner_id = ?`) | ditto |
| POST | `/za/protection/policies` | create policy with nested beneficiaries (single transaction) | + PreviewWriteInterceptor |
| GET | `/za/protection/policies/{id}` | detail | |
| PUT | `/za/protection/policies/{id}` | update | + PreviewWriteInterceptor |
| DELETE | `/za/protection/policies/{id}` | soft-delete | + PreviewWriteInterceptor |
| GET | `/za/protection/policy-types` | passthrough to `ZaProtectionEngine::getAvailablePolicyTypes()` | |
| GET | `/za/protection/tax-treatment/{type}` | passthrough to `ZaProtectionEngine::getPolicyTaxTreatment($type)` | |
| GET | `/za/protection/coverage-gap` | per-type gap analysis (pulls inputs from user state, calls engine aggregate method) | |
| POST | `/za/protection/beneficiaries/{policyId}` | replace beneficiary set for a policy (atomic — sum=100 validation) | + PreviewWriteInterceptor |
| GET | `/za/protection/beneficiaries/{policyId}` | list beneficiaries for a policy | |

### 3.3 Form requests

`app/Http/Requests/Za/`:
- `StoreZaProtectionPolicyRequest` — validates product_type against enum, conditional rules (severity_tier required-if dread; waiting_period_months + benefit_term_months required-if idisability_income), cover_amount and premium_amount ≥ 0, date ordering.
- `UpdateZaProtectionPolicyRequest` — same rules, all optional, product_type immutable (implementer note — if a policy changes type it's a new policy).
- `StoreZaBeneficiariesRequest` — validates array of beneficiary objects, sum of `allocation_percentage` = 100.00 via custom rule.
- `CoverageGapRequest` — empty body, just auth (reserved for future query params like "recalculate assumptions").

### 3.4 API resources

`app/Http/Resources/Za/`:
- `ZaProtectionPolicyResource` — transforms minor-unit → major-unit formatted, includes beneficiary collection when loaded.
- `ZaProtectionBeneficiaryResource` — standard fields.
- `ZaCoverageGapResource` — per-type gap objects with `recommended_cover`, `minimum_cover`, `existing_cover`, `shortfall`, `rationale`, plus a `missing_inputs` array when the engine couldn't run (e.g. no IncomeSource for life / income-protection gaps).

### 3.5 Engine extension

Add `ZaProtectionEngine::calculateAggregateCoverageGap(array $userPolicies, array $userContext): array` — iterates the 4 primary gap categories (life, income protection, dread, funeral), sums existing policies per type, invokes the existing per-type calculator (`calculateCoverageNeeds`), returns a 4-element array. This keeps aggregate logic in the pack, not in the controller.

`$userContext` shape: `['annual_income' => int, 'outstanding_debts' => int, 'dependants' => int]` — controller builds this from `IncomeSource::sum`, liability totals, and `HouseholdMember::count`.

### 3.6 Preview interceptor

Global path-pattern match on `/za/protection/*` for POST/PUT/DELETE. `PreviewWriteInterceptor` middleware already handles this pattern-based blocking; no additions needed to `EXCLUDED_ROUTES`. GET endpoints (`/policy-types`, `/tax-treatment/*`, `/coverage-gap`, `/dashboard`, all other reads) work in preview mode.

## 4. Frontend

### 4.1 Route

`resources/js/router/index.js` gets one lazy-loaded entry. Meta shape mirrors the WS 1.4d `/za/retirement` route (same auth + jurisdiction guard); the writing-plans step will lift the exact meta object from the existing ZA route to stay consistent.

```js
{
  path: '/za/protection',
  name: 'ZaProtection',
  component: () => import('@/views/ZA/ZaProtectionDashboard.vue'),
  meta: { /* copy from /za/retirement */ },
}
```

### 4.2 Sidebar registration

One object appended to `MODULES_BY_JURISDICTION.za` in `resources/js/store/modules/jurisdiction.js`:
```js
{ label: 'Protection', path: '/za/protection', icon: /* raspberry-compatible icon */ }
```

Data-driven sidebar (established WS 1.2b) picks it up — zero `SideMenu.vue` edits.

### 4.3 View

`resources/js/views/ZA/ZaProtectionDashboard.vue` — page shell with 3 tabs. On mount: dispatches `zaProtection/fetchDashboard` + `fetchPolicies` + `fetchCoverageGap` in parallel. Tab state held locally; deep-linkable via `?tab=policies|coverage-gap|beneficiaries`.

### 4.4 Components

Location: `resources/js/components/ZA/Protection/`. 11 tab-specific components (+ 1 shared `DialogContainer` in § 4.5), grouped by tab:

**Tab 1 — Policies:**
| Component | Responsibility |
|---|---|
| `ZaPoliciesTable.vue` | Groups policies by `product_type` (collapsible sections), row = provider / cover / premium / beneficiary count. Uses `v-preview-disabled` on action buttons. |
| `ZaProtectionPolicyForm.vue` | Single form with conditional field rendering based on `product_type`. Base fields (provider, cover, premium, frequency, dates) always shown; `severity_tier` only when `product_type=dread`; `waiting_period_months` / `benefit_term_months` only when `product_type=idisability_income`. Emits `save` per CLAUDE.md rule 4. |
| `ZaProtectionPolicyModal.vue` | Modal wrapper around the form. Wraps `DialogContainer` (see § 4.5). |
| `ZaPolicyDetailCard.vue` | Read-only detail card with tax-treatment narrative from `/tax-treatment/{type}`. |
| `ZaPolicyTypeSelector.vue` | 6-option selector with descriptions from `getAvailablePolicyTypes()`. |

**Tab 2 — Coverage gap:**
| Component | Responsibility |
|---|---|
| `ZaCoverageGapDashboard.vue` | Grid of 4 gauge cards (life / income protection / dread / funeral). Pulls from `/coverage-gap`. |
| `ZaCoverageGaugeCard.vue` | Horizontal progress bar with numeric labels: recommended / existing / shortfall. Uses `CHART_COLORS` from `designSystem.js`. |
| `ZaCoverageRationalePanel.vue` | Expandable "why this number?" panel showing the engine's `rationale` string + input assumptions (e.g. "using annual_income=R480,000 from your IncomeSources, dependants=2 from HouseholdMembers"). |
| `ZaMissingInputsEmptyState.vue` | Shown when a gap can't be computed. Deep-link CTAs: "Add income", "Add household members", "Add liabilities" pointing at the relevant module forms. |

**Tab 3 — Beneficiaries:**
| Component | Responsibility |
|---|---|
| `ZaBeneficiariesTab.vue` | Table of all policies with beneficiary allocation summary. Visual duty-warning badge for `beneficiary_type=estate` (per § 6.2 — these policies are dutiable). |
| `ZaBeneficiaryEditor.vue` | Inline editor for a single policy's beneficiaries. Enforces sum=100 with live validation. Supports all 5 beneficiary types; shows/hides `name` + `id_number` fields based on type. |

### 4.5 Shared modal wrapper (tech-debt W1)

New `resources/js/components/Common/DialogContainer.vue` — shared wrapper adding:
- `role="dialog"` + `aria-modal="true"` + `aria-labelledby` hook for heading
- Escape-to-close keyboard handler
- Focus trap (cycling Tab / Shift+Tab within the dialog)
- Backdrop click-to-close (optional prop)

WS 1.5b ships the wrapper **and** refactors prior ZA modals to use it:
- WS 1.2b: `ZaContributionModal.vue` (savings)
- WS 1.3c: `ZaInvestmentForm.vue` (when in modal mode)
- WS 1.4d: `ZaContributionModal.vue` (retirement), `ZaRetirementFundForm.vue`

~4 existing modals refactored, ~8-10 small edits. Closes W1 across all ZA UI workstreams.

### 4.6 Vuex

`resources/js/store/modules/zaProtection.js` replaces the placeholder.

State shape:
```js
{
  policies: [],              // full list (user + joint)
  beneficiaries: {},         // keyed by policy id
  policyTypes: [],           // from /policy-types
  taxTreatments: {},         // keyed by product_type, lazily filled
  coverageGap: null,         // full payload from /coverage-gap
  dashboard: null,           // summary payload
  loading: false,
  error: null,
}
```

Actions: `fetchDashboard`, `fetchPolicies`, `fetchPolicy`, `createPolicy`, `updatePolicy`, `deletePolicy`, `fetchPolicyTypes`, `fetchTaxTreatment`, `fetchCoverageGap`, `fetchBeneficiaries`, `saveBeneficiaries`. All destructure `response.data` correctly (WS 1.4d post-commit bug lesson — the service returns parsed body, not raw axios response).

### 4.7 Service

`resources/js/services/zaProtectionService.js` — axios wrapper using the project `api` instance (baseURL `/api/`). Paths relative: `/za/protection/...`, NOT `/api/za/protection/...` (WS 1.3c convention). Every method returns `response.data`.

### 4.8 Currency / conversion discipline

All components use `toMinorZAR()` from `@/utils/zaCurrency` for major→minor conversions — no inline `Math.round(x * 100)`. This closes tech-debt W2 for WS 1.5b's own code.

### 4.9 Design system compliance

- Palette tokens only: raspberry / horizon / spring / violet / savannah / eggshell. No amber, no orange, no `primary-*` / `secondary-*` / `gray-*`.
- Gauges + charts use `CHART_COLORS` from `resources/js/constants/designSystem.js` — no hardcoded hex.
- Typography: Segoe UI / Inter fallback; h1=900, h2-h5=700.
- No scores anywhere (root CLAUDE.md rule 13). Use currency values + descriptive prose instead.
- User-facing copy: British spelling, acronyms spelled out except `TFSA` (rule 10 — but N/A for Protection; no acronyms needed here).

## 5. Testing

### 5.1 Pest tests (target ~30 new)

| Suite | File | Test count | Coverage |
|---|---|---|---|
| Feature | `tests/Feature/Api/Za/ZaProtectionControllerTest.php` | ~18 | dashboard; list; create/read/update/delete; policy-types passthrough; tax-treatment passthrough; coverage-gap happy path; coverage-gap missing-inputs (no IncomeSource, no HouseholdMember); jurisdiction guard (403 without `pack.enabled:za`); preview-write interception; joint-owner visibility |
| Feature | `tests/Feature/Api/Za/ZaProtectionBeneficiaryTest.php` | ~6 | list; replace (atomic); sum=100 validation; cascade on policy delete; estate-type with null name accepted; nominated_individual requires name |
| Unit | `packs/country-za/tests/Unit/ZaProtectionEngineAggregateGapTest.php` | ~4 | aggregate across user policies; zero policies (shortfall = recommended); mix of all 6 types; missing income returns `missing_inputs` signal |
| Integration | `tests/Integration/ZaProtectionWorkflowTest.php` | ~2 | create-policy-then-coverage-gap-updates; delete-policy-then-gap-updates |

Factories needed: `ZaProtectionPolicyFactory`, `ZaProtectionBeneficiaryFactory` — in `packs/country-za/database/factories/`.

### 5.2 Playwright browser smoke (non-negotiable per CLAUDE.md)

1. Login as existing `za-retirement-test@example.com` / `password` (reuse from WS 1.4d — already ZA-jurisdiction-activated).
2. Sidebar shows "Protection" under "South Africa" section.
3. Navigate to `/za/protection`; verify all 3 tab headers visible.
4. **Tab 1:** click "Add policy" → modal opens; select `life`; fill provider (Discovery Life), cover amount (R5,000,000), premium (R1,500), frequency (monthly), start date, one beneficiary (spouse, 100%); save; verify row appears in table; edit cover to R6m; verify update; delete; verify removal.
5. **Tab 2:** verify 4 gauges populate; manually null `HouseholdMember` data on test user; refresh; verify funeral gauge shows missing-inputs empty state with deep-link; restore `HouseholdMember` data; verify gauge repopulates.
6. **Tab 3:** create a `dread` policy (severity_tier = B); add 2 beneficiaries (spouse 50%, nominated_individual 50%); save; verify sum=100 passes; attempt to save 60/50; verify client-side validation blocks save.

### 5.3 Baseline expectation

- Tests: 2,747 → ~2,777 passing (+30, 0 regressions).
- UK Protection controller unaffected — fresh pack tables, no UK schema changes, no shared service code touched.
- No new `ProtectionWorkflowTest` failures (the 4 pre-existing `adequacy_score` failures stay at 4, not 5).

## 6. Tech-debt targets addressed

- **W1 — Modal accessibility** (`tech-debt-report.md` WS 1.4d): ship `DialogContainer` wrapper + refactor 4 existing ZA modals from WS 1.2b / 1.3c / 1.4d. Closes W1 across all ZA UI workstreams.
- **W2 — `toMinorZAR` reuse**: WS 1.5b's own components use the util; bonus sweep refactors the 15 existing `Math.round(x * 100)` sites in WS 1.4d components to use `toMinorZAR` (zero behaviour change).

## 7. Scope non-goals (explicitly deferred)

- Employer group-life auto-import — no data source to pull from yet.
- Policy document uploads / attachments — infrastructure decision deferred.
- Expiry-date reminder notifications — WS 1.7 Coordination may surface these.
- Joint ownership on funeral policies — single-owner only in v1 (matches WS 1.2b savings pattern). Revisit in WS 1.7 when SA family model lands.
- Cross-border treatment of UK life policies held by SA residents — Phase 2 cross-border workstream.
- IT3(c) / tax-certificate rendering for life policy payouts — outside protection module scope (belongs to tax/reporting).

## 8. Files affected (estimate)

| Layer | Count | Sub-category |
|---|---|---|
| Pack migrations | 2 | policies + beneficiaries |
| Pack models | 2 | `ZaProtectionPolicy`, `ZaProtectionBeneficiary` |
| Pack engine | 1 | extend `ZaProtectionEngine` with aggregate method |
| Pack factories | 2 | policy + beneficiary |
| Controllers | 1 | `ZaProtectionController` |
| Form requests | 4 | 2 policy + 1 beneficiary + 1 coverage-gap |
| Resources | 3 | policy + beneficiary + coverage-gap |
| Routes | inline in existing group | ~11 new route definitions |
| Frontend view | 1 | `ZaProtectionDashboard` |
| Frontend components | 12 | 5 policies tab + 4 coverage-gap tab + 2 beneficiaries tab + 1 shared `DialogContainer` |
| Vuex module | 1 | replace placeholder |
| Service | 1 | `zaProtectionService.js` |
| Router + sidebar | 2 edits | 1 route + 1 MODULES_BY_JURISDICTION entry |
| Refactor (W1 tech debt) | ~4 modal files | prior-workstream modals to use `DialogContainer` |
| Refactor (W2 tech debt) | ~9 files | inline `Math.round * 100` → `toMinorZAR` |
| Tests | 4 suites | ~30 tests total |

**Total new files: ~34. Edits: ~13. Playwright smoke: 1 scenario with 6 steps.**

## 9. Risk register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `DialogContainer` refactor breaks existing ZA modals | Low | Medium | Refactor in one atomic commit per prior-workstream; smoke-test each after refactor; keep props surface-compatible with existing modal open/close events. |
| Coverage-gap aggregation uses wrong unit (major vs minor) across engine boundary | Medium | High | Engine receives minor units (ZAR cents) as `int` per WS 1.4d convention; resource transforms output to formatted major at the API boundary only. Add explicit test for round-tripping. |
| Joint-owner queries miss policies when only `joint_owner_id = $user` | Medium | Medium | Use `HasJointOwnership` trait precedent from UK code; explicit test for joint-owner-only visibility. |
| Playwright tab routing breaks with `?tab=...` query param | Low | Low | Default tab = policies; guard against unknown tab values. |
| Beneficiary sum-validation races with optimistic update | Low | Medium | Server-side is authoritative — client-side validation prevents submission but server recomputes sum on save. |
| Test user `za-retirement-test@example.com` lacks HouseholdMembers, breaking coverage-gap smoke | Certain | Low | Seed HouseholdMembers as part of Playwright setup, or use a different test user with complete data. Decision: use/create `za-protection-test@example.com` with complete profile. |

## 10. Pickup / handoff

Next step after this design is approved: invoke `superpowers:writing-plans` to produce a task-by-task implementation plan with exact code shapes, then `/prd-writer` to audit the plan against the live codebase before implementation starts.
