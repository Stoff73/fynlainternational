---
type: plan
date: 2026-05-05
session: 4
companion_spec: architecture-spec-realigned-2026-05-05.md
gap_analysis: architecture-gap-analysis-2026-05-05.md
chosen_option: C (Hybrid — UK refactor first, ZA migration second)
status: re-aligned, awaiting brainstorming/PRD decision
---

[[May Index]] | [[Home]]

# Architecture Plan — Re-aligned

This plan describes how the Fynla International codebase moves from its current drifted state (see `architecture-gap-analysis-2026-05-05.md`) to compliance with the re-affirmed spec (`architecture-spec-realigned-2026-05-05.md`). It assumes Option C — UK refactor first, ZA migration second — per user decision in session 4.

This is a Phase-1-scoped plan: pure UK and pure SA users, country-agnostic sidebar, pack-isolated frontend bundles. Dual-user UX (Phase 2) and cross-border (Phase 3) are out of scope and explicitly deferred.

---

## 0. Pre-flight

Before any code change:

- All cleanup PRs paused (DialogContainer, Tabs.vue, etc.) — confirmed.
- All new feature workstreams paused (WS 1.6b SA Estate, WS 1.7, WS 1.8) — confirmed.
- Working tree clean. Branch: main.
- Last shipped commit: `54862a9` (SARS acronym intro).

The realignment runs on its own branch (`feature/architecture-realignment-phase-1` or similar). Each workstream below ships as a separate commit on that branch. The branch merges to main when all workstreams are green.

---

## 1. Workstream sequence

Six workstreams, executed in order. Each has its own internal verification gate. Workstream N+1 starts only when workstream N is green.

| # | Workstream | Closes gaps | Effort |
|---|---|---|---|
| RA-1 | Sidebar — make UK fully data-driven | G1, G4, G5 | 2–3 days |
| RA-2 | Frontend route registration — gate UK on `gb` jurisdiction; conditional registration | G2, G6 | 2–3 days |
| RA-3 | UK frontend pack bundle — relocate UK views into `resources/js/packs/gb/`; lazy-load on auth | G3, G12 | 4–6 days |
| RA-4 | UK pack contracts — bind the 4 missing pack.gb.* contracts | G7 | 1–2 days |
| RA-5 | ZA URL realignment — drop `/za/*` prefix; ZA pack registers same paths as GB pack | (closes the `/za/*` UX artefact called out in Spec § 4.2) | 2–3 days |
| RA-6 | Full Playwright regression + smoke | verifies all of Phase 1 | 2 days |

**Total estimated effort:** 13–19 days. Calendar time depends on availability and review cadence.

---

## 2. RA-1 — Sidebar: make UK fully data-driven

### Goal

`SideMenu.vue` renders entirely from the `sidebarModules` Vuex getter. No hardcoded `<SideMenuItem>` declarations. UK and SA users see the same sidebar shape — same labels, same icons, same section groupings — with each item routing to the active pack's component.

### Tasks

1. Extend `MODULES_BY_JURISDICTION.gb` in `resources/js/store/modules/jurisdiction.js` from a list of strings to a list of `{key, label, route, icon, section}` objects matching the ZA shape.
   - Source the labels, icons, and routes from the existing inline `<SideMenuItem>` declarations in `SideMenu.vue` lines 53–98.
   - Group entries by `section`: `core` (Dashboard, Net Worth), `cashManagement` (Bank Accounts, Income, Expenditure), `finances` (Investments, Retirement, Property, Liabilities, Personal Valuables, Risk Profile, Business), `wealth` (Protection, Savings, Investment, Estate), `planning` (Holistic Plan, Plans, Journeys, What If, Goals, Life Events, Actions).
   - Dual-purpose entries (Bank Accounts as `cashManagement` AND `finances`) get one canonical entry; sidebar grouping handles display.
2. Refactor `MODULES_BY_JURISDICTION.za` to use the same canonical labels (drop "Savings (TFSA)" → "Savings", drop the `zaSection` group key — use the same section keys as GB).
3. Rewrite the `sidebarModules` getter to return the full configuration objects (not bare string keys) grouped by section.
4. Add a `sidebarSections` getter that returns sections in render order with their items.
5. Replace the hardcoded `<SideMenuSection>` + `<SideMenuItem>` blocks in `SideMenu.vue` lines 53–115 with a single `v-for` over `sidebarSections`.
6. Delete `hasZa` and `zaModules` computed/getters — superseded by `sidebarSections`.
7. Remove the `<SideMenuSection v-if="hasZa" label="South Africa">` wrapper entirely.

### Verification

- Pure UK test user (`john@example.com`): sidebar identical to current production output; no SA labels visible.
- Pure SA test user (`za-protection-test@example.com`): sidebar shows the same shape as UK user, but each item routes to its SA pack equivalent. No "South Africa" header. No UK ISA / SIPP / IHT references.
- Dual user (none seeded yet — temporarily seed for the test): sidebar shows the same shape; clicking Savings goes to whichever pack the user's primary jurisdiction owns.
- Architecture test: assert no hardcoded `<SideMenuItem>` outside core (Dashboard, Net Worth, etc. that genuinely belong to core).

### Risks

- Existing UK URLs (`/savings`, `/protection`, etc.) stay as-is in the GB pack's nav. No URL changes in this workstream — that's RA-5.
- Mobile sidebar (`MobileTabBar.vue`) may have its own hardcoded UK items. Audit and apply the same refactor in the same PR if shared.
- Routes that don't fit the standard layout (Goals quick-links to events, Life Events as a query-string fragment) need explicit configuration in the manifest — handle as edge cases inline.

---

## 3. RA-2 — Frontend route registration: gate UK on `gb` jurisdiction

### Goal

Routes for inactive packs are not registered. A UK-only user's router contains only core + GB routes. A SA-only user's router contains only core + ZA routes.

### Tasks

1. Restructure `resources/js/router/index.js`:
   - Move all core routes (Dashboard, Auth, Settings, Onboarding, Public pages) into a `coreRoutes` array.
   - Move all GB pack routes (`/savings`, `/protection`, `/retirement`, etc.) into a separate file `resources/js/packs/gb/routes.js` exporting `gbRoutes`.
   - ZA pack routes are already lazy-import-keyed; relocate the route definitions to `resources/js/packs/za/routes.js` exporting `zaRoutes`. (RA-5 will additionally rename their URLs.)
2. Replace the static route array with a router factory function:
   ```js
   export function buildRouter(activeJurisdictions) {
     const routes = [...coreRoutes];
     if (activeJurisdictions.includes('gb')) routes.push(...gbRoutes);
     if (activeJurisdictions.includes('za')) routes.push(...zaRoutes);
     return createRouter({ history, routes });
   }
   ```
3. Bootstrap order in `app.js`:
   - On mount, call `auth/fetchUser` BEFORE creating the router.
   - Read `active_jurisdictions` from the auth response.
   - Call `buildRouter(activeJurisdictions)` and mount the app.
   - For unauthenticated users, build with `activeJurisdictions = []` — only public/auth routes load.
4. Remove the `requiresJurisdiction` route meta — superseded by registration-gating.
5. Update the navigation guard to drop the `requiresJurisdiction` check (now redundant — un-registered routes are unreachable).

### Verification

- A SA-only user calling `router.push('/savings')` gets a 404 (no UK route registered).
- A UK-only user calling `router.push('/za/savings')` (legacy URL) gets a 404 (no ZA route registered).
- Browser network tab: SA-only user's session never fetches GB pack JS chunks.
- Pure UK user: every existing UK URL works exactly as before.
- Pure SA user: every existing `/za/*` URL works exactly as before. (RA-5 will rename them to unprefixed.)

### Risks

- Race condition between `auth/fetchUser` and router mount. Existing code (per WS 1.2b) already awaits user fetch on first load — preserve that pattern.
- Hot-reload during development. Vite HMR with a router factory needs verification.
- Deep links to URLs that the user lacks jurisdiction for (e.g. old bookmarks). Currently they get a soft redirect to dashboard via the guard; under the new model they get a 404. Decide: keep the soft redirect via a catch-all route in core, or accept the 404. Recommendation: catch-all redirect to dashboard with a one-time toast.

---

## 4. RA-3 — UK frontend pack bundle

### Goal

UK frontend code lives at `resources/js/packs/gb/` as a self-contained bundle. The bundle is dynamically imported after auth based on `gb` being in `active_jurisdictions`. A SA-only user's main JS bundle does not include UK pack code.

### Tasks

1. Create `resources/js/packs/gb/` with:
   - `index.js` — exports `{routes, navigation, store}` (the pack's public API).
   - `routes.js` — relocated from `router/index.js` (RA-2).
   - `navigation.js` — exports the GB pack's nav manifest (created in RA-1 as `MODULES_BY_JURISDICTION.gb`; relocate ownership).
   - `store/` — Vuex modules currently in `resources/js/store/modules/` that are UK-specific (savings, investment, retirement, protection, estate, goals, etc.). Audit each module to confirm it's UK-only before relocating.
   - `components/`, `views/`, `services/`, `mixins/`, `utils/`, `constants/` — relocated UK component / view / service code.
2. Update import paths across the moved code. Most imports are relative (`@/components/Savings/...`). After move they're `@/packs/gb/components/Savings/...`. Use a codemod or careful sed pass.
3. In the router factory (RA-2), change `import { gbRoutes } from '@/packs/gb/routes'` to a dynamic import:
   ```js
   if (activeJurisdictions.includes('gb')) {
     const gb = await import('@/packs/gb');
     routes.push(...gb.routes);
     store.registerModule('gb', gb.store);
   }
   ```
4. Move `Vuex.createStore` registration out of `store/index.js` for UK modules — register them via `store.registerModule` after pack import (so they're not in the core bundle).
5. Update `app.js` bootstrap to await pack imports before mounting Vue.
6. Verify `vite.config.js` chunk-splits the pack bundle correctly (each `import('@/packs/gb')` becomes its own chunk).

### Verification

- Pure SA user: browser network tab shows core + `packs-za-*.js` chunks loaded; no `packs-gb-*.js`.
- Pure UK user: opposite — only core + `packs-gb-*.js` chunks.
- Bundle size sanity check: SA-only user's total JS download is materially smaller than today (currently they receive everything).
- All existing UK Pest feature tests pass — backend wasn't touched.
- All existing UK Playwright journeys pass.

### Risks

- This is the largest workstream. Hundreds of files relocate. Imports break en masse mid-migration.
- Mitigation: do the move in stages — relocate one module at a time (Savings first, then Investment, etc.), verify each stage with a sub-build, commit each stage.
- Some "UK" code is actually shared (currencyMixin, design system primitives, layout components, Auth/Onboarding/Settings views). These stay in `resources/js/core/` (or `resources/js/`) — only genuine UK financial-module code moves into the pack.
- Audit gate: before relocating each file, confirm it has no SA call sites and no shared use. A file used by both UK and SA pages stays in core.

---

## 5. RA-4 — UK pack contracts: bind the 4 missing contracts

### Goal

`pack.gb.localisation`, `pack.gb.identity`, `pack.gb.banking`, `pack.gb.life_tables` are bound. Brings UK to feature parity with ZA at the contract level.

### Tasks

1. Create `app/Localisation/GbLocalisation.php` implementing `Fynla\Core\Contracts\Localisation`:
   - `formatMoney(int $minor): string` — `£1,234.56` formatting, GBP only.
   - `formatDate(\DateTimeInterface $d): string` — `DD/MM/YYYY`.
   - `formatNumber(...)`, `currencyCode(): 'GBP'`, `locale(): 'en-GB'`, etc.
   - Mirror the contract methods from `ZaLocalisation`.
2. Create `app/Validation/NationalInsuranceValidator.php` implementing `IdentityValidator`. Reference existing UK NI validation logic (likely in `app/Services/` or as a request-rule).
3. Create `app/Validation/GbBankingValidator.php` implementing `BankingValidator`. Sort code (6 digits) + 8-digit account number validation.
4. Create `app/Services/GbLifeTableProvider.php` implementing `LifeTableProvider`. Source ONS data (already seeded at `database/seeders/ActuarialLifeTablesSeeder.php`).
5. Bind all four in `GbPackServiceProvider::register()`.
6. Remove the "intentionally NOT bound" comment block at the top of `GbPackServiceProvider.php`.
7. Add 4 unit tests under `tests/Unit/Pack/Gb/` — one per binding — confirming each implements its contract and produces correct UK-formatted output.

### Verification

- Resolve each binding from the container in a Pest test; assert it returns the correct concrete class.
- Each binding's output validated against a known UK fixture (e.g. `£1,234.56`, `12-34-56` sort code, `AB123456C` NI number, ONS male-65 life expectancy).

### Risks

- Tax-config and other core values for UK already exist in `app/Services/`. The new contracts should wrap, not duplicate, this logic. Audit before re-implementing.

---

## 6. RA-5 — ZA URL realignment: drop the `/za/*` prefix

### Goal

ZA pack frontend routes register the same URLs as GB pack (`/savings`, `/investment`, `/retirement`, `/protection`, `/estate`, `/exchange-control`). For a SA-only user the URLs are unprefixed. The `/za/*` artefact disappears from the user-visible URL space.

### Tasks

1. In `resources/js/packs/za/routes.js` (relocated in RA-2), rename:
   - `/za/savings` → `/savings`
   - `/za/investments` → `/investments`
   - `/za/retirement` → `/retirement`
   - `/za/protection` → `/protection`
   - `/za/exchange-control` → `/exchange-control`
2. Update breadcrumbs to drop "South Africa — " prefixes.
3. ZA backend routes stay at `/api/za/*` (per Spec § 4.1). Frontend axios services that hit `/api/za/*` remain unchanged — only the UI URL changes.
4. Add legacy `/za/*` redirects to the new URLs (one-time, soft-redirect with toast) for any seeded user / external bookmark that points at the old paths. Six routes; each gets one entry in core's catch-all.
5. The router factory ensures only one pack's `/savings` route is registered for any given user (RA-2 already guarantees this for single-jurisdiction users — pure UK user gets GB's `/savings`, pure SA user gets ZA's `/savings`).

### Verification

- Pure SA user navigating the sidebar: every URL is unprefixed.
- Pure SA user manually entering `/za/savings`: redirected to `/savings` with a toast "URL updated".
- Pure UK user navigating: every URL is unprefixed (no change from today).
- Architecture test: no `/za/` literal in `resources/js/packs/za/routes.js`.
- No `/za` literal in user-visible breadcrumbs / titles / nav.

### Risks

- Dual users (Phase 2): both packs claim `/savings`. The router factory must register only one — by primary jurisdiction. The "currently-focused jurisdiction" toggle that lets dual users switch between UK and SA Savings is Phase 2 work; for Phase 1, dual users are not in scope.
- External documentation, deploy notes, and old vault docs reference `/za/*` URLs. Search-and-update those after the workstream lands.

---

## 7. RA-6 — Full Playwright regression + smoke

### Goal

Confirm Phase 1 is delivered: pure UK and pure SA users see structurally identical sidebars, navigate to country-correct pages, never see the other country's modules, and can complete every existing journey.

### Tasks

1. Pure UK regression — `chris@fynla.org` or `john@example.com`:
   - Login → Dashboard → Net Worth → each module sidebar item → submit a representative form per module (add bank account, add savings goal, add investment, add policy, add will). Verify no SA references appear.
   - Verify network tab does not download `packs-za-*.js`.
2. Pure SA regression — `za-protection-test@example.com`:
   - Login → Dashboard → Net Worth → each module sidebar item → submit a representative form per module (TFSA contribution, investment account, retirement fund, protection policy, beneficiary). Verify no UK references appear.
   - Verify URLs are unprefixed (`/savings`, not `/za/savings`).
   - Verify network tab does not download `packs-gb-*.js`.
   - Verify legacy `/za/savings` URL redirects to `/savings` with toast.
3. Dual-user smoke — temporarily seed a dual-jurisdiction user; verify the sidebar still renders without crashing and primary jurisdiction's pack is what links resolve to. Phase-1 expectation: dual user sees same sidebar shape; primary jurisdiction routing applies. Phase 2 will add the toggle UX.
4. Pest full suite: `./vendor/bin/pest` — green.
5. Architecture tests: any new test enforcing "no `/za/` literal in UI strings" passes.

### Risks

- The known SavingsAgentGoalsTest flake (passes in isolation, occasional fail in full suite) may surface again. Acceptable per existing CSJTODO Known Issues.
- Manual Playwright testing across two test users + 7 modules each = 14 journeys. Allocate a full day.

---

## 8. Out of scope for this plan

Deferred to Phase 2 / 3 per Spec § 11:

- `<CountryView>` resolver component (G8 — Phase 2)
- `<Money>` / `<DateString>` localisation components (G9 — Phase 2)
- vue-i18n pack-scoping (G10 — Phase 2)
- `JurisdictionDetectionObserver` wired to asset CRUD (G11 — Phase 2)
- Geo-registration verification (G13 — Phase 2)
- Cross-border layer / dual-user toggle (Phase 3)

These items are NOT blockers for Phase 1. They are blockers for Phase 2 (dual-user UX) and Phase 3 (cross-border).

Also explicitly deferred from CSJTODO:

- DialogContainer refactor (cleanup PR)
- Tabs.vue extraction (cleanup PR)
- WS 1.6b SA Estate Planning frontend (next major workstream — runs against the realigned architecture)
- WS 1.7 SA personas + onboarding
- WS 1.8 FAIS / POPIA copy

These resume after the realignment branch merges to main.

---

## 9. Decision points (awaiting user)

1. **Approve this plan as-is?** Or amend.
2. **Brainstorming:** the user noted a brainstorming-skill decision is pending. Given the spec and plan are now re-stated explicitly with all questions resolved, brainstorming may be redundant. Recommendation: skip brainstorming; proceed directly to `superpowers:writing-plans` to expand each workstream (RA-1 through RA-6) into TDD tasks. User's call.
3. **Branch strategy:** one long-lived `feature/architecture-realignment-phase-1` branch that hosts all 6 workstreams and merges atomically? Or six small PRs landing serially on main? Recommendation: single feature branch — the workstreams are interdependent and a half-merged state breaks the app.
4. **Test users:** RA-6 requires a dual-jurisdiction test user. Seed one as part of RA-1 or defer to RA-6 itself? Recommendation: seed in RA-1 (small change, useful for ongoing testing).

No code touched. Awaiting approval.
