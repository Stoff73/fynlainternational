# Fynla International — Full Application & Planning Audit

**Date:** 6 July 2026
**Branch:** `refactor/uk-pack-relocation` @ `a03ab72` (138 commits ahead of `main`, pushed, clean tree)
**Repo idle since:** 14 May 2026 (~7.5 weeks)
**Previous full tech-debt report:** 9 April 2026 (v0.9.4, 68 issues) — `docs/tech-debt-report-full.md`
**Method:** 6 parallel codebase scans (backend services, HTTP layer, models/database, Vue components, frontend state, tests) + plan-vs-reality gap analysis of all 8 `Plans/` docs + live app walkthrough (Playwright on localhost:8001) + full Pest run + dependency audits.

---

## 1. Executive Summary

The app **works** — it boots, auth + two-factor flow succeeds, preview personas render data-rich dashboards, Protection/Estate/Net Worth pages all render, and 2,974 of 2,975 tests pass. But the audit found one **live production-class bug** (Goals API 500), one **systemic architectural gap** (the UK pack relocation is structurally incomplete — a bidirectional core↔pack dependency cycle), one **operational time bomb** (the entire frontend still runs on a compatibility shim scheduled for deletion), and a **planning crisis** (the test gauntlet that gates the prod freeze lift is ~6 weeks behind with the freeze ending next week).

### Headline numbers

| Severity | Count (clusters) |
|----------|------------------|
| Critical | 15 |
| Warning | 45 |
| Suggestion | 26 |
| **Total** | **~86 clusters** (many represent dozens–hundreds of individual sites, e.g. 174 legacy URL calls, 102 dead components, 1,146 off-palette colors) |

| Top issues | Where |
|------------|-------|
| Live 500 on `/api/goals` — `GoalResource` references relocated class | `app/Http/Resources/GoalResource.php:102` |
| Frontend 100% on legacy API URLs (174 call-sites, 0 migrated) | `resources/js/services/*` via `LegacyApiRewrite` shim |
| Bidirectional core↔pack cycle (78 core files → pack; 76 pack files → core) | `app/Services/` ↔ `packs/country-gb/` |
| Running Pest **wipes the local dev database** | `phpunit.xml` (no test DB configured) |
| Test gauntlet ~2 of 8 weeks done; prod freeze lifts ~12 July | `Plans/test-gauntlet-plan-v1.md` |
| 102 dead Vue components (~1 in 7) never imported | `resources/js` + GB pack component trees |
| G-4-b security fixes (10 HIGH) still not deployed to dev — 5-session stall | `May/May13Updates/deploy-2026-05-13.md` |
| Dependency drift since May: composer 15 advisories (2 high), npm 23 vulns (2 critical, 15 high) | `composer.lock` / `package-lock.json` |

### Comparison with 9 April report
The April report counted 68 issues (12 critical) pre-relocation. Most April themes persist (god files, hardcoded tax fallbacks, test coverage gaps) but the landscape changed: the relocation eliminated duplicate-service copies and enforced strict_types to 100%, while **creating** the new dominant issue class — incomplete boundary migration (dangling references, the legacy URL shim, the allow-list ratchet at 69 entries).

---

## 2. Live App Check (what was actually exercised)

Performed in a real browser (Playwright) against `./dev.sh` on localhost:8001, plus curl probes and log inspection.

**Working, verified by interaction:**
- Landing page renders; cookie consent (decline → limited-functionality warning → accept) works
- Login `john@example.com` + email verification code (fetched from DB) → dashboard ✓
- Preview persona selector (`/?demo=true`) shows all 6 personas; entering "Emily & James Carter" renders a full preview dashboard (Net Worth £97,200, Protection £350,000, 4 savings accounts, recommendations) with "View as Emily" spouse toggle ✓
- Net Worth page renders completeness widget ✓; Protection page renders policy + shortfall analysis ✓; Estate page renders IHT summary with age-84 projections ✓
- Real API routes are auth-guarded: unauthenticated `/api/gb/protection` and `/api/dashboard` correctly 401 ✓

**Bugs found during the walkthrough:**

1. **CRITICAL — `/api/goals` returns 500 in preview mode (and for any user with a goal linked to a savings account).** Root cause: `app/Http/Resources/GoalResource.php:102` instantiates `SavingsAccountResource`, which was relocated to `packs/country-gb/src/Http/Resources/SavingsAccountResource.php` — the old `App\Http\Resources\SavingsAccountResource` no longer exists and only `App\Models\User` has a `class_alias` (`core/app/Core/Providers/CoreServiceProvider.php:108`). Stack trace confirmed in `storage/logs/laravel.log`. **Same file, second latent bug:** line ~95 instantiates `UserResource` (no import) — `UserResource` now lives at `core/app/Core/Http/Resources/UserResource.php`, so `joint_owner` rendering fatals whenever `jointOwner` is eager-loaded. Fix: `GoalResource`'s **only consumer is the pack's own `packs/country-gb/src/Http/Controllers/GoalsController.php`** — the clean fix is relocating `GoalResource` (+`GoalContributionResource`) into the pack beside its consumer, where `SavingsAccountResource` imports resolve naturally and no boundary is crossed. Add a Feature test for a goal with `linked_savings_account_id` set and one with an eager-loaded `jointOwner`. Effort: small.
   **✅ FIXED same session (2026-07-06):** both resources relocated to `packs/country-gb/src/Http/Resources/` (namespace `Fynla\Packs\Gb\Http\Resources`, explicit `Fynla\Core\Http\Resources\UserResource` import), `GoalsController` imports updated, `PackIsolationTest` allow-list ratcheted down 2 entries, and 2 regression tests added (`tests/Feature/Api/GoalsControllerTest.php`). Verified live: `/api/goals` returns 200 for a preview user with a linked-savings-account goal. Uncommitted.

2. **CRITICAL — the frontend never migrated to `/api/gb/*`.** 174 call-sites across `resources/js` use legacy `/api/{module}` paths; **zero** use `/api/gb/`. Every Protection/Estate/Savings/Investment/Retirement request travels through `LegacyApiRewrite` (`core/app/Core/Http/Middleware/LegacyApiRewrite.php`), whose own docblock says it "should be removed at the end of Phase 1 (60 days after Phase 0 cutover)" per ADR-004. Observed live during the walkthrough (`/api/protection`, `/api/estate`, `/api/estate/calculate-iht`, `/api/tax-year/current` all rewritten). The Pest suite also exercises legacy URLs through the shim. Removing the shim today would break the entire GB frontend. Fix: migrate the frontend service layer to `/api/gb/*` (mechanical, ~44 service files), update tests, then delete the shim; its logging already tells you every remaining caller. Effort: medium. Until done, R-14 "routing realignment" is only half true.

3. **WARNING — SPA catch-all swallows unknown `/api/*` paths with 200 + HTML.** `routes/web.php:17-19` (`Route::get('/{any}', …)->where('any', '.*')`) returns the SPA shell for any GET including non-existent API endpoints (verified: `/api/savings/accounts` → 200 HTML). This masks stale frontend calls as silent successes instead of loud 404s — particularly dangerous mid-relocation. Fix: exclude `api/` from the catch-all (`->where('any', '^(?!api).*')`) and let unknown API GETs 404 as JSON. Effort: trivial.

4. **WARNING — running the test suite destroys the local dev database.** `phpunit.xml:40-41` has the sqlite override commented out and no `.env.testing` exists, so `RefreshDatabase` ran `migrate:fresh` against `fynla_international` — mid-audit, all users vanished (verified: `User::count()` 15 → 0). Reseeding restores seeded data but **not** `john@example.com`'s financial data — `database/seeders/TestUsersSeeder.php` creates bare accounts only, so CLAUDE.md's "Test user with full data" claim is stale (data-rich testing requires preview personas or `chris@fynla.org`). Fix: point tests at a dedicated DB in `phpunit.xml` (`<env name="DB_DATABASE" value="fynla_international_test"/>`). Effort: trivial, high value.

5. **WARNING — `ProfileCompletenessAlert` never renders on the Protection dashboard.** `resources/js/views/Protection/ProtectionDashboard.vue:6-8` uses the component in its template but never imports/registers it and never defines `profileCompleteness`/`loadingCompleteness` (Vue console warnings confirmed live). The alert silently never shows. Fix: import + wire the data, or delete the template block. Effort: small.

6. **SUGGESTION — page titles don't update per route.** Title stayed "Sign In — Fynla" after landing on `/dashboard`, and module pages keep the generic landing title. Fix: set `document.title` in a router `afterEach` from route meta. Effort: small.

7. **SUGGESTION — `AppNavbar` missing `emits: ['toggleChat']`** — Vue warning on every page (`resources/js/` AppNavbar). Trivial.

8. **NOTE — local mail is misconfigured:** verification emails fail with `getaddrinfo for mailpit failed` (docker mailpit host not running/resolvable); codes must be fetched from the DB. Also CLAUDE.md's tinker snippet references `\App\Models\EmailVerificationCode`, which is now `\Fynla\Core\Models\EmailVerificationCode` — the documented command fails.

**Test suite result (full run, parallel):** 2,974 passed / 1 failed / 1 skipped (11,618 assertions, 986s). The failure is `MonteCarloSimulatorTest` `tests/Unit/Services/Investment/MonteCarloSimulatorTest.php:312` — a wall-clock `<10s` assertion that hit 32.6s while 7 audit agents were saturating the machine; it also failed serially under the same load. Treat as load-sensitive perf assertion (the known "parallel flake" family), re-run on a quiet machine before reading anything into it; long-term fix is removing wall-clock assertions or gating them (`->group('perf')`).

**Dependency audits (drift since the May G-4-a scan, which was composer-clean):**
- `composer audit`: **15 advisories / 8 packages** — HIGH: laravel/framework CRLF injection in default email rule (CVE-2026-48019), symfony/mime email header/SMTP command injection via CRLF (CVE-2026-45067); MEDIUM: guzzle cookie-domain + HTTPS-proxy-downgrade, psr7 CRLF ×3, Laravel signed-URL path confusion, symfony http-foundation/mailer/routing ×3, polyfill-intl-idn (low)
- `npm audit`: **23 vulnerabilities (2 critical, 15 high, 5 moderate, 1 low)**, e.g. `ws` memory disclosure/DoS. Most likely dev-chain (May's scan risk-accepted npm prod), but the two criticals need re-triage.
- Fix: `composer update` the framework/symfony/guzzle patch releases + `npm audit fix`, then re-run the G-4-a triage. Effort: small–medium.

---

## 3. Planning Folder Audit — Plan vs Reality

All 8 docs in `Plans/` read in full, cross-checked against the codebase, git history, `May/May12–14Updates/`, and `docs/`.

### 3.1 Architecture campaign (R-phases, `Plans/architecture-plan-v3.md`)

The plan's frontmatter says `status: closed (dev-green)`. The relocation **did substantially happen** — `app/Models/` is empty (53 models now in the GB pack, 44 in core), `app/Services/Estate|Tax` are gone, controllers/routes/migrations/seeders relocated, R-14a int-minor money closed 14/14, R-14b query layer closed 9/9. But:

| Gap | Detail |
|-----|--------|
| **R-15 acceptance gate unmet vs "closed" status** | Plan §17 requires the `PackIsolationTest` allow-list be **empty**; it has **69 `App\` entries** (`tests/Architecture/PackIsolationTest.php`). The campaign was closed on dev-green smoke, not its own written criterion. |
| **41 UK service files still in `app/Services/`** | Investment (19), Retirement (8), Goals (3), Plans (4), Coordination (3), Protection (3), Savings (1) — R-6/R-7 scope deferred into a "post-cutover R-17 batch" **for which no plan doc exists**. |
| **Bidirectional dependency cycle** (see §4.1) | 78 `app/Services` files import `Fynla\Packs\Gb\*`; 76 pack files import `App\Services\*` back. Core and pack cannot be built/tested/deployed independently — the architecture's core promise is not yet delivered. |
| **`BaseAgent` + `TaxOptimisationAgent` stranded in `app/Agents/`** | `app/Agents/BaseAgent.php:7-8` imports pack constants/traits; all 7 relocated GB agents extend it. Tracked as triage E-3 but E-3 only names 2 of the 69 allow-list entries. |
| **Frontend never migrated** (see §2.2) | R-14 realigned backend routes only; 174 frontend call-sites remain on legacy URLs behind the ADR-004 shim. |

### 3.2 Test Gauntlet (G-phases, `Plans/test-gauntlet-plan-v1.md`) — the active workstream, stalled

Done: G-(-1) lifecycle MVP, G-0-iv/v, G-1-a (2,836 baseline), G-1-b (59 observer tests), G-1-d persona surgery, G-4-a CVE scan, G-4-b slices 1–3 (10 HIGH + dozens of MEDIUM fixed, 60+ tests).
Blocked/not started: G-0-i/ii/iii (CSJ items: SiteGround cron, xAI dev key, Revolut sandbox webhook), G-1-c (needs CSJ persona sign-off), **G-2 entirely** (systems integration, ~1.5 wk), **G-3 entirely** (42 Playwright journeys, ~1.5 wk), G-4-b slice 4, G-4-c/d/e/f, **G-5 entirely** (7 hardening items), **G-6** (2-week user test — an irreducible calendar clock), **G-7** (go/no-go).

**Timeline reality:** the plan's week-6 checkpoint (22 June) — which mandates re-baseline if G-1..G-4 aren't green — passed 2 weeks ago with the repo idle. ~6 of 8 weeks of work remain; the prod freeze lifts **~12 July**. There is no scenario in which the gauntlet completes before the freeze lifts. **Recommendation: formally re-baseline per plan §10 (write gauntlet plan v2 or extend the freeze) as the first planning act of the next session.**

**Operational blocker, now 5 sessions old:** G-4-b slices 1–3 are committed/pushed but **never deployed to dev** (`May/May13Updates/deploy-2026-05-13.md` manifest ready; CSJ to pick: SiteGround File Manager / configure `ssh-csjones-dev` MCP / `git pull` on server). The security posture the gauntlet certifies exists only in git, not on the environment the gauntlet tests against. Everything downstream queues behind this decision.

### 3.3 Stale / superseded planning docs

| Doc | Verdict | Action |
|-----|---------|--------|
| `Plans/Implementation_Plan_v2.md` | Superseded (listed in v3 frontmatter `supersedes:`) — the abandoned hybrid | Archive |
| `Plans/phase_0_Implementation_Guide.md` | Superseded by Implementation_Plan_v2 (documents the failed 12-week Phase 0) | Archive |
| `Plans/multi_country_architecture.md` | Foundation re-affirmed by v3; §15–16 phasing stale | Keep; annotate stale sections |
| `Plans/SA_Research_and_Mapping.md` | Rules/rates authoritative; §3.2 fork assumption + §14 layout + §15 inventory obsolete (SA built as a pack) | Keep; flag obsolete sections |
| `Plans/architecture-{spec,plan}-v3.md` | Current-authoritative (closed, but see R-15 gap) | Keep |
| `Plans/test-gauntlet-{spec,plan}-v1.md` | Active, stalled | Keep; **re-baseline** |
| `docs/grok-migration-plan.md`, `docs/plans/*` (phase2a/2b, mobile) | Completed / pre-relocation era | Archive |
| Root `TODO.md`, `System Map.md`, `appMapping/`, `Home.md` | Stale wrt pack architecture (triage B-2 territory) | Housekeeping |
| **Missing docs the plans promise:** `Plans/test-gauntlet-coverage-matrix.md` (due G-2-a), `Plans/test-gauntlet-r14a-residuals.md` (due G-2-g), any R-17 residual-relocation plan | Referenced as companions but never created | Create when work resumes |

### 3.4 Triage backlog (`May/May12Updates/triage-backlog.md`)

All **E-1..E-23 remain open** (the 2026-05-14 handover's "16 active" undercounts the canonical doc). Substantive ones: **E-3** (R-14a residual bindings — tip of the 69-entry iceberg), **E-16** (MED: Trust currency fields missing `max:`), **E-22** (MED candidate: ReferralController throttle permits 14,400 invites/day — verification deferred to G-4-c). Open bugs: B-2 (dev-root cruft), **B-4 (`@capgo/capacitor-native-biometric` auth-bypass CVE, blocked on semver-major)**. Open CSJ questions: Q-2 (cron), Q-3 (Revolut webhook), Q-4 (triage tooling).

### 3.5 SA pack vs `SA_Research_and_Mapping.md`

Delivered as a real pack (contrary to the doc's fork assumption): Tax engine (11F/10C ledgers), Two-Pot + Reg 28, TFSA, Exchange Control ledger, Protection, Localisation/Identity/Banking, 44 Vue components, 12 migrations, best test coverage of any pack (19/24 classes).
**Gaps vs the doc's own v1-essential scope** (all consistent with the sanctioned "SA paused" decision, but unscheduled): **Estate is a 7 KB engine stub** — no models, tables (`estate_duty_projections`, `donations_register` absent), controller, or UI despite being declared v1-essential; Goals partial (defaults + severance calc only); Coordination absent; SA personas not seeded; FAIS/POPIA compliance items absent; `tax_compliance_statuses` table absent.

---

## 4. Codebase Findings by Area

### 4.1 Backend services (`app/Services`, `app/Agents`, `core/`, `packs/*/src`) — the big one

**CRITICAL — the boundary is crossed at scale, both directions:**
- **78 of 129 `app/Services` files** import `Fynla\Packs\Gb\*` directly (101 files across all of `app/`). Worst: `app/Services/Plans/InvestmentPlanService.php` (19 refs), `app/Services/AI/SystemPromptBuilder.php` (18), `app/Services/Coordination/HouseholdPlanningService.php` (15), `app/Services/NetWorth/NetWorthService.php` (9), `app/Services/Investment/Recommendation/UserContextBuilder.php` (9).
- **76 `packs/country-gb` files** import `App\Services\*`/`App\Agents\*` back. High-fanout core targets: `CacheInvalidationService` (10 pack consumers), `AssumptionsService` (6), `RiskPreferenceService` (6), `FeeAnalyzer` (6), `NetWorthService` (5).
- `app/Agents/BaseAgent.php:7-8` imports pack constants+traits while all 7 GB agents extend it — the agent base is neither core-clean nor pack-local.
- Fix directionally: per-service triage — jurisdiction-neutral → `core/`; UK-specific → pack. The stranded sets are already identifiable: `app/Services/Retirement/{RetirementStrategyService(2,142L),RetirementIncomeService(2,293L),PensionContributionOptimizer,DecumulationPlanner,SalarySacrificeAnalyzer,AnnualAllowanceChecker}`, `app/Services/Investment/Tax/{BedAndISACalculator,ISAAllowanceOptimizer,TaxOptimizationAnalyzer,DividendTaxCalculator}`, `app/Services/Investment/AssetLocation/AssetLocationOptimizer`, `app/Services/Protection/ProtectionActionDefinitionService` (2,349L — its 5 siblings all live in the pack), `app/Agents/TaxOptimisationAgent` (837L). Effort: large — this **is** the missing R-17 plan.

**WARNING — duplicate tax logic:** `determineTaxBand`/`calculateTaxBand` reimplemented **6×** with divergent thresholds (`app/Services/Investment/Recommendation/UserContextBuilder.php:362`, `app/Services/Coordination/HouseholdPlanningService.php:487`, `packs/country-gb/src/Savings/PSACalculator.php:79`, `…/Investment/PortfolioStrategyService.php:537`, `…/Tax/TaxOptimisationService.php:475`, `…/Tax/TaxActionDefinitionService.php:385`); marginal-rate lookup duplicated 4×; `calculateIncomeTaxRate` duplicated with one copy hardcoding rates (`packs/country-gb/src/Http/Controllers/Investment/AssetLocationController.php:288-292`). Centralise on the pack tax engine.

**WARNING — hardcoded tax-value fallbacks:** 27 files (11 core + 16 pack) carry `?? 12570`-style literals (`?? 50270`, `?? 125140`, `?? 20000`, `?? 60000`, `?? 37700`, `?? 0.40/0.45/0.20`) that silently drift each tax year — e.g. `app/Services/Investment/DividendTaxCalculator.php:34-38`, `packs/country-gb/src/Savings/SavingsActionDefinitionService.php:2198-2202`, `app/Services/Retirement/RetirementStrategyService.php:1227-1231` (hardcodes the whole rate ladder). Fix: make `TaxConfigService` throw on missing values; delete the fallbacks.

**WARNING — 25 god files >800 lines** (top: `packs/country-gb/src/Savings/SavingsActionDefinitionService.php` **3,688**, `…/Retirement/RetirementActionDefinitionService.php` **2,704**, `…/Agents/CoordinatingAgent.php` **2,638**, `app/Services/Protection/ProtectionActionDefinitionService.php` **2,349**, `…/Estate/IHTCalculationService.php` **1,643**). The `*ActionDefinitionService` classes are mostly static definition tables — prime candidates for data/config extraction.

**Minor:** stale "moved-from" docblocks (`packs/country-gb/src/Investment/Analytics/PortfolioStatisticsCalculator.php:11` etc.); empty `app/Traits/`, `app/Constants/` dirs left behind; `app/Services/CLAUDE.md` documents files that no longer live there; `app/Services/Lifecycle/LifecycleEngine.php` is a dead MVP stub (test-only consumer — matches triage E-1).

**Clean:** `core/app/` is exemplary (0 pack refs, 0 app refs, four contracts used correctly, no god files); `strict_types` 100% across everything; ZA pack well-isolated (own `ZaTaxConfigService`, no reverse imports); no literal duplicate service copies between core and pack.

### 4.2 HTTP layer (`app/Http`, `routes/`, pack routes)

**CRITICAL:**
- **ZA routes live in core:** `routes/api.php:515-613` hardcodes `\Fynla\Packs\Za\Http\Controllers\*` for ~30 endpoints — `packs/country-za/routes/` doesn't exist and ZA has no route-registering provider (GB does: `GbPackServiceProvider.php:126`). Two core mobile controllers also `use Fynla\Packs\Gb\Agents\*` (`app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php:7-12`, `InsightsController.php:7`). Fix: mirror the GB pattern for ZA; resolve GB agents via bindings. Effort: medium.
- **God controllers:** `packs/country-gb/src/Http/Controllers/InvestmentController.php` (1,070L), `app/Http/Controllers/Api/PaymentController.php` (995L), `app/Http/Controllers/Api/AuthController.php` (829L); `RetirementController`/`AdminController` at exactly 800.

**WARNING:**
- **100+ inline `$request->validate()` on security-sensitive write endpoints** despite 103 FormRequest classes existing — `AdminController.php:94-713` (8 sites), `GDPRController.php` (7), `MFAController.php` (5), `PaymentController.php` (6), `AuthController.php` (3). Promote the admin/GDPR/MFA/payment rules to Form Requests first.
- **`PreviewWriteInterceptor` EXCLUDED_ROUTES gaps:** `api/auth/mfa/verify`, `api/auth/mfa/recovery` (`routes/api.php:56-57`), `api/webhooks/revolut` (`routes/api.php:317`) absent from `app/Http/Middleware/PreviewWriteInterceptor.php:47-70`. Defense-in-depth today (interceptor needs a preview Bearer token to fire), but violates the documented rule. Trivial fix.
- Response-shape inconsistency: only 3 Resource classes in core vs 31 in packs; `AgentInternalController` mixes envelopes (`:141-147` vs `:216-224`); `JsonResponseHelper` used only by the exception handler.

**Suggestions:** legacy inert GDPR erasure methods kept-but-unrouted (`GDPRController` — delete or re-route); `AgentInternalController:127-166` pulls `module`/`parameters` with manual null checks only; 4 core route files missing `declare(strict_types=1)`.

**Clean:** all user-data endpoints behind `auth:sanctum`; May's payment-write + admin-write MFA gates correctly in place (`routes/api.php:301-307, 376-402`); no raw SQL/`DB::table` in controllers (only legit `DB::transaction`); no route collisions core↔pack; mobile v1 routes consistently validated.

### 4.3 Models & database

**WARNING:**
- **Orphaned duplicate migrations:** `core/database/migrations/2026_04_16_000001…000004` are byte-identical to `database/migrations/2026_04_16_200001…200004` and never loaded (`CoreServiceProvider.php:111-112` says core migrations moved). Delete the 4 dead files. Also `core/database/seeders/TaxYearSeeder.php` declares `Database\Seeders` namespace from an un-autoloaded path — dead. Trivial.
- **Auditable gaps:** all 7 ZA models lack `Auditable` (GB financial siblings have it — `ZaProtectionPolicy` vs `LifeInsurancePolicy`); `packs/country-gb/src/Models/SavingsGoal.php:21` missing it while `InvestmentGoal` has it.
- **`PropertyFactory` writes legacy columns** (`database/factories/PropertyFactory.php:42-46` populates `annual_*` fields not in `$fillable`, never sets the `monthly_*` fields the app reads) — factory-built properties have NULL running costs.
- **Broken doc commands:** `seedMigration.md:22-57` still uses short `--class=TaxConfigurationSeeder` forms that no longer resolve (must be FQCN `Fynla\Packs\Gb\Database\Seeders\…`); `database/CLAUDE.md` seeder/factory inventory stale.
- **Cross-pack enum mutation:** ZA migration `2026_04_20_000001_extend_pension_type_for_za_fund_types.php` widens GB-owned `dc_pensions.pension_type`; its `down()` reverts to the UK-only enum and would truncate ZA rows on rollback. Guard it.
- GB-coupled dev seeders (`ChrisUserSeeder`, `TestUsersSeeder`) import ~20 GB pack models from core namespace — relocate or gate.

**Suggestions:** 9 model scopes defined but never called (incl. `scopeOfType` across 8 models); schema dump 4.5 months stale (114 post-dump migrations run on every fresh install — re-run `schema:dump`).

**Clean:** enum canonicality perfect (no `sole` anywhere); `joint_owner_id` indexed on all 12 tables; single-record joint-ownership pattern respected; no duplicate migration basenames; no migration would fail a fresh install; sampled factories otherwise in sync.

### 4.4 Vue components (716 files: `resources/js` + pack trees)

**CRITICAL — 102 dead components/views never imported anywhere** (no import, no registration, no tag usage, no dynamic-import path — verified against `app.component()`, `import.meta.glob`, and `<component :is>` usage). Whole superseded generations sit in the tree: the old Dashboard card set (`AlertsPanel`, `NetWorthSummary`, `TaxOptimisationCard`, `GoalsOverviewCard`, …), the entire GB `Investment/PlanSections/*` set (superseded by `Plans/Shared/planPrintMixin.js`), 14 GB Estate components (`GiftingStrategy`, `IHTMitigationStrategies`, `NRBRNRBTracker`, …), 14 GB Investment components (`PortfolioOverview`, `MonteCarloResults`, `RebalancingCalculator`, …), 9 GB Goals components, GB Risk/Retirement/Protection/Savings stragglers, 5 mobile components, 2 ZA components, and one dead view (`resources/js/views/Investment/AccountPerformancePanel.vue`). Fix: delete per-module after a quick git-history check for anything newly added awaiting wiring. Effort: large but mechanical — this is the single biggest bulk-deletion win in the codebase (roughly 1 in 7 components is dead).

**WARNING:**
- **Off-palette default-Tailwind colors: 1,146 occurrences across 192 files** — blue (246), purple (236), green (155), teal (122), red (91), indigo (61), etc. Not the *banned* amber/orange set (that's clean) but outside the allowed raspberry/horizon/spring/violet/savannah/eggshell palette. Top offenders: `views/Version.vue` (101), `views/Public/CalculatorsPage.vue` (54), `components/UserProfile/LetterToSpouse.vue` (47), and the whole Risk module (risk-level coding in blue/purple/green/teal). Fix: token-mapping codemod (blue→horizon, green→spring, purple→violet, red→raspberry semantic).
- **~44 hardcoded hex values in `<style>` blocks across 10 files** — `LetterToSpouse.vue:1181-1426` (27, print document), `WillBuilderReviewStep.vue`, `LpaDetailView.vue` (print docs), `ModuleStatusBar.vue:239`, gradient backgrounds in `Login.vue:386`, `Register.vue:382`, `PlansDashboard.vue:161`, `FeaturesPage.vue:231`. Print documents are a defensible edge case; the rest should use `@apply`.
- **Scores rule violations:** one **live** — `views/Investment/AccountRebalancingPanel.vue:94` renders a bold "Drift Score" percentage (relabel "Allocation Drift"); three more (`RiskAnalysisSection` `{{ score }}/10`, `FeeAnalysisSection`, `TaxStrategySection` "Efficiency Score") are all in the dead-component list — delete with #1.
- Local `formatCurrencyCompact` shadowing the mixin it already imports: `packs/country-gb/resources/js/components/NetWorth/AssetBreakdownBar.vue:141` (the only currency-format bypass in 716 files).

**SUGGESTION:** 7 duplicate component basenames naming genuinely different components (`AccountSummaryPanel` ×2, `CurrentSituation` ×2, `WhatIfScenarios` ×3, `Settings` ×2, …) — rename per module for grep-ability; bare acronyms on a few public education pages (`InheritanceTaxExplainedPage.vue:173,225` "IHT", `PensionAnnualAllowancePage.vue:101-109` "MPAA") — in-app UI is compliant.

**Clean:** banned color tokens (amber/orange/primary/secondary/gray-N): **zero**; no duplicate `@keyframes`/scrollbar/spinner CSS; zero `v-if`+`v-for` collisions; zero missing `:key` (769 v-for sites scanned); form modals all emit `save`; `currencyMixin` discipline near-perfect.

### 4.5 Frontend state layer (stores, services, utils, router, constants)

**CRITICAL:**
- `resources/js/views/Trusts/TrustsDashboard.vue:192,429` — the only component bypassing the service layer: raw `$http.post('/api/estate/trusts/…/calculate-iht-impact')`, duplicating the (orphaned) `trusts/calculateTrustIHTImpact` store action. Route through the store; delete the axios import.
- `resources/js/store/utils/crudActionFactory.js` — entirely dead (no module uses it; every CRUD module hand-rolls). Delete or adopt.

**WARNING:**
- **66 orphaned Vuex members across 18 modules** (never dispatched/committed/mapped): biggest clusters `chattels` (9), `aiFormFill` (8), `onboarding` (6), `trusts` (5), `journeys` (5), `businessInterests` (5), plus ZA clusters (`zaRetirement`, `zaProtection`, `zaExchangeControl`, `zaInvestment`, `zaSavings`) suggesting half-built features.
- **`utils/ownership.js` is dead** — zero importers; CLAUDE.md still documents it as the canonical frontend ownership util. Either wire it in (preferable — components define ownership logic locally) or delete + fix CLAUDE.md.
- **`constants/taxConfig.js` drift risk:** 39 components import `IHT_NIL_RATE_BAND`, `ISA_ANNUAL_ALLOWANCE` etc. as primary values (despite the file's own "fallback-only" header), duplicating backend `TaxConfigService` — will diverge at the next tax-year change. 3 files still use the `@deprecated TAX_CONFIG` export.

**Suggestions:** 30 components reimplement `isPreviewMode` locally instead of using `previewModeMixin` (contradicts CLAUDE.md); 4 unused `designSystem.js` exports (`INFO_COLORS`, `getColorByThreshold`, `getValueColor`, `BORDER_RADIUS`); hardcoded invoice-download URL in `SubscriptionManagement.vue:331`.

**Clean:** router fully sound (all lazy imports resolve, meta flags consistent, guards enforced, no duplicate paths); no dead API services; no local `formatCurrency` reimplementations — `currencyMixin` used consistently; utils otherwise heavily used.

### 4.6 Test suite

**CRITICAL:**
- **Stale mocks of 4 deleted classes:** `tests/Feature/Api/RecommendationsControllerTest.php:7-11,31,44` mocks `App\Services\Estate\EstateAnalyzer`, `…\Protection\ProtectionAgent`, `…\Retirement\RetirementProjector`, `…\Savings\EmergencyFundAnalyzer` — none exist post-relocation; Mockery silently defines the names and binds instances nothing resolves, so nothing is actually stubbed. False confidence.
- **"No DB facade in controllers" arch rule lost its targets:** `tests/Architecture/ApplicationArchitectureTest.php:59-78` scopes only `App\Http\Controllers`; 5 relocated pack controllers now use `DB::` outside the rule. Add parallel rules for `Fynla\Packs\{Gb,Za}\Http\Controllers`.

**WARNING:**
- **Time is the flake surface:** 354 raw `now()`/`Carbon::now()` usages vs only 4 files freezing time. Confirmed time bombs: `tests/Unit/Services/Savings/ISATrackerTest.php:41-127` (hardcodes tax year 2024/25 while service derives 2026/27 from the clock — now silently exercising the wrong branch) and `tests/Unit/Core/TaxYear/TaxYearResolverDbTest.php:48-51` (asserts `'2026/27'` from live clock — breaks after 5 April 2027). This family, plus wall-clock perf assertions (Monte Carlo), is almost certainly the untriaged parallel-flake backlog item. Fix: freeze time in date-boundary tests; consider global `Carbon::setTestNow` in `Pest.php`.
- **strict_types arch rule doesn't cover pack services/controllers/models** (currently compliant, unenforced); **no pack-local Architecture suite is wired into `phpunit.xml`** — `packs/_template/tests/Architecture/PackIsolationTest.php` never executes and GB's pack test dirs are `.gitkeep` placeholders.
- **Coverage gaps (money-critical first):** `app/Services/Payment/{InvoiceService,SubscriptionRenewalService,TrialService,RevolutSubscriptionService,DataPurgeService}` — zero test references; GB Investment advanced-analytics module — no Feature test hits `/api/investment/{portfolio-strategy,rebalancing,tax-optimization,asset-location,…}` and the pure-math backing services (`MarkowitzOptimizer`, `EfficientFrontierCalculator`, matrix/statistics classes) have no unit tests; **`UKTaxCalculator` has no dedicated test** (PA-taper/NI band edges untested); GB Estate/IHT sub-services untested at unit level (`IHTCalculationService` et al. — only indirect Feature coverage); GB Goals services (9 classes) untested; `InvestmentAgent` + `CoordinatingAgent` (the orchestrator) untested; 11 core controllers with no Feature test (incl. `AiChatController`, `PasswordResetController`, `ReferralController`); AI layer (`XaiClient`, `SystemPromptBuilder`, compliance prompts) untested — notable for an FCA-adjacent product.
- Tautological assertions accepting both outcomes: `tests/Feature/PortfolioOptimizationTest.php:171-172` (`toBeIn([200,400])`), `RetirementIntegrationTest.php:501-528`, `Estate/LpaControllerTest.php:53` (`[200,401]` hides auth ambiguity).

**Clean:** DB isolation via `RefreshDatabase` correctly mapped suite-wide; zero Mockery::close omissions (42 files); zero randomness; zero unconditional skips; core-independence and pack-isolation arch tests genuinely run and enforce; no-float-money/no-hardcoded-legal-copy tests exist; ZA the best-covered pack (19/24).

---

## 5. Cross-Cutting Themes

1. **The relocation is 90% moved, 60% finished.** Files moved; the dependency graph didn't. The R-15 gate (empty allow-list), the frontend URL migration, the stranded 41 services, GoalResource-style dangling references, stale mocks, arch-rule scoping, and doc drift are all the same debt: the campaign was declared closed before the boundary was actually sealed. **The missing "R-17" plan is the single most important planning artifact to write next.**
2. **No static analysis.** No PHPStan/Larastan/Psalm installed. The GoalResource 500 and the stale test mocks are exactly level-0 undefined-class findings. Installing Larastan at level 0–2 and wiring it into CI would have caught both classes of bug mechanically. Strong recommendation.
3. **Documentation lags the refactor:** root CLAUDE.md (tinker snippet, john@example.com claim, localhost:8000 vs auto-selected 8001), `app/Services/CLAUDE.md`, `database/CLAUDE.md`, `seedMigration.md`, `utils/ownership.js` reference — all stale in ways that actively mislead (two bit this audit live).
4. **Tax values still leak outside TaxConfigService** — backend `?? literal` fallbacks (27 files) and frontend `taxConfig.js` primary-value imports (39 components). One canonical source, both layers.
5. **Repo cruft:** git-tracked stray `Users/Chris/Desktop/fpsApp/fynla/2025_12_19_144610_add_settlor_to_trusts_table.php` (accidental add; the real settlor schema exists via proper migrations — delete the whole `Users/` tree), empty relocated dirs, orphaned core migrations, dead seeder, `appMapping/` + root maps stale (triage B-2).

---

## 6. Recommended Action Plan

### Immediate (this week — before/at freeze-lift decision)
1. **Decide the dev-deploy path and ship G-4-b slices 1–3 to dev** (manifest ready: `May/May13Updates/deploy-2026-05-13.md`). 5-session blocker; everything queues behind it. *(CSJ decision)*
2. **Re-baseline the gauntlet** — write `test-gauntlet-plan-v2` or formally extend the prod freeze (plan §10 mandates this; the week-6 trigger passed 22 June). *(30–60 min planning)*
3. **Fix the `/api/goals` 500** — repair both dangling references in `app/Http/Resources/GoalResource.php` + add a Feature test for linked-savings-account and joint-owner goals. *(small)*
4. **Give tests their own database** — `phpunit.xml` `DB_DATABASE=fynla_international_test`. *(trivial; prevents data loss)*
5. **Exclude `api/` from the SPA catch-all** (`routes/web.php:17`). *(trivial)*
6. **`composer update` + `npm audit fix`** for the 2 HIGH composer advisories (Laravel email CRLF, symfony/mime SMTP injection) and 2 critical npm vulns; re-run G-4-a triage. *(small)*
7. Fix the stale mocks in `RecommendationsControllerTest` — currently testing nothing. *(small)*

### Short-term (rest of July)
8. **Write the R-17 plan** (relocate the 41 stranded services + BaseAgent/TaxOptimisationAgent, break the 78↔76 cycle, drive the allow-list toward 0) — spec → plan → PRD per workflow.
9. **Migrate the frontend to `/api/gb/*`** (174 call-sites, mechanical), update tests, then delete `LegacyApiRewrite`. Use the shim's logs to verify zero remaining callers first.
10. **Install Larastan (level 0 → 2)** and add to CI; it enforces the relocation from now on.
11. Move ZA routes into the pack (mirror GB's provider pattern); resolve GB agents in mobile controllers via bindings.
12. Patch the arch-test blind spots: DB-facade + strict_types rules for pack namespaces; wire pack test dirs into `phpunit.xml` or delete the placeholders.
13. Freeze time in the confirmed time-bomb tests (`ISATrackerTest`, `TaxYearResolverDbTest`) and triage the parallel-flake list with the time-freeze lens.
14. Add `PreviewWriteInterceptor` exclusions (mfa/verify, mfa/recovery, webhooks/revolut).
15. Add unit tests for the money paths: `UKTaxCalculator` boundaries, Payment renewal/trial/invoice services.
16. Update the four stale docs (CLAUDE.md ×2, database/CLAUDE.md, seedMigration.md) + delete repo cruft (`Users/` tree, orphaned core migrations, dead `TaxYearSeeder`, empty dirs).

### Backlog
17. Centralise `determineTaxBand`/marginal-rate on the pack tax engine (6 divergent copies); purge `?? literal` tax fallbacks (27 files); route frontend tax values through the store/API (39 components).
18. Split the >2,000-line `*ActionDefinitionService` god files into data providers.
19. Promote inline validation to Form Requests (admin/GDPR/MFA/payment first).
20. Frontend hygiene: **delete the 102 dead components per-module** (biggest bulk win), run the off-palette→token codemod (1,146 sites/192 files), relabel the live "Drift Score" (`AccountRebalancingPanel.vue:94`), delete `crudActionFactory.js` + `ownership.js` (or adopt), prune 66 orphaned store members, migrate 30 components to `previewModeMixin`, fix `ProfileCompletenessAlert` wiring, `AppNavbar` emits, per-route page titles, rename the 7 duplicate component basenames.
21. Auditable trait for ZA models + `SavingsGoal`; fix `PropertyFactory` legacy columns; guard the ZA `down()` enum reversion; re-run `schema:dump`.
22. SA pack: when unpaused, Estate is the declared-v1-essential gap (stub only); then Goals/Coordination/personas/FAIS-POPIA.
23. Coverage build-out per §4.6 (Investment analytics math, IHT unit tests, GB Goals, AI layer, 11 untested controllers); fix tautological assertions.
24. Consider adopting `JsonResponseHelper`/Resources consistently in core controllers.

---

## 7. What's Healthy (credit where due)

- `core/app/` is a model citizen — the four-contract isolation pattern works exactly as designed where it's used.
- 100% `declare(strict_types=1)` coverage across every PHP tree.
- 2,974/2,975 tests green on a 7.5-week-idle branch; DB isolation, Mockery hygiene, and determinism (no randomness) are all clean.
- Route/auth surface is solid: every user-data endpoint authenticated, May's MFA gates for payment/admin writes in place, webhooks HMAC-verified.
- Enum canonicality, joint-ownership single-record pattern, and index coverage are fully compliant.
- The ZA pack is the best-behaved codebase area: isolated, tested, no reverse imports.
- Router, API service layer, and currency-mixin discipline on the frontend are near-spotless.
- The app genuinely runs end-to-end: real login + 2FA, preview personas, module dashboards with correct-looking financial figures.

---
*Generated by the tech-debt-full audit, 6 July 2026. Companion conversation covers the live walkthrough evidence. Detailed agent transcripts available on request.*
