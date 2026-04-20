---
title: WS 1.4d — SA Retirement Frontend
status: amended
date: 2026-04-20
amended: 2026-04-20 — codebase audit resolved
workstream: 1.4d
predecessors: [WS 1.2b, WS 1.3c, WS 1.4a, WS 1.4b, WS 1.4c]
spec_sources:
  - Plans/Implementation_Plan_v2.md § Workstream 1.4
  - Plans/SA_Research_and_Mapping.md § 9 (Retirement)
  - April/April18Updates/PRD-ws-1-4a-za-retirement.md
---

> **Amendments (20 April 2026):** (1) `LifeAnnuityQuoteRequest` field is `declared_section_10c_pool_minor` (not `section_10c_pool_minor`) — flags user override, v1.1-survivable. (2) `LifeAnnuityQuoteRequest` does NOT take `capital_minor` — the backend calculator doesn't need it. (3) `CompulsoryApportionRequest` takes three separate bucket amounts (`vested_minor`, `provident_vested_pre2021_minor`, `retirement_minor`) — not a combined `total_balance_minor`. (4) `Reg28CheckRequest.allocation` is a flat associative map of float percentages (matches `ZaReg28Monitor::check()`) — not an indexed array of `{asset_class, weight_bps}`. (5) Pack migration required: `dc_pensions.pension_type` ENUM → VARCHAR(60) (Task 0.5 in plan). (6) UK `RetirementController` + `RetirementAgent` patched to filter ZA funds (Task 3.5 in plan). (7) `PreviewWriteInterceptor` patterns prefixed with `/za/`.

# WS 1.4d — SA Retirement Frontend Design

## 1. Context & goal

The SA retirement backend (WS 1.4a / 1.4b / 1.4c) is shipped but unexposed — no HTTP controller, no UI. SA users cannot record retirement funds, track Two-Pot buckets, simulate Savings-Pot withdrawals, model annuity drawdowns, or check Regulation 28 compliance. This workstream builds the HTTP surface and the Vue UI that consumes it.

Goal: a single `/za/retirement` page with three tabs (Accumulation, Decumulation, Compliance) that exercises every public method on `ZaRetirementEngine`, `ZaContributionSplitService`, `ZaSavingsPotWithdrawalSimulator`, `ZaLivingAnnuityCalculator`, `ZaLifeAnnuityCalculator`, `ZaCompulsoryAnnuitisationService`, `ZaRetirementFundBucketRepository`, and `ZaReg28Monitor`, and lets an SA peak-earner persona complete the full retirement-planning journey end-to-end in Playwright.

## 2. Scope

**In scope**
- One `ZaRetirementController` exposing 13 endpoints under `/api/za/retirement/*`.
- One pack migration converting `dc_pensions.pension_type` ENUM → VARCHAR(60).
- Patch to UK `RetirementController` + `RetirementAgent` filtering out ZA-coded DC pensions from the UK retirement view.
- 8 form requests, 4 resources.
- One functional Vuex module (`zaRetirement`) replacing the WS 1.2b placeholder.
- One axios service (`zaRetirementService`).
- One view (`ZaRetirementDashboard`) + 13 components split across three tabs.
- One lazy router entry + one sidebar entry in `MODULES_BY_JURISDICTION.za`.
- ~22 new Pest feature tests.
- Playwright browser smoke test end-to-end.

**Out of scope (deferred)**
- Savings-Pot once-per-tax-year frequency enforcement — deferred per WS 1.4a PRD § 5 (backend doesn't enforce it; neither will v1 UI).
- SASSA Old Age Grant capture — data field only, no dedicated UI widget in v1.
- Reg 28 look-through roll-up from individual fund holdings — v1 accepts manual allocation input; automatic look-through from `za_investment_holdings` is a WS 1.4d.v1.1 enhancement.
- Joint ownership — SA retirement funds are always individual per SARS rules; no `joint_owner_id`.
- Preview persona data — WS 1.7 will seed retirement fixtures; v1 empty states must render cleanly.

## 3. Route & sidebar

- **Route:** `resources/js/router/index.js` — `{ path: '/za/retirement', component: () => import('@/views/ZA/ZaRetirementDashboard.vue'), meta: { requiresAuth: true, requiresJurisdiction: 'za' } }`.
- **Tabs:** state via `?tab=accumulation|decumulation|compliance` query param. Default `accumulation`. Reads/writes via `vue-router` push.
- **Sidebar entry:** append to `resources/js/store/modules/jurisdiction.js` → `MODULES_BY_JURISDICTION.za`: `{ key: 'za-retirement', label: 'Retirement', path: '/za/retirement', icon: 'briefcase' }`. No `SideMenu.vue` edits.

## 4. HTTP surface

All routes live inside the existing `/api/za/*` group in `routes/api.php` (middleware: `auth:sanctum`, `active.jurisdiction`, `pack.enabled:za`). Controller at `app/Http/Controllers/Api/Za/ZaRetirementController.php`.

| Method | Path | Form request | Response |
|---|---|---|---|
| GET | `/api/za/retirement/dashboard` | — | `{ tax_year, funds[], section_11f: {ytd, remaining_allowance, carry_forward}, total_balance_minor, upcoming_actions[] }` |
| GET | `/api/za/retirement/funds` | — | `ZaRetirementFundResource::collection` |
| POST | `/api/za/retirement/funds` | `StoreFundRequest` | `ZaRetirementFundResource` (201) |
| GET | `/api/za/retirement/funds/{id}/buckets` | — | `ZaRetirementBucketResource` |
| POST | `/api/za/retirement/contributions` | `StoreContributionRequest` | `{ split: {vested, savings, retirement}, buckets: ZaRetirementBucketResource }` (201) |
| POST | `/api/za/retirement/savings-pot/simulate` | `SimulateSavingsPotWithdrawalRequest` | `{ tax_delta_minor, net_received_minor, marginal_rate, crosses_bracket }` |
| POST | `/api/za/retirement/savings-pot/withdraw` | same req | `{ buckets: …, withdrawal: {gross, net, tax} }` (201) |
| POST | `/api/za/retirement/tax-relief/calculate` | `CalculateTaxReliefRequest` | `{ relief_amount_minor, relief_rate, net_cost_minor, method: 'section_11f', carry_forward_minor }` |
| POST | `/api/za/retirement/annuities/living/quote` | `LivingAnnuityQuoteRequest` | `ZaAnnuityQuoteResource` |
| POST | `/api/za/retirement/annuities/life/quote` | `LifeAnnuityQuoteRequest` | `ZaAnnuityQuoteResource` (includes `section_10c_exemption_minor`) |
| POST | `/api/za/retirement/annuities/compulsory-apportion` | `CompulsoryApportionRequest` | `{ lump_sum_minor, annuity_capital_minor, provident_pre2021_commutable_minor, below_de_minimis }` |
| GET/POST | `/api/za/retirement/reg28/check` | `Reg28CheckRequest` | `{ compliant, breaches[], summary: {offshore_bps, equity_bps, property_bps, private_equity_bps, single_entity_max_bps}, tax_year }` |
| GET | `/api/za/retirement/reg28/snapshots` | — | `Reg28SnapshotResource::collection` |
| POST | `/api/za/retirement/reg28/snapshots` | `Reg28CheckRequest` | `Reg28SnapshotResource` (201) |

**Middleware additions** (`app/Http/Middleware/PreviewWriteInterceptor.php`) — add to `EXCLUDED_PATTERNS` (what-if reads, not writes):
- `#/retirement/savings-pot/simulate$#`
- `#/retirement/tax-relief/calculate$#`
- `#/retirement/annuities/living/quote$#`
- `#/retirement/annuities/life/quote$#`
- `#/retirement/annuities/compulsory-apportion$#`
- `#/retirement/reg28/check$#`

**Controller conventions (lessons from WS 1.3c):**
- Thin HTTP adapter over `pack.za.retirement*` bindings. No business logic.
- `try { } catch (\InvalidArgumentException $e) { return 422 }` around pack calls that validate inputs (simulator, annuity calculators).
- `Money` VO + `Currency::ZAR()` for arithmetic in `dashboard()` aggregation.
- Fund-create writes `country_code = 'ZA'` and `country = 'South Africa'` (human-readable).
- `fund_type` enum: `retirement_annuity`, `pension_fund`, `provident_fund`, `preservation_fund`.

## 5. Form requests (7)

`app/Http/Requests/Za/Retirement/`:

- **StoreFundRequest** — `fund_type` (enum above), `provider` (string ≤120, required), `scheme_name` (string ≤255, nullable), `member_number` (string ≤60, nullable), `starting_vested_minor` (int ≥0), `starting_savings_minor` (int ≥0), `starting_retirement_minor` (int ≥0), `provident_vested_pre2021_minor` (int ≥0, required only when `fund_type=provident_fund`).
- **StoreContributionRequest** — `fund_holding_id` (exists:dc_pensions,id + user scope), `amount_minor` (int ≥1), `contribution_date` (date, before_or_equal:today).
- **SimulateSavingsPotWithdrawalRequest** — `fund_holding_id`, `amount_minor` (int ≥200_000 per R2,000 backend minimum), `current_annual_income_minor` (int ≥0), `age` (int 18–125), `tax_year` (string, regex: `/^\d{4}\/\d{2}$/`).
- **CalculateTaxReliefRequest** — `contribution_minor` (int ≥1), `gross_income_minor` (int ≥0), `tax_year`.
- **LivingAnnuityQuoteRequest** — `capital_minor` (int ≥1), `drawdown_rate_bps` (int 250–1750), `age` (int 18–125), `tax_year`.
- **LifeAnnuityQuoteRequest** — `annual_annuity_minor` (int ≥1), `declared_section_10c_pool_minor` (int ≥0), `age`, `tax_year`. (No `capital_minor` — backend calculator doesn't require it.)
- **CompulsoryApportionRequest** — `vested_minor` (int ≥0), `provident_vested_pre2021_minor` (int ≥0), `retirement_minor` (int ≥0), `tax_year`. Backend `ZaCompulsoryAnnuitisationService::apportion()` takes three separate bucket amounts.
- **Reg28CheckRequest** — `tax_year`, `allocation` (associative map of float percentages), keys: `offshore`, `equity`, `property`, `private_equity`, `commodities`, `hedge_funds`, `other`, `single_entity`. Each 0–100. The 7 asset-class keys must sum to 100% (custom rule); `single_entity` is an independent max-exposure measure. Matches `ZaReg28Monitor::check()` input shape.

## 6. Resources (4)

`app/Http/Resources/Za/Retirement/`:

- **ZaRetirementFundResource** — `id, fund_type, fund_type_label, provider, scheme_name, member_number, country, country_code, total_balance_minor, buckets: ZaRetirementBucketResource, created_at_iso`.
- **ZaRetirementBucketResource** — `fund_holding_id, vested_minor, provident_vested_pre2021_minor, savings_minor, retirement_minor, total_minor, last_transaction_date_iso`.
- **ZaAnnuityQuoteResource** — `kind (living|life), capital_minor, annual_income_minor, monthly_income_minor, net_monthly_income_minor, effective_tax_rate_bps, drawdown_rate_bps (living only), section_10c_exemption_minor (life only), warnings[]`.
- **Reg28SnapshotResource** — `id, tax_year, taken_at_iso, compliant, breaches[], summary{}`.

## 7. Vue component tree

```
resources/js/views/ZA/
  ZaRetirementDashboard.vue               (page view, tab router, header, summary)

resources/js/components/ZA/Retirement/
  ZaRetirementTabs.vue                    (3-tab nav; syncs ?tab= param)
  ZaRetirementSummary.vue                 (top card: total balance, Section 11F YTD)

  — Accumulation tab —
  ZaRetirementFundsList.vue               (list of ZA funds with bucket badges)
  ZaRetirementFundForm.vue                (add/edit modal)
  ZaTwoPotTracker.vue                     (3 (or 4) visual buckets per fund)
  ZaContributionModal.vue                 (live 1/3–2/3 split preview)
  ZaSavingsPotWithdrawalCard.vue          (two-step: simulate → confirm)
  ZaSection11fReliefCalculator.vue        (what-if card, no persistence)

  — Decumulation tab —
  ZaLivingAnnuitySlider.vue               (2.5–17.5% slider, debounced recalc)
  ZaLifeAnnuityQuote.vue                  (form + Section 10C exemption display)
  ZaCompulsoryAnnuitisationCard.vue       (at-retirement apportioner, R165k de minimis)

  — Compliance tab —
  ZaReg28AllocationForm.vue               (8 asset-class inputs, sums-to-100 indicator)
  ZaReg28ComplianceCard.vue               (pass/fail + breach list with limits)
  ZaReg28SnapshotHistory.vue              (table with tax-year filter)
```

**Design compliance (non-negotiable):**
- **Icons**: sidebar only. No icons inside cards, dashboards, tables, modals, or detail views (design guide v1.4.0 Rule 1).
- **Scores**: none. Currency values, percentages, and "X of Y" framings only (CLAUDE.md Rule 13).
- **Acronyms spelled on first use** per CLAUDE.md Rule 10: Retirement Annuity (RA), Pension Fund (PF), Provident Fund (PvF), Preservation Fund, Regulation 28, Section 11F, Section 10C, Pension Commencement Lump Sum (PCLS), Living Annuity, Life Annuity. TFSA stays abbreviated.
- **Currency** via `zaCurrencyMixin` (`R 46 000,00` en-ZA format). Never inline `toFixed()`.
- **Palette**: raspberry (CTAs), horizon (text/nav), spring (success/compliant), violet (warnings/caution), raspberry (breach/error). No amber, no orange.
- **Buttons**: follow global classes from `app.css` (`btn-primary`, `btn-secondary`, `btn-tertiary`). No hardcoded hex in `<style scoped>`.
- **Empty states**: clear copy for zero funds, zero snapshots, not-yet-simulated. "Record your first Retirement Annuity contribution to see how the Two-Pot split applies." etc.

## 8. State & services

**Vuex module** `resources/js/store/modules/zaRetirement.js` (replaces WS 1.2b placeholder). Namespaced.

- **State:** `taxYear`, `funds`, `bucketsByFundId`, `dashboard: {section11f, totalBalanceMinor, upcomingActions}`, `simulatorResult`, `annuityQuotes: {living, life, compulsoryApportion}`, `reg28Allocation`, `reg28CheckResult`, `reg28Snapshots`, `loading`, `error`.
- **Mutations:** setters + `addFund`, `updateFund`, `setBucketsForFund`, `setSimulatorResult`, `setAnnuityQuote(kind, result)`, `setReg28Result`, `addSnapshot`.
- **Actions** (one per endpoint): `fetchDashboard`, `fetchFunds`, `storeFund`, `fetchBuckets(fundId)`, `storeContribution`, `simulateSavingsPotWithdrawal`, `withdrawSavingsPot`, `calculateTaxRelief`, `quoteLivingAnnuity`, `quoteLifeAnnuity`, `apportionCompulsory`, `checkReg28`, `fetchReg28Snapshots`, `storeReg28Snapshot`.

**Axios service** `resources/js/services/zaRetirementService.js` — thin wrapper around `/api/za/retirement/*`. Mirrors Vuex action surface one-to-one.

## 9. Acceptance / tests

**Pest feature tests** (~22 new):

`tests/Feature/Api/Za/ZaRetirementControllerTest.php` (~16):
- Auth gate (401 unauthenticated)
- Jurisdiction gate (403 without ZA activation)
- Dashboard shape + Section 11F YTD composition
- Fund create sets `country='South Africa'` and `country_code='ZA'`
- Fund create with provident_fund requires provident_pre2021 field
- Bucket read returns 4 balances (3 or 4 non-zero depending on fund type)
- Contribution pre-2024-09-01 → 100% vested
- Contribution post-2024-09-01 → 1/3 savings + 2/3 retirement (integer cents)
- Savings-pot simulate returns delta + crosses_bracket flag
- Savings-pot simulate below R2,000 → 422 (pack service throws `InvalidArgumentException`)
- Savings-pot withdraw decrements savings bucket
- Tax-relief calculate returns R350k cap + carry-forward
- Living annuity quote at 5% in-band → quote
- Living annuity quote at 20% out-of-band → 422
- Life annuity quote shows Section 10C exemption
- Compulsory apportion below R165k → `below_de_minimis: true`, full lump sum

`tests/Feature/Api/Za/ZaRetirementReg28ControllerTest.php` (~6):
- Compliant allocation (all limits satisfied) → `compliant: true, breaches: []`
- Offshore >30% → breach
- Equity >75% → breach
- Multiple simultaneous breaches return all
- Allocation that does not sum to 100% → 422
- Snapshot persist + list by tax year

**Playwright browser smoke (end-to-end, ZA test user):**
1. Login + MFA → `/za/retirement` via sidebar
2. Accumulation tab: Add RA fund (Allan Gray, R0 starting) → Record R3,000 contribution dated 2026-05-10 → verify split R1,000 / R2,000 in Two-Pot tracker
3. Section 11F what-if: R5,000 contribution on R40,000 income → verify deductible + net cost
4. Savings-pot simulate: R2,500 withdrawal on R240,000 income, age 40, 2026/27 → verify marginal-rate warning
5. Decumulation tab: Living annuity slider at 5% on R2m capital → verify monthly income
6. Life annuity quote: R1m capital, R60k/yr annuity, age 65 → verify Section 10C line
7. Compliance tab: Enter allocation 40/10/15/15/5/30/0/0 → verify "Offshore 30%" boundary pass, then 35% offshore → verify breach
8. Take snapshot → verify history appears

**Regression baseline:** 2,723 passing. Target 2,745 passing (+22). 4 pre-existing `ProtectionWorkflowTest` failures acknowledged.

## 10. Work decomposition

| Task | Scope | Est. |
|---|---|---|
| 0 | Sidebar entry + icon key | 15 min |
| 1 | 7 form requests | 1 h |
| 2 | 4 resources | 30 min |
| 3 | `ZaRetirementController` + routes + middleware `EXCLUDED_PATTERNS` additions | 3 h |
| 4 | `ZaRetirementControllerTest` (16 tests) | 2.5 h |
| 5 | `ZaRetirementReg28ControllerTest` (6 tests) | 1 h |
| 6 | Functional `zaRetirement` Vuex module | 1.5 h |
| 7 | `zaRetirementService` axios wrapper | 30 min |
| 8 | Router route + sidebar `MODULES_BY_JURISDICTION.za` append | 15 min |
| 9 | View `ZaRetirementDashboard` + tabs | 1 h |
| 10 | Accumulation tab (7 components) | 4 h |
| 11 | Decumulation tab (3 components) | 2.5 h |
| 12 | Compliance tab (3 components) | 2 h |
| 13 | Full Pest suite green + Playwright + commit + handover | 2 h |

Total rough estimate: 22 hours of autonomous session time; in practice compressed by prior WS 1.2b/1.3c scaffold reuse.

## 11. Risks & mitigations

1. **Fund data model overlap with UK DCPension** — ZA funds live on the same `dc_pensions` table as UK workplace pensions with a `country_code` discriminator. Risk: a UK view accidentally surfaces a ZA fund, or vice versa. Mitigation: controller scopes `where country_code = 'ZA'` on index; existing UK controllers don't filter by country_code (all pre-ZA rows are NULL), so UK fetches currently return NULLs only — verify this in a Playwright regression for the UK retirement page.

2. **Reg 28 allocation input ergonomics** — 8 asset classes summing to 100% is fiddly. Mitigation: live "remaining allocation: X%" indicator + disable submit until sum==100%, not a post-submit 422.

3. **Living annuity slider live recalc** — debounce 300ms so slider drag doesn't hammer the endpoint.

4. **Preview mode** — calculation endpoints (simulate, quote, check) are in `EXCLUDED_PATTERNS`. Write endpoints (contributions, withdrawal, fund create, snapshot persist) remain blocked. Mitigation: UI must gracefully handle the interceptor's fake success response via `previewModeMixin` guards on the Confirm buttons.

5. **`dc_pensions` schema may lack Two-Pot columns** — WS 1.4a shipped `za_retirement_fund_buckets` as a separate table linked by `fund_holding_id`. Controller reads/writes buckets via the repository; `dc_pensions` row stores only canonical fund metadata. Verify migration state during Task 0.

6. **Compliance with CSS governance (Rule 12)** — all component styles use Tailwind `@apply` with palette tokens only. Pre-flight check after Task 10/11/12: grep for hex in `<style scoped>` and banned `amber|orange|primary-|secondary-`.

## 12. Pick-up for planning

Next step: invoke `superpowers:writing-plans` to produce `docs/superpowers/plans/2026-04-20-ws-1-4d-za-retirement-frontend.md` that breaks this design into ordered tasks with explicit file lists, acceptance conditions, and amendments; then `/prd-writer` to validate against the live codebase and produce the canonical PRD under `April/April20Updates/PRD-ws-1-4d-za-retirement-frontend.md`.
