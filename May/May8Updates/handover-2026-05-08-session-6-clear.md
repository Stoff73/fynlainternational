---
type: handover
mode: context-clear
date: 2026-05-08
session: 6
branch: refactor/uk-pack-relocation
previous_session: 2026-05-08-session-5-clear
---

# Context Clear Handover — 2026-05-08, Session 6

## Immediate state

R-12, R-13a, and R-13b all SHIPPED — 11 commits pushed. Branch tip `5f7ef63`, **61 commits ahead of `main`**, working tree clean. Pest **2,823 passing** / 1 skipped / 0 failing. Architecture **129 passing**.

## The thread

- Session opened from `handover-2026-05-08-session-5-clear.md` recommending R-12 per-pack `navigation()` providers (~2 hr) per `architecture-plan-v3.md` § 14.
- **R-12 done first** (1 commit, `a3c7dac`). Replaced the in-core `MODULES_BY_JURISDICTION` constant with per-pack `navigation()` thunks. Manifest shape preserves today's behaviour (UK = flat module-name list for the `sidebarModules` getter; ZA = config-object array for the `zaModules` getter consumed by `<SideMenuItem v-for>`). Plan § 14's richer `rootItems` + `sections` shape was deliberately deferred — converting the UK sidebar from hardcoded template to data-driven rendering is R-13a scope (~7 hr), not R-12 (~2 hr). Step 5 of the plan ("no template change to SideMenu.vue") was the load-bearing constraint that resolved the apparent contradiction in § 14's example. Vite aliases `@gb` → `packs/country-gb/resources/js` and `@za` → `packs/country-za/resources/js` added in the same commit (R-13a needs them too). New architecture test `tests/Architecture/PackNavigationTest.php` (3 it-blocks): forbids `MODULES_BY_JURISDICTION` in any `.js`/`.vue`/`.ts` under `resources/js/` or pack js dirs, and asserts both pack manifests exist + export default `navigation`. Pest 2,820 → 2,823 (+3 = the new arch tests); Architecture 126 → 129. Browser-verified `young_family` UK persona — sidebar identical to pre-R-12 (Dashboard, Net Worth, 3-item Cash Management, 7-item Finances, 6-item Family, 7-item Planning, no SA section).
- **R-13a UK frontend** done in 9 module-by-module commits, 288 .vue files relocated:
  - WhatIf 3 (`aa9204a`) → Risk 6 (`7852ac2`) → Protection 12 (`dedff37`) → Savings 13 (`6ad82af`) → Goals 25 (`0ba26c3`) → Retirement 22 (`238d36a`) → Investment 60 (`f87541a`) → Estate+Trusts 63 (`9e2ce03`) → Plans+NetWorth 84 (`33a18ec`).
  - Pattern crystallised on the second commit (Risk) and held cleanly for the remaining 7: `git mv` the directory wholesale → batch-rewrite `'@/components/X/'` → `'@gb/components/X/'` across all consumer + internal-cross-import sites in one Python pass → browser-verify the route → commit. Smallest module first (WhatIf 3 files) was the proof-of-concept; largest (Plans+NetWorth 84 files, with heavy cross-imports across Plans/Holistic ↔ Plans/{Estate,Investment,Retirement,Protection,Goals}/Shared) was handled by a single Python rewrite that touched 34 files in one pass.
  - Browser-verified routes (zero console errors throughout): `/dashboard`, `/risk-profile`, `/protection`, `/net-worth/cash`, `/goals`, `/net-worth/retirement`, `/net-worth/investments`, `/estate`, `/trusts`, `/plans`, `/net-worth/wealth-summary`, `/planning/what-if`. Three preview personas exercised: `young_family` (UK family), `peak_earners` (UK Mitchells), `retired_couple` (UK Bennetts — the estate-planning persona).
  - **One non-`.vue` file initially missed**: `resources/js/components/Plans/Shared/planPrintMixin.js` got skipped by the `*.vue` glob in the move loop. Caught by the post-move count discrepancy (51 vs expected 52). Moved separately, ended up at `packs/country-gb/resources/js/components/Plans/Shared/planPrintMixin.js`. Worth flagging because future module relocations may have similar non-`.vue` straggler files (mixins, JSON manifests, etc.) — use `find -type f` not `*.vue` for completeness.
  - **Vite dev server crashed once mid-session** under rapid file-move HMR pressure (after the Estate+Trusts move). Pre-existing PID 56992 died, came back at PID 15919 within ~2s. Manifested as three `ERR_CONNECTION_REFUSED` console errors on the next `/estate` navigation, then cleared on the retry. No data loss; `./dev.sh` orchestrator restarted Vite automatically.
- **R-13b SA frontend** done in 1 commit (`5f7ef63`), 44 .vue files. Dropped the redundant `ZA/` subdir prefix during the move — files now live under `packs/country-za/resources/js/components/{Savings,Investment,Retirement,ExchangeControl,Protection}/` (component filenames already carry `Za*` disambiguation). 5 consumer views in `views/ZA/` updated (`'@/components/ZA/'` → `'@za/components/'`). No internal cross-imports. Browser test was limited because **no SA preview personas are seeded** — `/za/savings` returned the route guard's "Page Not Found" for the UK preview persona, but with zero console errors, proving the SPA bundle loaded with the new `@za` imports intact (any broken import would have crashed the entire app). End-to-end SA verification is gated on R-13a-style ZA preview personas (separate workstream — not in current plan budget).
- **Vault sync (Haiku 4.5 subagent, vault root `/Users/CSJ/Desktop/fynlaInter/FynlaInter/`)** ran cleanly: Git History `May08.md` extended (5 commits → 16; 11 session-6 commits added in tabular form), `May2026 Commits.md` totals updated (67 → 78; refactor type now 45; branch tip → `5f7ef63`; ahead-of-main → 61), `May Index.md` session 6 entry added with full R-12/R-13a/R-13b narrative, `Home.md` Git History table row updated and session 6 entry inserted with the architecture progress text. 0 broken wikilinks, 0 orphans, 5 May8Updates frontmatter files all compliant. Memory dir audit: 6 files, all current, 0 stale, 0 contradictions, 0 new suggestions.

## Files touched (all committed + pushed)

- **R-12 (`a3c7dac`):** 8 files — 2 new pack manifests (`packs/country-gb/resources/js/navigation.js`, `packs/country-za/resources/js/navigation.js`), 1 new arch test (`tests/Architecture/PackNavigationTest.php`), `vite.config.js` (+`@gb`/`@za` aliases), `resources/js/store/modules/jurisdiction.js` (rewrite), `resources/js/components/SideMenu.vue` (stale-comment update only — template unchanged), 2 new `.gitkeep` for ZA pack resources tree.
- **R-13a × 9:** 288 `.vue` + 1 `.js` (planPrintMixin) relocated. ~30 consumer files rewritten across `views/`, `components/Onboarding/`, `components/Dashboard/`, `components/UserProfile/`, `router/index.js`, and 1 in-pack consumer (`packs/country-gb/.../Retirement/DCPensionForm.vue`).
- **R-13b (`5f7ef63`):** 44 `.vue` relocated, 5 consumer files (`views/ZA/Za*Dashboard.vue`) rewritten.

Working tree at handover write-time: clean except for this handover file + CSJTODO update, which Phase 10 of session-end will commit + push as one final docs commit.

## What the next Claude needs to know

1. **Branch state:** `refactor/uk-pack-relocation` at `5f7ef63` (after Phase 10 will be `5f7ef63` + 1 docs commit), **61 → 62 commits ahead of `main`**, all pushed. Pest **2,823 passing** / 1 skipped / 0 failing. Architecture **129 passing**.
2. **R-13a + R-13b are CLOSED.** Next workstream is **R-14 routing realignment (~3 hr)** per `architecture-plan-v3.md` § 16. Different surface than R-13 — backend PHP routes + middleware, not Vue. Work consists of: (a) UK routes mount at `/api/gb/*` (pack already mounts under `/api/*`; need to add the `/gb` prefix and a 301 redirect layer for legacy `/api/protection`-style URLs), (b) SA routes already at `/api/za/*` (no change), (c) frontend SA pack registers same URL paths as UK so SA-only users hit unprefixed `/savings` etc., (d) legacy `/za/*` URLs get a one-time soft-redirect in core's catch-all, (e) arch test asserting no `/za/` literal in `packs/country-za/resources/js/routes.js`. **301 strips POST bodies in some clients — use 308 (Permanent Redirect) for non-GET methods.**
3. **Don't touch the deferred surfaces during R-14.** The 6 core models (User/Household/Goal/GoalContribution/LifeEvent/LifeEventAllocation) and the 12 float-money services are gated behind R-14b and R-14a respectively — both still ahead of R-14. Don't pre-empt either.
4. **R-14a deferral list still ~59 entries.** R-12 + R-13a + R-13b added zero new entries (pure file relocations don't change service signatures). Architecture suite stayed at 129 throughout — the +3 from R-12 is all that moved.
5. **Vite alias contract:** `@gb` → `packs/country-gb/resources/js`, `@za` → `packs/country-za/resources/js`. Use these in any pack-internal or pack-consumer imports. Don't re-introduce `@/components/{Investment,Estate,Plans,...}/` paths — those resolve to the now-empty core `resources/js/components/` dirs and will silently 404 in Vite.
6. **Vault path is `/Users/CSJ/Desktop/fynlaInter/FynlaInter/`, NOT `fynlaBrain/`.** Restated for emphasis — the session-end skill text still references `fynlaBrain` but the dispatch prompt to the Haiku subagent overrides the path explicitly. `fynlaBrain` remains historical.
7. **CLAUDE.md metric drift continues, do not auto-fix.** Vault sync's Phase 1 reports: Vue Components 713 → 382 (drift -331), PHP Services 240 → 140, Controllers 99 → 51, Models 94 → 6, Agents 9 → 2. Vuex Stores still 39, factories still 61, migrations still 112 (loaded from pack via `loadMigrationsFrom`). The re-baseline of CLAUDE.md happens at R-15.
8. **One straggler-file lesson for future module moves:** non-`.vue` files inside a module dir (mixins, JSON, helpers) won't be caught by a `*.vue` glob. Use `find <dir> -type f` to enumerate everything, or grep the post-move counts against expected.
9. **Vite restart is harmless but disruptive.** If you see three `ERR_CONNECTION_REFUSED` errors on `localhost:5173` after a heavy file move, just retry the navigation — `./dev.sh` will have restarted Vite within seconds. Don't panic-rebuild.
10. **No SA preview personas exist.** R-13b's browser test was limited to "SPA bundle loads + route guard returns 404 cleanly". A full SA journey verification needs a SA-active preview persona — currently a separate workstream not in the v3 plan budget. Won't block R-14, but flag if R-15 regression discovers SA-specific breakage that browser testing should have caught.

## Pick up from here

**Recommendation: R-14 routing realignment (~3 hr).** Approach:

1. Open `Plans/architecture-plan-v3.md` § 16 for the R-14 spec.
2. **Backend tasks:**
   - Add `/api/gb` prefix to the GB pack's route group in `packs/country-gb/src/Providers/GbPackServiceProvider.php` `boot()` (currently mounts under `/api/*`).
   - Create a new core middleware `App\Http\Middleware\LegacyUkApiRedirect` that catches unprefixed `/api/protection/*`, `/api/savings/*`, etc. and 301-redirects to `/api/gb/protection/*` etc. **Use 308 for non-GET methods** (the 301 → POST body loss is a real footgun in some HTTP clients including some mobile fetch implementations).
   - Register the middleware before pack middleware in `app/Http/Kernel.php` `$middlewareGroups['api']`.
3. **Frontend tasks:**
   - SA pack routes (when they exist — currently the SA pack has no `routes.js` since R-13b only relocated components) should register unprefixed paths (`/savings`, `/protection`) — for SA-only users these are unprefixed since pack scoping handles isolation.
   - Add a one-time soft-redirect in core's catch-all for legacy `/za/*` URLs:
     ```js
     { path: '/za/:rest*', redirect: to => ({ path: '/' + to.params.rest, query: to.query }) }
     ```
4. **Architecture test:** `arch('no /za/ literal in pack ZA routes')` — assert that `packs/country-za/resources/js/routes.js` (when created) doesn't contain a `/za/` URL prefix.
5. **Verification:**
   - UK clients on the old `/api/protection` URLs get a 301 (or 308 for POST) → 200 — transparent.
   - Mobile app (which relies on follow-redirect default in fetch/axios) continues to work on old URLs.
   - SA pack routes are unprefixed for the SA-only user.
6. Browser-test: Existing UK routes (`/api/protection`, `/api/savings`) still work via the redirect layer; new `/api/gb/protection` works directly. Mobile dashboard endpoint check.
7. Commit + push as `refactor(uk-pack): R-14 routing realignment — /api/gb prefix + legacy 301/308 redirects`.

**DO NOT** introduce a `/api/za/` URL change for existing SA routes — they're already namespaced. R-14 is about the UK side getting its prefix; SA already has one.

**Alternative if R-14 feels entangled with deployment concerns** (e.g. mobile app caching old URL paths): jump to **R-14a (~6 hr — provisional, re-scope at kickoff)** — ADR-005 int-minor money refactor for the ~12 services + 2 traits in the R-14a allow-list. R-14a is bigger but purely backend domain work; R-14 touches HTTP routing + redirects + frontend router. Pick R-14 first if you want to keep architectural symmetry advancing; pick R-14a first if mobile app deploy concerns make R-14's redirects feel risky.

**DO NOT** introduce compatibility shims, aliases, or fallback layers (other than the explicit 301/308 redirect layer R-14 mandates, which is a one-shot legacy bridge — not a permanent compat surface). The whole v3 plan is "direct relocation, no compat aliases" — the antipattern that broke the April attempt. Don't reintroduce it.
