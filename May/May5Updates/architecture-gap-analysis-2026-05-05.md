---
type: gap-analysis
date: 2026-05-05
session: 4
branch: main
authoritative_plans:
  - Plans/multi_country_architecture.md
  - Plans/Implementation_Plan_v2.md
---

[[May Index]] | [[Home]]

# Architecture Gap Analysis — Plan vs Implementation

**The plan exists. The plan is unambiguous. The implementation has drifted. This document inventories the gap so we can decide how to close it.**

## What the plan says (verbatim)

### `Plans/multi_country_architecture.md`

- **§ 3.2 SA-only users see a SA-only product.** "A user whose active jurisdiction is ZA sees the module set described in the companion research document. Values are in ZAR. The tax year is 2026/27 on 1 March logic. Disclaimers are FAIS-framed. **ISA, SIPP, IHT, Annual Allowance do not appear.**"
- **§ 8.2 Router composition.** "Routes for inactive packs are not registered at all — not lazy-loaded-and-hidden, not registered. **A UK-only user's bundle download includes core + packs/gb only; the SA JavaScript is never shipped to them.**"
- **§ 8.3 Navigation manifest.** "Each pack exposes a navigation() function returning a list of sidebar items scoped to that pack. **The sidebar is a concatenation of navs from all active packs plus (if eligible) the global nav. The core layout simply renders the concatenated list.** This is how 'TFSA' stays invisible to a UK-only user: the pack that owns the TFSA nav item is never loaded."
- **§ 8.4 Component resolution.** A core helper `<CountryView name="…" />` resolves the correct pack component based on the currently-focused jurisdiction.
- **§ 8.5 Currency and date components.** `<Money>` and `<DateString>` consult the Localisation binding for the active jurisdiction.

### `Plans/Implementation_Plan_v2.md` (16 April 2026 — supersedes parts of the original)

- **§ 2.4** Pure UK user → "Pure UK app (current Fynla)". Pure SA user → "Pure SA app". Dual user → "Both module sets, under distinct headers in the sidebar" + cross-border section.
- **§ 3.3 Routing.** UK routes stay at their current paths (`/api/protection/*`, `/api/retirement/*`, etc.) — no breaking changes. **SA routes are mounted at `/api/za/*`.** `ActiveJurisdictionMiddleware` gates SA and cross-border routes — a UK-only user cannot access `/api/za/*`.
- **§ 3.4 Frontend.** "SA Vue components live in `resources/js/components/ZA/` and are lazy-loaded — a UK-only user never downloads them." Sidebar reads from jurisdiction state via a `sidebarModules` getter.

### Memory: `project_product_model.md`

- "The user never sees the word 'jurisdiction.' The phrase 'add a country.' A settings page for managing active countries."

---

## What's on-spec (no action needed)

| Item | Where | Notes |
|---|---|---|
| ZA pack as Composer package at `packs/country-za/` | `packs/country-za/` | ✓ |
| `ZaPackServiceProvider` registers `pack.za.*` bindings | `packs/country-za/src/Providers/ZaPackServiceProvider.php` | All 12 contracts + sub-services |
| ZA pack tables prefixed `za_*` | `packs/country-za/database/migrations/` | 10+ migrations all prefixed |
| ZA backend routes at `/api/za/*` | `packs/country-za/routes/api.php` | Behind `auth:sanctum`, `active.jurisdiction`, `pack.enabled:za` |
| `ActiveJurisdictionMiddleware` gates ZA backend routes | `core/app/Core/Http/Middleware/` (referenced from `routes/api.php:1242`) | UK-only user gets 403 on `/api/za/*` |
| Auth response exposes `active_jurisdictions` | `app/Http/Controllers/Api/AuthController.php:374` | Frontend can read user's jurisdictions |
| `ZaLocalisation` implements `Fynla\Core\Contracts\Localisation` | `packs/country-za/src/Localisation/ZaLocalisation.php` | Bound at `pack.za.localisation` |
| UK code stays in `app/` (per Plan v2 §1) | `app/Services/`, `app/Http/`, etc. | Workstream E revert held |
| UK routes stay unprefixed (per Plan v2 §3.3) | `routes/api.php` | Explicitly on-spec for v2 |
| `GbPackServiceProvider` exists with `pack.gb.*` bindings | `app/Providers/GbPackServiceProvider.php` | 7 of 12 contracts bound |
| `jurisdiction.js` Vuex module exists | `resources/js/store/modules/jurisdiction.js` | State + getters present |
| ZA frontend route components are lazy-loaded | `resources/js/router/index.js:110-114` | `() => import('@/views/ZA/…')` |
| ZA pages live under `resources/js/components/ZA/` and `resources/js/views/ZA/` | filesystem | Architecturally isolated |

---

## Gaps — drift from plan

Severity scale:
- **CRITICAL** — directly breaks the "Pure UK app / Pure SA app" UX promise visible to users
- **HIGH** — architectural compliance failure that drives the CRITICAL surface symptoms
- **MEDIUM** — capability missing but not yet user-visible
- **LOW** — cosmetic divergence from plan layout, no functional impact

### CRITICAL

#### G1 — UK modules hardcoded inline in `SideMenu.vue`

**Plan:** § 8.3 — "the sidebar is a concatenation of navs from all active packs"; § 2.4 — pure SA user sees "Pure SA app".

**Reality:** `resources/js/components/SideMenu.vue` lines 53–98 contain **28 hardcoded `<SideMenuItem>` declarations** for UK modules (Dashboard, Net Worth, Bank Accounts, Income, Expenditure, Investments, Retirement, Property, Liabilities, Personal Valuables, Risk Profile, Business, Protection, Savings, Investment, Estate, Goals, Plans, Holistic Plan, Journeys, What If, Life Events, Actions, etc.). They render unconditionally on every authenticated page regardless of `active_jurisdictions`.

`hasGb` does not exist as a computed (`grep -c "hasGb" SideMenu.vue` → 0). Only `hasZa` is checked, gating only the bolted-on "South Africa" subsection at lines 100–115.

**User-visible symptom:** A pure SA user (`active_jurisdictions = ['za']`) sees the entire UK sidebar plus a "South Africa" subsection. They have access to UK pages they cannot use.

#### G2 — UK frontend routes are not gated on `gb` jurisdiction

**Plan:** § 8.2 — "Routes for inactive packs are not registered at all".

**Reality:** Every UK route in `resources/js/router/index.js` is registered unconditionally with `meta: { requiresAuth: true }`. None carry `meta: { requiresJurisdiction: 'gb' }` (only ZA routes do — lines 688, 701, 714, 727, etc.). A SA-only user can `router.push('/protection')`, `/savings`, `/retirement`, `/dashboard` and see UK content.

**User-visible symptom:** Same as G1 — SA-only user can navigate into the UK app.

#### G3 — UK pages bundle is shipped to all users

**Plan:** § 8.2 — "A UK-only user's bundle download includes core + packs/gb only; the SA JavaScript is never shipped to them" (and conversely).

**Reality:** UK components are imported directly (not lazy + jurisdiction-conditional) at the top of `router/index.js` and inlined in many views. There is no `frontend/packs/gb/` bundle, no `resources/js/packs/gb/` bundle. The lazy-import pattern is only used for individual route components, not for whole pack bundles.

**User-visible symptom:** A pure SA user downloads the entire UK JS bundle. Mirror problem for UK users vs SA. Not user-visible per se, but it blocks the "Pure UK / Pure SA app" promise from being structurally enforced.

### HIGH

#### G4 — `MODULES_BY_JURISDICTION.gb` data shape can't drive the sidebar

**Plan:** § 8.3 — sidebar concatenates pack navs.

**Reality:** `resources/js/store/modules/jurisdiction.js:34–43`:

```js
gb: [
  'protection',
  'savings',
  'investment',
  'retirement',
  'estate',
  'goals',
  'coordination',
],
```

UK modules are bare strings. ZA modules below them have full `{key, label, route, icon, section}` objects. Even if SideMenu wanted to render UK from this getter, it has no `route` or `icon` to use.

The `sidebarModules` getter at lines 95–113 mixes both shapes and returns string keys. **No component consumes this getter** (`grep -n "sidebarModules" SideMenu.vue` → 0 matches). The getter is dead code today.

**Impact:** Closing G1 requires first closing G4 (give UK entries the same shape as ZA entries) and rewiring SideMenu to consume `sidebarModules`.

#### G5 — Sidebar uses a jurisdiction-named header for ZA only

**Plan v2 § 2.4:** dual user — "Both module sets, under distinct headers in the sidebar".

**Reality:** `<SideMenuSection v-if="hasZa" label="South Africa">` is rendered for any user with ZA in `active_jurisdictions`, regardless of whether they also have GB. UK modules are not similarly bucketed under a "United Kingdom" header — they sit under generic functional headers ("Cash Management", "Finances", "Planning", etc.) at the top of the sidebar.

For a pure SA user this is wrong (they shouldn't see a "South Africa" header — they should just see modules, "Pure SA app").
For a dual user this is half-wrong (they should see distinct UK and SA headers — they currently see UK functional groupings + a SA jurisdiction grouping).

#### G6 — ZA route guards exist but registration is unconditional

**Plan:** § 8.2 — "not lazy-loaded-and-hidden, not registered".

**Reality:** ZA routes are registered for every user, then gated by a `requiresJurisdiction: 'za'` route meta + a global navigation guard. The component imports are lazy (`() => import(...)`) so the JS isn't fetched until navigation, but the route entries themselves exist in the router for everyone.

**Impact:** Less severe than G2 because guards do block access. But it doesn't fulfil the bundle-size / JS-isolation promise of § 8.2 for *route definitions* (the entries themselves leak into all bundles).

### MEDIUM

#### G7 — 4 UK pack contracts intentionally not bound

**Reality:** `app/Providers/GbPackServiceProvider.php:18–22` documents the gap explicitly:

> "Contract gap (documented): pack.gb.localisation, pack.gb.identity, pack.gb.banking, pack.gb.life_tables are intentionally NOT bound here. The equivalent UK classes (GbLocalisation, NationalInsuranceValidator, GbBankingValidator, GbLifeTableProvider) need to be created as the UK side catches up with the ZaLocalisation / ZaIdValidator / …"

**Impact:** Cross-border features (Phase 3) and dual-user flows that need to format UK money via the contract will fall back or fail. Phase 1 / 2 functional today.

#### G8 — `<CountryView>` resolver component not implemented

**Plan:** § 8.4 — generic component resolves correct pack component by jurisdiction.

**Reality:** No `<CountryView>` exists. UK and SA components are referenced directly. The plan's example was a "shared concept with different shapes" (e.g. retirement-account form). For dual users navigating between UK and SA assets, there is no single component that forwards.

**Impact:** Dual-user UX (Phase 2) blocked. Phase 1 unaffected — SA-only and UK-only users use their own components directly.

#### G9 — `<Money>` and `<DateString>` localisation components not implemented

**Plan:** § 8.5 — `<Money :value />` consults `Localisation` binding for the focused jurisdiction.

**Reality:** No such components. The frontend uses `currencyMixin` which is UK/GBP-defaulting. ZA components format ZAR via inline `ZaLocalisation::formatMoney` calls (backend) or local utilities. No automatic switching.

**Impact:** Dual users (Phase 2) won't see correct currency without route-by-route rewrites. Phase 1 functional.

#### G10 — vue-i18n is not pack-scoped

**Plan:** § 8.6 — keys like `fynla.gb.protection.*` and `fynla.za.protection.*` carry materially different copy.

**Reality:** Module copy lives in component templates and string literals. No vue-i18n integration at all. Country-specific copy is hardcoded per component (e.g. `ZaProtectionEngine` returns formatted ZAR strings; UK components hardcode GBP / FCA wording).

**Impact:** Dual-user copy (Phase 2/3) blocked. Phase 1 ships ZA copy hardcoded into ZA components (acceptable but won't scale).

#### G11 — `JurisdictionDetectionObserver` not wired to asset CRUD

**Plan v2 § 0.6:** Adding an asset with a foreign location auto-activates the new jurisdiction (`auto_detected = true`).

**Reality:** The observer class exists in core (`core/app/Core/Observers/JurisdictionDetectionObserver.php` per plan). Whether it's registered on UK asset models (Property, Investment, etc.) or only on a stub set is unverified in this audit. Asset add/edit forms don't appear to expose a country-location field on UK pages today.

**Impact:** The plan's flagship "automatic cross-border" UX from memory is not wired end-to-end. To activate ZA from a UK app's asset entry would need this layer. For now ZA jurisdiction comes from explicit registration / seeding, not asset location.

### LOW

#### G12 — Frontend pack location diverges from plan layout

**Plan:** § 6.1 puts SA Vue at `frontend/packs/za/`. Implementation has them at `resources/js/components/ZA/`, `resources/js/views/ZA/`, `resources/js/store/modules/za*`.

**Impact:** Cosmetic. Files are still isolated under a ZA-named subtree. Either compatible with the v2 plan since v2 `§ 3.4` references `resources/js/components/ZA/`.

#### G13 — Geolocation registration not verified

**Plan v2 § 0.4 + § 2.2:** IP geolocation pre-fills registration with country.

**Reality:** Not verified in this audit. Registration controller and `GeoLocationService` both exist per the plan but their wiring to set primary jurisdiction is unconfirmed.

**Impact:** New users may have to be manually assigned a jurisdiction. Doesn't block Phase 1 functionally for seeded test users.

---

## Summary

The plan promises "Pure UK app / Pure SA app / dual user with both + cross-border". The implementation delivers "UK app + bolted-on ZA section". The gap is concentrated in five CRITICAL/HIGH items (G1–G5) all rooted in one root cause:

> **Root cause:** WS 1.2b (April 18) made *only* the ZA half of the sidebar / route layer data-driven. The UK half stayed hardcoded. WS 1.3c – 1.5b each appended one ZA module without re-questioning that the architecture itself was incomplete. The PRD process for each WS validated against existing code shape, not against `multi_country_architecture.md`. Drift compounded.

The remaining MEDIUM/LOW items (G7–G13) are capability gaps for Phase 2/3 (cross-border, dual-user UX) — they do not affect the "Pure SA app" promise being broken today.

---

## Realignment options

### Option A — Retrofit (close the gap on existing code)

1. Add full `{key, label, route, icon, section}` shape to `MODULES_BY_JURISDICTION.gb`.
2. Add `hasGb` computed to `SideMenu.vue`; rewire all UK sidebar items to consume `sidebarModules` getter.
3. Add `requiresJurisdiction: 'gb'` meta to every UK route definition; register UK routes conditionally based on auth-session jurisdiction state.
4. Restructure UK component imports into `resources/js/packs/gb/` bundle that is conditionally loaded after auth.
5. Wire `<CountryView>`, `<Money>`, `<DateString>` (G8–G9).
6. Pack-scope vue-i18n (G10).
7. Bind missing UK contracts (G7).
8. Wire `JurisdictionDetectionObserver` to UK asset models (G11).
9. Verify geo-registration (G13).
10. Playwright regression: pure UK, pure SA, dual users, on every module.

**Estimated effort:** 2–3 weeks. Risk: high — the existing UK code wasn't authored for jurisdiction-conditional rendering; retrofitting may surface latent assumptions.

### Option B — Revert WS 1.2b – 1.5b and rebuild SA on the correct foundation

1. Revert all WS 1.2b – 1.5b commits (SA frontend).
2. Refactor `SideMenu.vue` and `router/index.js` first to be fully data-driven for UK (close G1, G2, G4, G5 cleanly without ZA in the picture).
3. Build a proper `resources/js/packs/gb/` bundle and migrate UK views into it (close G3).
4. Re-build SA frontend on the now-correct foundation (re-shipping WS 1.2b – 1.5b features against the right architecture).
5. SA backend (controllers, models, migrations, seeders, tests) stays as-is — none of that is wrong, the drift is purely frontend.

**Estimated effort:** 4–5 weeks. Risk: medium — bigger but on a clean foundation; SA backend already correct so the frontend rebuild has a working API.

### Option C — Hybrid (UK refactor first, then incrementally fix ZA pages)

1. UK sidebar/routes refactored to fully data-driven first (close G1, G2, G4, G5).
2. UK frontend pack bundle created (close G3).
3. ZA pages then conditionally load on top of the new architecture, no need to revert.
4. Defer G7–G13 to Phase 2/3 sequencing.

**Estimated effort:** 3 weeks. Risk: medium-low — preserves shipped SA work, fixes the structural drift in one focused workstream.

---

## Recommendation

**Option C.** The SA frontend pages (WS 1.2b – 1.5b) are functionally correct; only the surrounding shell (sidebar, routing, bundle) is wrong. Reverting them costs 4+ weeks of shipped work for no functional gain. Retrofitting UK first and then re-pointing the existing ZA pages onto the corrected shell preserves the work and closes the architectural gap in one focused sprint.

Phase 1 (G1–G5) closes the "Pure UK / Pure SA app" promise. Phase 2 (G7–G11) closes dual-user / cross-border capability. Phase 3 (G12–G13) cleans cosmetics.

---

## Awaiting decision

Do not proceed with any cleanup PRs (DialogContainer, Tabs, etc.) or new feature workstreams (WS 1.6b SA Estate) until this realignment is decided. WS 1.6b on the current architecture would compound the drift further.

Three open decisions for the user:

1. Approve **Option A / B / C** (or define D).
2. Approve treating the realignment as a workstream needing its own spec → plan → PRD per the project workflow rule, or treat it as a defect remediation that can run with a lighter ceremony.
3. Approve whether `superpowers:brainstorming` should run on the realignment plan, or whether the existing plans (multi_country_architecture.md, Implementation_Plan_v2.md) are sufficient as the spec.
