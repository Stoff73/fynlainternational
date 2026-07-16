---
type: spec
date: 2026-07-16
workstream: WS3 — Frontend Localisation (SA user sees R / SA dates / 1-March tax year)
program: International Layer (WS1–WS6)
status: APPROVED (design approved by CSJ 2026-07-16; not yet implemented)
branch: feat/international-layer
depends_on: WS1 (jurisdiction lifecycle) — DONE; WS2 (query-layer un-null) — DONE
---

# WS3 — Frontend Localisation

## Program context

Third of six international-layer workstreams. End-to-end goal: an SA user sees
a coherent, correct, ZAR SA experience. WS1 gave every user a primary
jurisdiction; WS2 made their SA assets reach core aggregation. **WS3's slice:
the SPA's formatting layer adapts to the user's primary jurisdiction — an SA
user sees `R 1 234.56`, `16 Jul 2026` dates, and the 1-March `2026/27` tax
year instead of hardcoded `£1,234` / `16/07/2026` / 6-April.**

## Problem (traced)

Backend localisation exists per pack but never reaches the SPA:

- `Fynla\Core\Contracts\Localisation` (currencyCode / currencySymbol / locale /
  dateFormat / formatMoney / getTerminology) is bound per pack as
  `pack.gb.localisation` → `GbLocalisation` and `pack.za.localisation` →
  `ZaLocalisation` (`packs/*/src/Providers/*PackServiceProvider.php`).
- `AuthController::user` (`app/Http/Controllers/Api/AuthController.php:352`)
  already returns `active_jurisdictions` / `primary_jurisdiction` /
  `cross_border`, and `jurisdiction.js` hydrates them — but no localisation
  fields are sent.
- `resources/js/utils/currency.js` hardcodes `Intl.NumberFormat('en-GB',
  {currency: 'GBP'})` and literal `£` in `formatCurrencyCompact`.
- `resources/js/mixins/currencyMixin.js` `formatNumber` hardcodes
  `toLocaleString('en-GB')`. ~126 component files consume these.
- `resources/js/utils/dateFormatter.js` hardcodes DD/MM/YYYY (`formatDate`),
  `en-GB` (`formatDateLong`), and the 6-April UK tax year
  (`getTaxYearStart/End`, `getCurrentTaxYear`). 19 files call the tax-year
  functions.
- **Latent ZA bug:** `taxConfig/fetchActive` (dispatched unconditionally from
  `App.vue:34` on login) calls the GB pack route `/api/gb/tax-year/current`.
  For a ZA-primary user this is a cross-pack call that WS1's
  `ActiveJurisdictionMiddleware` blocks — the store silently falls back to the
  GB calendar year.
- `resources/js/utils/zaCurrency.js` (`formatZAR` → `R 1 234 567.89`, period
  decimal, U+00A0 grouping, per SA Research §17) is live in 32 ZA pack
  components via `zaCurrencyMixin`, but nothing outside explicitly-ZA
  components uses it.

### Known convention conflict (resolved by this spec)

Backend `ZaLocalisation::formatMoney` renders comma-decimal (`R 1 234 567,89`,
asserted in `packs/country-za/tests/Unit/ZaLocalisationTest.php`); frontend
`formatZAR` renders period-decimal (`R 1 234 567.89`, per SA Research §17).
**The frontend/research convention wins**: WS3 routes all frontend ZAR
formatting through `formatZAR`, so the app shows one ZA convention everywhere.
Backend `formatMoney` is not currently user-facing; aligning it (+ its test)
is a follow-up, not WS3.

### Tax-year data (traced)

Core `Fynla\Core\TaxYear\TaxYearResolver::resolve(code, ?date): TaxYear`
resolves from the `tax_years` table. Seeded today: one ZA row — label
`2026/27`, `starts_on 2026-03-01`, `ends_on 2027-02-28` (ADR-006,
`ZaTaxConfigurationSeeder`). **GB has no `tax_years` row** — the GB active
year comes from `TaxConfiguration` via `/api/gb/tax-year/current` and the
existing `setActiveTaxYear` override in `dateFormatter.js`. The two mechanisms
must not fight (see Design §4).

## Approach decision

**Chosen: module-level config singleton inside the existing utils.** A tiny
`resources/js/utils/localisation.js` holds the session's localisation config;
`currency.js`, `dateFormatter.js`, and `currencyMixin.formatNumber` read it
internally. Zero changes to the ~126 consumer files. This follows the existing
in-repo precedent: `_activeTaxYearFromBackend` + `setActiveTaxYear()` in
`dateFormatter.js`, populated from a Vuex store on login.

Rejected:
- **Vuex-reactive formatting** — utils are imported by non-component code
  (charts, services) with no store access, and a user's primary jurisdiction
  never changes mid-session, so reactivity buys nothing for a much larger diff.
- **vue-i18n number/date formats** — new dependency plus a big-bang migration
  of 126 files, for two locales.

## Design

### 1. Backend — session payload

`AuthController::user` additionally returns (inside `data`):

```json
"localisation": {
  "currency_code": "ZAR",
  "currency_symbol": "R",
  "locale": "en_ZA",
  "date_format": "d M Y"
},
"tax_year": {
  "label": "2026/27",
  "starts_on": "2026-03-01",
  "ends_on": "2027-02-28"
}
```

- Resolved from `app("pack.{$primaryCode}.localisation")` for the user's
  primary jurisdiction, and `TaxYearResolver::resolve($primaryCode)`.
- **Guards (fail-open to GB, mirroring WS1):** no primary jurisdiction, or the
  container binding missing → GB-shaped defaults
  (`GBP / £ / en_GB / d/m/Y`). No `tax_years` row for the jurisdiction (GB
  today) → `"tax_year": null`.
- GB users therefore receive the GBP block + `tax_year: null` — and the
  frontend treats that combination as "behave exactly as today".
- `getTerminology()` is deliberately **not** sent. WS4 (SA experience
  surfacing) consumes terminology; ship the field when it has a consumer.

### 2. Frontend — hydration lifecycle

- New `resources/js/utils/localisation.js`: module-level
  `{ currencyCode, currencySymbol, locale, dateFormat }` (null when unset)
  with `setLocalisation(config)` / `getLocalisation()` /
  `resetLocalisation()`. Imported by `currency.js`, `dateFormatter.js`,
  `currencyMixin`, and `CurrencyInputField.vue` — one source, no circular
  imports (localisation.js imports nothing).
- `setLocalisation` normalises the POSIX locale from the contract
  (`en_ZA`) to BCP-47 (`en-ZA`) **once at set time** — `Intl.NumberFormat`
  and `toLocaleString` throw `RangeError` on underscore locales, and no
  formatter should re-do the conversion per call.
- `jurisdiction/hydrateFromSession` (already dispatched from
  `auth.js` on every session fetch) additionally calls
  `setLocalisation(payload.localisation)` and
  `setJurisdictionTaxYear(payload.tax_year)` (dateFormatter export, §4).
- `jurisdiction/reset` (already dispatched on logout) calls
  `resetLocalisation()` and `setJurisdictionTaxYear(null)`.
- Logged-out / public pages: config unset → GB defaults everywhere.

### 3. `currency.js` (+ mixin + shared input)

- `formatCurrency` / `formatCurrencyWithPence` / `formatCurrencyCompact`
  consult the config first:
  - unset or `GBP` → the **exact current en-GB code path** (UK regression
    surface: zero);
  - `ZAR` → delegate to `formatZAR` (`zaCurrency.js`), plus a compact ZAR
    variant (`R 1.2M` / `R 12.3K`) added in `zaCurrency.js`;
  - any other code → generic
    `Intl.NumberFormat(locale, { style: 'currency', currency })` (future
    packs work with no frontend change).
- `parseCurrency` strips the configured `currency_symbol` in addition to `£`.
  (No component currently calls it — mixin re-export only — but keeping it
  symbol-aware is one line.)
- `currencyMixin.formatNumber` → `toLocaleString(locale from config,
  fallback 'en-GB')`. `formatLiability` inherits via `formatCurrency`.
- `components/Shared/CurrencyInputField.vue`: the literal `£` prefix span
  renders the session `currency_symbol` (static per session; read at render).

### 4. `dateFormatter.js`

- `formatDate` maps the payload's PHP-notation `date_format`:
  `d/m/Y` → current DD/MM/YYYY output; `d M Y` → `16 Jul 2026`
  (en-ZA medium); **unknown format string → GB default** (fail-open). A
  two-entry map, not a PHP-format interpreter.
- `formatDateLong` uses the config locale (`en_GB` → `en-GB` etc.) instead of
  hardcoded `'en-GB'`.
- `formatDateForInput` (ISO, feeds `<input type="date">`) and
  `parseDate` / `calculateAge` / `getRelativeTime` unchanged.
- New module singleton `_jurisdictionTaxYear` (`{label, startsOn, endsOn}` or
  null) + exported `setJurisdictionTaxYear()`. Tax-year functions consult it
  first:
  - `getTaxYearStart/End(referenceDate?)`: when set, derive the boundary
    month/day from `startsOn` (1 March for ZA) and apply the same
    calendar-roll logic for any `referenceDate` — the 19 caller files,
    including explicit-date calls, stay correct.
  - `getCurrentTaxYear(referenceDate?)`: precedence — explicit
    `referenceDate` → boundary math; else `_jurisdictionTaxYear.label`; else
    `_activeTaxYearFromBackend` (GB admin override, unchanged); else GB
    calendar.
  - The two singletons never fight: GB users get `tax_year: null` (resolver
    has no GB row) so the existing fetchActive/admin chain runs; ZA users skip
    fetchActive entirely (§5).

### 5. `taxConfig/fetchActive` gating

Guard inside the action (one place, not per dispatch site): only call
`/api/gb/tax-year/current` when the jurisdiction store's primary is `'gb'` or
unset. ZA-primary users no longer fire a cross-pack request that the WS1
middleware rejects.

### 6. Error handling summary

Fail-open to GB formatting at every layer: missing/malformed payload block,
unknown currency code (→ generic Intl), unmapped date format, resolver
returning nothing, logged-out pages. A GB user's rendered output is
byte-for-byte identical to today in all paths.

## Testing

- **Pest (backend):** `/api/auth/user` payload — ZA-primary user gets the ZAR
  localisation block + `2026/27` tax_year; GB-primary user gets GBP block +
  `tax_year: null`; user with no jurisdiction rows gets GB-shaped defaults.
- **Vitest (frontend — runner + existing specs present):**
  `localisation.js` set/get/reset; `formatCurrency` GBP/unset (unchanged
  output), ZAR (`R 1 234.56`, compact variants), unknown code (generic Intl);
  `formatNumber` locale; `formatDate` both mapped formats + unknown-format
  fallback; tax-year boundary maths either side of 1 March (e.g. 28 Feb 2027
  → `2026/27`, 1 Mar 2027 → next year) and precedence vs `setActiveTaxYear`.
- **Browser (Playwright, per CLAUDE.md rules):** log in as `sa_professional`
  → dashboard/module pages show `R` amounts, `2026/27` tax year, `d M Y`
  dates; log in as a GB persona → £ / 6-April output unchanged.

## Out of scope (assigned elsewhere)

- Literal-`£` sweep in views/components (~30 files incl. `Dashboard.vue`) and
  the `ZaEstateDashboard.vue` £→R fix — **WS4** (SA experience surfacing).
- Terminology map exposure/consumption — **WS4**.
- Sidebar module hiding for SA users — **WS4**.
- Payment/subscription currency — **WS5**.
- FX conversion / composite currency mixing for cross-border users — future
  workstream (WS3 formats per primary jurisdiction; ZA pack components stay
  explicitly ZAR via `zaCurrencyMixin`).
- Marketing/public pages (no session, no jurisdiction) — stay GB.
- Backend `ZaLocalisation::formatMoney` comma-decimal alignment — follow-up.
