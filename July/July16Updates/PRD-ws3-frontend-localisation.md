# PRD — WS3 Frontend Localisation

**Project:** WS3 Frontend Localisation (International Layer, workstream 3 of 6)
**Owner:** CSJ
**Status:** Draft
**Date:** 16 July 2026
**Spec:** `docs/superpowers/specs/2026-07-16-ws3-frontend-localisation-design.md` (amended 2026-07-16)
**Plan:** `docs/superpowers/plans/2026-07-16-ws3-frontend-localisation.md` (amended 2026-07-16)
**Codebase audit:** Completed 16 July 2026 — zero conflicts found; all findings dispositioned (see Risks & Dependencies)

---

## 1. Context & Why

### Problem
The backend knows how a South African user's money and dates should look — the `Localisation` contract (`core/app/Core/Contracts/Localisation.php`) is implemented per pack and container-bound (`pack.za.localisation` → `ZaLocalisation`, `pack.gb.localisation` → `GbLocalisation`) — but none of it reaches the single-page application. The SPA's shared formatting layer hardcodes the UK: `resources/js/utils/currency.js` pins `Intl.NumberFormat('en-GB', GBP)`, `currencyMixin.formatNumber` pins `en-GB`, and `resources/js/utils/dateFormatter.js` pins DD/MM/YYYY and the 6-April UK tax year. The result: a South African user who signed up through WS1 and whose assets reach core aggregation through WS2 still sees `£0` framing, UK date formats, and a 6-April tax year across the Dashboard, Net Worth, Goals, and every shared surface.

Two live defects compound this:
- `taxConfig/fetchActive` (dispatched on every login from `App.vue:34` and `auth.js fetchUser`) unconditionally calls the GB pack route `/api/gb/tax-year/current`. For an SA-only user, WS1's `ActiveJurisdictionMiddleware` 403s that call (verified against `ActiveJurisdictionMiddleware.php:57-64` and its precedent test) — silent noise on every SA login.
- `formatZAR` (`resources/js/utils/zaCurrency.js`) claims period-decimal output (`R 1 234 567.89`, per SA Research §17) but delegates to `Intl.NumberFormat('en-ZA')`, whose CLDR data emits comma decimals — so the 32+ ZA pack components have been rendering `R 1 234,56` against the documented convention.

### Business case
The International Layer programme exists to make Fynla credible outside the UK. WS1 (jurisdiction lifecycle) and WS2 (SA data reaches core) are invisible plumbing; WS3 is the first workstream an SA user can *see*. Until amounts read `R 1 234.56` and the tax year reads 1-March, the SA experience looks like a UK app with the flag swapped — undermining trust in a product whose whole promise is jurisdiction-correct financial planning. WS3 also unblocks WS4 (SA experience surfacing), which assumes formatting is already jurisdiction-aware.

### Strategic fit
Touches the display layer of all seven modules (Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination) in both jurisdictions — GB output is contractually unchanged, ZA output becomes convention-correct. Sits third in the six-workstream International Layer programme on `feat/international-layer` (WS1 commits `a78bfbb…1940d12`, WS2 commits `cb84328…08d98fb`, both green at 3,089 passing). Dev-only: deploys to `csjones.co/fynla_inter`, never `fynla.org` (legacy). Depends on WS1's session jurisdiction fields and `user_jurisdictions`; feeds WS4/WS6.

---

## 2. Target Persona

**Primary:** `sa_professional` (seeded SA preview persona, `ZaPreviewUserSeeder`) and all real ZA-primary users — they currently see UK formatting on every shared surface despite holding only SA products (Tax-Free Savings Account, retirement annuity buckets). WS3 is built and browser-verified against this persona.

**Secondary:** All GB personas (`young_family`, `peak_earners`, etc.) and real GB users — not as beneficiaries but as the regression population: every GBP/unset code path must render byte-for-byte identically to today. The Vitest suite encodes this guarantee.

Infrastructure notes: the localisation singleton is a shared mechanism that future packs (any `pack.{iso}.localisation` binding) get for free via the generic `Intl` path — no frontend change needed for country four.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Programmatic money/date/tax-year formatting shown to the SA persona uses ZA conventions (R, period decimal, `d M Y`, 1-March year) | 0% outside explicit ZA components | 100% of formatting routed through `currency.js`/`dateFormatter.js`/`currencyMixin` | Playwright walkthrough of `sa_professional` dashboard + module pages at Task 8 |
| GB rendered output changed by WS3 | n/a | Zero changes (byte-for-byte) | Vitest regression assertions (GBP/unset paths) + GB persona browser pass |
| Blocked cross-pack `/api/gb/tax-year/current` calls fired by SA-only sessions per login | 1 per login (403) | 0 | `taxConfig.test.js` gate assertions; no `JURISDICTION_NOT_AUTHORISED` noise in `storage/logs/laravel.log` during SA persona session |
| Full test suites | 3,089 Pest passing / Vitest green | All green including ~5 new/extended test files | `./vendor/bin/pest`, `npm run test:run`, `composer analyse` at Task 8 |

---

## 4. User Stories & Scenarios

### User stories
- As an **SA user**, I want every amount, date, and tax-year label in the app to follow South African conventions so that my financial plan reads like it was built for my country, not translated from someone else's.
- As an **SA user**, I want form money fields to show `R` (not `£`) so that I never wonder which currency I'm entering.
- As a **GB user**, I want nothing about my display to change so that an international feature ships without disturbing my experience.
- As a **mobile user who logs out**, I want the login screen to show neutral defaults so that the previous account's formatting never leaks onto shared-device screens.

### Key scenarios

**Scenario 1 — SA login end-to-end:**
1. SA user logs in; `/api/auth/user` returns the WS1 jurisdiction fields plus the new `localisation` block (`ZAR / R / en_ZA / d M Y`) and `tax_year` block (`2026/27`, 1 Mar 2026 – 28 Feb 2027, resolved by core `TaxYearResolver` from the seeded `tax_years` row).
2. `jurisdiction/hydrateFromSession` stores them in the localisation singleton and the jurisdiction tax-year singleton.
3. Dashboard renders: `formatCurrency(15250.75)` → `R 15 251`; `formatDate` → `16 Jul 2026`; `getCurrentTaxYear()` → `2026/27`.
4. `taxConfig/fetchActive` sees a ZA-only session and returns null without calling the GB pack route.

**Scenario 2 — GB login (regression path):**
1. GB user logs in; payload carries the GBP-shaped block and `tax_year: null` (GB has no `tax_years` row — its year comes from the existing `TaxConfiguration` flow).
2. Singletons hold GBP config; every formatter takes the exact pre-WS3 code path; `fetchActive` runs as today and the admin tax-year override chain is untouched.

**Scenario 3 — Unhappy path (missing/malformed payload):**
1. A user has no jurisdiction rows, or the `localisation` block is absent/malformed, or the pack binding is missing.
2. Every layer fails open to GB behaviour: backend sends GB-shaped defaults; `setLocalisation(null)` clears the singleton; formatters render exactly as today. Preview mode requires no special handling — `/api/auth/user` is a GET and personas hold real jurisdiction rows.

**Scenario 4 — Mobile logout:**
1. SA user taps logout on iOS; `mobileLogout` now dispatches `jurisdiction/reset`, clearing both singletons.
2. The login screen renders neutral GB defaults; a subsequent Face ID login re-runs `fetchUser`, which re-hydrates — the biometric flow is unaffected because the server token is never revoked.

---

## 5. Functional Requirements

### Must-have
- **FR-M1:** `/api/auth/user` returns `data.localisation` (`currency_code`, `currency_symbol`, `locale`, `date_format` from `app("pack.{$primaryCode}.localisation")`) and `data.tax_year` (`label`, `starts_on`, `ends_on` from `TaxYearResolver::resolve`, null when no row), failing open to GB-shaped defaults. _Touches: `app/Http/Controllers/Api/AuthController.php` (`user()`), `tests/Feature/Auth/SessionJurisdictionTest.php`._
- **FR-M2:** A session localisation singleton normalising POSIX locales to BCP-47 once at set time, with set/get/reset. _Touches: `resources/js/utils/localisation.js` (new), `tests/frontend/utils/localisation.test.js`._
- **FR-M3:** `formatCurrency` / `formatCurrencyWithPence` / `formatCurrencyCompact` / `parseCurrency` / `formatNumber` adapt to session config: unset/GBP → byte-identical current path; `Currency ZAR` → deterministic `formatZAR` (rewritten: period decimal, U+00A0 grouping, sign before symbol) + new `formatZARCompact`; any other code → generic `Intl` from session locale/currency. _Touches: `resources/js/utils/currency.js`, `resources/js/utils/zaCurrency.js`, `resources/js/mixins/currencyMixin.js`._
- **FR-M4:** `formatDate` maps the pack's PHP-notation format (`d/m/Y` → current output, `d M Y` → `16 Jul 2026`, unknown → GB); `formatDateLong` uses the session locale; tax-year functions (`getTaxYearStart/End`, `getCurrentTaxYear`) consult the jurisdiction tax year first with boundary math derived from `starts_on` (leap-safe end dates), preserving the GB admin-override chain; `formatDateForInput` stays ISO. _Touches: `resources/js/utils/dateFormatter.js`._
- **FR-M5:** `jurisdiction/hydrateFromSession` and `jurisdiction/reset` set/clear both singletons; `auth/mobileLogout` gains the missing `jurisdiction/reset` dispatch. _Touches: `resources/js/store/modules/jurisdiction.js`, `resources/js/store/modules/auth.js`._
- **FR-M6:** `taxConfig/fetchActive` skips the GB endpoint only when the session holds ≥1 jurisdictions and none is GB (mirrors the middleware's fail-open logic; cross-border GB+ZA users keep fetching). _Touches: `resources/js/store/modules/taxConfig.js`._
- **FR-M7:** `CurrencyInputField.vue` renders the session currency symbol (fallback `£`). _Touches: `resources/js/components/Shared/CurrencyInputField.vue`._

### Should-have
- **FR-S1:** Correct the stale `ZaPreviewUserSeeder` docblock claiming the SA persona isn't selectable from the landing page (it is, and browser verification depends on it). _Touches: `packs/country-za/database/seeders/ZaPreviewUserSeeder.php` (comment only)._

### Nice-to-have
- **FR-N1:** None. The generic-currency `Intl` path (future packs work with zero frontend change) ships inside FR-M3 rather than as an optional extra.

---

## 6. User Flow & UX/Design

### Flow
```
login / app boot
  → auth/fetchUser → GET /api/auth/user
      → data.localisation + data.tax_year        (FR-M1)
  → jurisdiction/hydrateFromSession
      → setLocalisation(...) → utils/localisation.js singleton   (FR-M2, M5)
      → setJurisdictionTaxYear(...) → dateFormatter singleton
  → taxConfig/fetchActive — skipped for non-GB-holding sessions  (FR-M6)
  → any component calls formatCurrency / formatDate / getCurrentTaxYear
      → singleton consulted internally → ZA or GB or generic output (FR-M3, M4)
logout / exitPreview / mobileLogout → jurisdiction/reset → singletons cleared (FR-M5)
[unhappy branch] missing block / unknown code / unmapped format → GB output unchanged
```

### UX/Design notes
- **Design system:** No visual redesign — the only template change is `CurrencyInputField.vue`'s prefix text node; classes, spacing, and colours are untouched, so `fynlaDesignGuide.md` is not implicated.
- **Jurisdiction visibility:** Formatting adapts silently from the primary jurisdiction; the word "jurisdiction" never appears in UI (consistent with the `jurisdiction.js` store's existing rule).
- **Reusable components:** `CurrencyInputField.vue` (shared) adapts; ZA pack components keep `zaCurrencyMixin` deliberately (explicit ZAR regardless of session — correct for cross-border viewing of SA assets), now converging on the same rewritten `formatZAR`.
- **New components:** None. One new util module (`resources/js/utils/localisation.js`).
- **Responsive behaviour:** Standard — no layout changes. Note `R 1 234 567.89` is one character wider than `£1,234,567.89`; U+00A0 grouping prevents mid-amount line breaks.
- **Accessibility:** No interaction changes. Formatted output remains plain text readable by screen readers.
- **Reference artefacts:** SA formatting conventions per `Plans/SA_Research_and_Mapping.md` §17; WS1/WS2 session history in `July/July15Updates/` and `July/July16Updates/CSJTODO.md`.

---

## 7. Out of Scope

- Literal `£` in view/component templates (~30 files) **and** JS chart-formatter callbacks (e.g. `DashboardSparkline.vue:108`) — WS4's sweep, wording widened to cover both.
- Terminology map (`Localisation::getTerminology()`) exposure and consumption — WS4; the payload field ships when it has a consumer.
- Sidebar module hiding for SA users — WS4.
- Payment/subscription currency (`RevolutSubscriptionService` hardcodes GBP) — WS5.
- FX conversion / composite currency mixing for cross-border users — future workstream; WS3 formats by primary jurisdiction only.
- Marketing/public pages — no session, no jurisdiction; stay GB.
- Backend `ZaLocalisation::formatMoney` comma-decimal alignment (+ its test) — follow-up; not currently user-facing.
- `netWorth.js` hardcoded-GBP getters + orphaned `NetWorthOverviewCard.vue` — dead code, spun off as task chip `task_defce945`.
- **ZA wills capability** — required (CSJ, 16 July 2026): SA succession law is a separate legal system and needs its own implementation in `packs/country-za/`, not a re-skin of the GB `willDocumentRenderer.js` (which stays GB-only). Recorded on the programme backlog in `CSJTODO.md`; needs its own spec → plan → PRD cycle.
- Mobile `appStateChange` foreground re-hydration — deliberately skipped; jurisdiction never changes mid-session.

---

## 8. Risks & Dependencies

### Risks
| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `formatZAR` rewrite visibly changes 32+ ZA components (comma→period decimals, `-R` sign) with zero existing Vitest coverage on those components | High (it *will* change them — intentionally) | Low–Med | The change *is* the approved convention fix (SA Research §17). New unit tests pin the format; Task 8 Playwright pass eyeballs live ZA surfaces; deterministic output removes ICU variance permanently |
| GB regression sneaks through an untested formatter path | Low | High | Byte-for-byte GBP assertions in Vitest for every touched function; GB persona + `chris@fynla.org` browser passes; GBP branch hardcodes `en-GB` rather than trusting session locale |
| Tax-year boundary math wrong at edges (28 Feb / 1 Mar / leap years) | Low | Med | Explicit boundary and leap-year assertions (`2027-02-28` → `2026/27`, `2028-02-29` end date); backend resolver stays authoritative for the label |
| Mobile Face ID breaks from the `mobileLogout` reset | Low | High | Reset only clears formatting singletons — token revocation is untouched; `fetchUser` re-hydrates post-biometric; Task 8 includes the mobile-unaffected check |
| Vitest full run includes 7 legacy `__tests__/*.spec.js` files whose current state is unverified | Low | Low | Task 8 runs `npm run test:run` before any WS3 change lands as "done"; pre-existing failures (if any) get reported, not absorbed |

### Technical dependencies
- `Fynla\Core\Contracts\Localisation` + `pack.gb.localisation` / `pack.za.localisation` container bindings (exist, verified).
- `Fynla\Core\TaxYear\TaxYearResolver` + `tax_years` table; ZA row seeded by `ZaTaxConfigurationSeeder` (runs before `ZaPreviewUserSeeder` in `DatabaseSeeder` — dev DB ordering verified). GB deliberately has no row.
- WS1 session fields (`primary_jurisdiction` et al.) and dispatch ordering: `jurisdiction/hydrateFromSession` before `taxConfig/fetchActive` in `auth.js fetchUser` (verified).
- Pest auto-seeding does **not** populate `tax_years` — the new feature test inserts its own row (verified against `tests/Pest.php`).
- Vitest 3 + jsdom (installed); `tests/frontend/` conventions from WS1.

### Sequencing dependencies
- WS1 and WS2 shipped (both done, green).
- WS4 consumes WS3's singletons for its sweep and terminology work — WS3 blocks WS4.
- Task chip `task_defce945` (dead `NetWorthOverviewCard`) is independent — any order.

### Residual concerns from codebase audit
None — all audit findings addressed in the amended spec/plan, spun off (task chip `task_defce945`), or recorded on the programme backlog (ZA wills). The audit found zero factual conflicts between the documents and the codebase.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 16 July 2026 | Initial draft — from amended spec/plan after codebase audit + CSJ interview (gate semantics, mobile reset, dead-formatter chip, ZA wills backlog) | prd-writer skill |
