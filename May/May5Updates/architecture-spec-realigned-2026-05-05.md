---
type: spec
date: 2026-05-05
session: 4
supersedes_ambiguity_in:
  - Plans/multi_country_architecture.md
  - Plans/Implementation_Plan_v2.md
status: re-affirmed authoritative spec
---

[[May Index]] | [[Home]]

# Architecture Spec — Re-affirmed (Fynla International)

This document re-states the authoritative architectural intent for Fynla International, with one critical clarification (sidebar shape) baked in. It supersedes any ambiguity in the existing plans where they conflict with this restatement. Where the existing plans are unambiguous, they remain authoritative and are referenced here verbatim.

This is the spec. The companion plan in `architecture-plan-realigned-2026-05-05.md` describes how implementation gets from the current drifted state to this spec.

---

## 1. Product promise

A user opens fynla.org. Based on their primary jurisdiction:

- A **UK user** sees a UK financial planning app. The currency is GBP. The tax year runs 6 April – 5 April. ISA, SIPP, IHT, Annual Allowance — these belong to them. FAIS, TFSA, RA, Estate Duty, Reg 28 — these are invisible to them.
- A **SA user** sees a South African financial planning app. The currency is ZAR. The tax year runs 1 March – 28/29 February. TFSA, RA, PF, PvF, Estate Duty, Two-Pot, FAIS — these belong to them. ISA, SIPP, IHT, Annual Allowance — these are invisible to them.
- A **dual-jurisdiction user** sees both country experiences plus a cross-border layer for residency, DTA, worldwide estate, exchange control, and FX. The cross-border layer activates automatically when a user has assets in two jurisdictions; it disappears when they don't.

The user never sees the word "jurisdiction." There is no "manage countries" page. There is no "select your country" dropdown for a single-jurisdiction user. The system shapes itself around the user's financial reality.

## 2. Country-agnostic sidebar (the clarification)

The sidebar shape is country-agnostic. UK and SA users see structurally identical sidebars: the same labels, the same iconography, the same section groupings. What differs is what each link does.

| Sidebar item | UK user | SA user |
|---|---|---|
| Dashboard | UK dashboard | SA dashboard |
| Net Worth | UK Net Worth views (GBP, UK assets) | SA Net Worth views (ZAR, SA assets) |
| Savings | UK Savings (ISA, GIA, fixed savings) | SA Savings (TFSA, fixed deposit, money market) |
| Investment | UK Investment (ISA, GIA, VCT, EIS) | SA Investment (Discretionary, Endowment, Offshore) |
| Retirement | UK Retirement (SIPP, workplace DC/DB, State Pension) | SA Retirement (RA, PF, PvF, Preservation, Living/Life Annuity) |
| Protection | UK Protection (Life, Critical Illness, Income Protection) | SA Protection (Life, Dread Disease, Disability, Funeral) |
| Estate Planning | UK Estate (IHT, NRB/RNRB, Trusts) | SA Estate (Estate Duty, Donations Tax, Liquidity Test) |
| Goals & Life Events | Same shape; goal types differ per pack | Same shape; goal types differ per pack |
| Plans / Holistic Plan / Journeys / What If / Actions | UK content | SA content |
| Cash Management (Bank Accounts, Income, Expenditure) | UK content | SA content |

**There is no "South Africa" sidebar header. There is no "United Kingdom" sidebar header. The sidebar is a single, unified shape, parameterised by the user's active jurisdictions.**

For the dual user (Phase 2), the same sidebar shape is preserved. The cross-border section appears as an additional, jurisdiction-agnostic group in the sidebar (e.g. "Cross-Border" with items like "Residency", "Worldwide Estate", "Exchange Control"). Per-module navigation (Savings, Retirement, etc.) remains a single unified item — it routes to a jurisdiction-aware view that surfaces both UK and SA assets. Detailed dual-user UX is in scope for Phase 2 and not re-specified here beyond this principle.

This supersedes the wording in `Implementation_Plan_v2.md § 2.4` ("Both module sets, under distinct headers in the sidebar") which described an earlier draft. The clarification: **distinct headers were never the intent — the sidebar is one shape; the back-end routing is what differs by jurisdiction.**

## 3. What lives where (architecture)

Unchanged from `Plans/multi_country_architecture.md` and `Plans/Implementation_Plan_v2.md § 3`:

- **`app/`** — UK code. Stays here. Untouched. All 940+ tests pass from day 1.
- **`core/app/Core/`** — Country-agnostic infrastructure. Contracts, Money, TaxYear, Jurisdiction, PackRegistry, ActiveJurisdictionMiddleware.
- **`packs/country-za/`** — SA pack as a Composer package. Implements 12 contracts. Pack-owned tables prefixed `za_*`. Backend routes mounted at `/api/za/*`. Service provider auto-discovered.
- **`packs/cross-border/`** — Optional, depends on ≥ 2 country packs. Phase 3.
- **`resources/js/packs/gb/`** — UK frontend feature bundle (NEW location for restructured UK frontend). Views, route definitions, navigation manifest. Lazy-loaded for users with `gb` in `active_jurisdictions`.
- **`resources/js/packs/za/`** — SA frontend feature bundle (NEW location; existing files at `resources/js/components/ZA/`, `resources/js/views/ZA/`, `resources/js/store/modules/za*` migrate here).
- **`resources/js/core/`** — Country-agnostic frontend (router shell, layouts, design system primitives, auth, jurisdiction state).

The frontend pack layout is the architectural target. The current paths at `resources/js/components/ZA/` etc. are not strictly wrong (Plan v2 § 3.4 references them), but the realignment will normalise on the pack-bundle layout for both UK and SA so the structural promise of § 8.2 (per-pack bundle isolation) holds.

## 4. Routing

### 4.1 Backend

Per `Plan v2 § 3.3`:

- **UK backend routes are unprefixed.** `/api/protection/*`, `/api/savings/*`, `/api/retirement/*`, etc. They are bound to UK pack contracts via `pack.gb.*` container resolution.
- **SA backend routes are prefixed `/api/za/*`.** Mounted by `ZaPackServiceProvider`. Behind `auth:sanctum`, `active.jurisdiction`, `pack.enabled:za`.
- **Cross-border routes are prefixed `/api/global/*`.** Phase 3.
- **Core routes are prefixed `/api/core/*`** (auth, household, goals, coordination, billing).

`ActiveJurisdictionMiddleware` rejects requests for a pack the user does not have. UK-only user gets 403 on `/api/za/*`; SA-only user gets 403 on UK pack routes (this requires UK pack routes to register themselves with the middleware — see plan).

### 4.2 Frontend

Per `multi_country_architecture.md § 8.2`:

- **Routes for inactive packs are not registered.** A UK-only user's router contains only core + GB pack routes. A SA-only user's router contains only core + ZA pack routes. A dual user's router contains core + both packs + cross-border.
- **Bundle isolation.** A UK-only user's JS bundle does not include SA pack JS, and vice versa. Pack bundles are dynamically imported after the auth response delivers `active_jurisdictions`.
- **URL paths are country-agnostic.** A SA user navigates to `/savings` and sees the SA Savings page. A UK user navigates to `/savings` and sees the UK Savings page. The pack provides the route definition for `/savings`. Only one pack's `/savings` route is registered for any given user.

The current `/za/savings` URLs are a drift artefact of the bolt-on pattern. They are realigned to unprefixed paths under the pack-isolation model.

For dual users (Phase 2), if both packs claim the same URL path, the registration order is determined by `primaryJurisdictionCode`. A "currently-focused jurisdiction" mechanism (per `multi_country_architecture.md § 8.4`) lets dual users toggle which pack a shared-name URL resolves to. Phase 2 detail.

## 5. Navigation manifest

Per `multi_country_architecture.md § 8.3`:

- **Each pack exposes a `navigation()` function** returning a list of sidebar items scoped to that pack. Each item has `{key, label, route, icon, section}`.
- **The sidebar is a concatenation** of navs from active packs plus cross-border (when eligible).
- **Pack labels collide deliberately.** GB pack's nav and ZA pack's nav both include an item with `label: 'Savings'`, `route: '/savings'`. For a single-jurisdiction user, only their pack's nav loads — no collision. For a dual user, the concatenation de-duplicates by `key` (or merges into a single shared-concept entry per § 8.4).

This is what enables the country-agnostic sidebar promise of § 2: same labels, same shape, different pack provides them.

## 6. Component resolution

Per `multi_country_architecture.md § 8.4`:

- Shared-concept components (e.g. "Savings dashboard", "Retirement form") live one-per-pack under their respective frontend bundles.
- The router resolves the correct pack component for a given URL based on which packs are active.
- For dual users, `<CountryView name="…" />` resolves the correct component based on `currently-focused jurisdiction`. Phase 2.

## 7. Localisation

Per `multi_country_architecture.md § 8.5, § 8.6`:

- `<Money :value />` and `<DateString :date />` consult the `Localisation` binding for the currently-focused jurisdiction. UK-only user always sees GBP; SA-only user always sees ZAR. Dual user sees the currency of whichever asset they're viewing.
- vue-i18n keys are pack-scoped: `fynla.gb.savings.title`, `fynla.za.savings.title`. Identical keys carry country-specific copy.

## 8. Data model

Per `Plan v2 § 3.5`:

- UK tables unprefixed. SA pack tables prefixed `za_*`. Core tables unprefixed.
- Every country-scoped entity has a `country_code` column (or jurisdiction-equivalent FK).
- Money columns are integer minor units with a sibling `currency_code` column.
- FX rates table populated only when cross-border is active.

## 9. Jurisdiction lifecycle

Per `project_product_model.md` (memory):

- Registration is geo-determined. IP geolocation sets primary jurisdiction. User never selects a country.
- Cross-border activates automatically when a user adds an asset in a country that isn't currently active. The `JurisdictionDetectionObserver` watches asset CRUD and updates `user_jurisdictions`.
- Cross-border deactivates when the last foreign asset is removed.
- The user never sees the word "jurisdiction" or a "manage countries" page.

## 10. What the user must never see

- The word "jurisdiction."
- The phrase "add a country" or "select your country" (post-onboarding).
- A "South Africa" or "United Kingdom" sidebar header for a single-jurisdiction user.
- A `/za/...` URL prefix for a SA-only user (the URL is just `/savings`).
- An ISA, SIPP, IHT, or Annual Allowance reference for a SA-only user.
- A TFSA, RA, FAIS, or Estate Duty reference for a UK-only user.

## 11. Phase scope

| Phase | Goal | Includes | Excludes |
|---|---|---|---|
| **Phase 1 (now)** | Pure UK app + Pure SA app on shared codebase | Country-agnostic sidebar, pack-isolated frontend bundles, jurisdiction-gated routes, container-resolved backend, UK + SA module parity at sidebar level | Cross-border, dual-user UX, `<CountryView>`, `<Money>` localisation component, geo-registration |
| **Phase 2** | Dual-user UX | `<CountryView>`, `<Money>`, `<DateString>`, jurisdiction-focus toggle, pack-scoped vue-i18n, cross-border sidebar section | DTA, QROPS, residency, worldwide estate, FX |
| **Phase 3** | Cross-border tax layer | DTA classification, QROPS modelling, residency tracking, worldwide estate aggregation, FX rates, exchange control across jurisdictions | — |

This realignment closes the architectural drift required to deliver Phase 1 cleanly. Phase 2 and Phase 3 build on top of a Phase-1-compliant foundation.
