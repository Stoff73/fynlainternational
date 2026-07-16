---
type: reconciliation
date: 2026-07-15
branch: chore/laravel-12-upgrade (== main @ 7081429)
method: 7 parallel read-only audit agents + deployed-server probe + legacy-repo divergence check
scope: spec/plan vs implementation (incl. iterations), blindspots, unknown-unknowns, beyond-worktree
---

# Fynla International — Full Reconciliation (2026-07-15)

## The one-paragraph verdict

The **backend pack-relocation refactor genuinely succeeded** — the GB pack is `App\`-free, core holds zero `Fynla\Packs\` literals, the container query layer is real, and the Architecture test suite is green today with **137 assertions, 0 skips** (re-run and verified this session). But **almost everything that makes the app actually "international" for a real user is either cosmetic scaffolding or unbuilt**: a genuine South-African user cannot register into the SA experience, cannot pay, and would land on a £0 UK dashboard with a half-UK sidebar. Separately, **nothing has been deployed anywhere since 12 May** — the dev server still runs Laravel 10 while the branch is on Laravel 12, web builds are double-blocked, and ~200 commits (including 10 HIGH security fixes) exist only in git. And the thing nobody is tracking: **production fynla.org runs a *different, still-actively-developed* codebase** (the legacy repo), so the eventual cutover is an un-rehearsed big-bang against a database whose schema has drifted from International's in ~48 unreconciled migrations.

The work that was done is real and mostly high-quality. The gap is between "the refactor is complete" (true, backend) and "the product works as specced" (not yet — the user-facing international layer is the unbuilt half).

---

## 1. Reconciliation — promised vs built, by layer

Authoritative sources: `Plans/multi_country_architecture.md` v1.1 (April, product intent), `Plans/architecture-spec-v3.md` + `architecture-plan-v3.md` (May, the "UK as a pack" execution), `Plans/test-gauntlet-plan-v2.md` (quality campaign).

| Layer | Promised | Built | Verdict |
|---|---|---|---|
| **Backend pack isolation** | GB App\-free; core pack-literal-free; arch fence enforced | Done; suite green, 0 skips | ✅ **MET** |
| **Container query layer** | Typed contracts so core reads packs without literals | 4 contracts + composites + GB impls + ZA Nulls, all bound | ✅ **MET** |
| **Sidebar from pack `navigation()`** | Core concatenates per-pack manifests; `MODULES_BY_JURISDICTION` deleted | Constant gone; manifests exist… | ⚠️ **COSMETIC** — manifests have **zero consumers**; sidebar is hardcoded `SideMenu.vue` with `zaOnly` ternaries flipping 6 destinations |
| **Frontend relocated to packs** | GB+ZA own views/stores/services/routes, dynamic-imported on auth | Components only moved; **all views, 31 stores, all services still in core monolith** | ❌ **NOT MET** (R-13 half-done) |
| **Conditional route registration + bundle isolation** | Inactive packs' routes never registered; SA user downloads no GB JS | All routes statically registered; SA-only user is routed *into* UK views | ❌ **NOT MET** |
| **Jurisdiction lifecycle** | Geo-registration at signup; auto cross-border on foreign asset | Registration writes **no jurisdiction row**; `GeoLocationService` orphaned; activation is chicken-and-egg | ❌ **NOT MET** |
| **Backend jurisdiction enforcement** | Middleware rejects wrong-pack calls | Middleware is a no-op (no `{cc}` routes); any user hits any pack's API → 200 | ❌ **NOT MET** |
| **Money as int-minor (ADR-005)** | All money columns → `amount_minor`+`currency_code` | Shadow columns added April; **no backfill/flip/drop; 0 models use MoneyCast**; int-minor only in ~12 service signatures | ⚠️ **STALLED** — scope silently eroded |
| **SA pack** | Full SA product | 5 modules full (Savings/Investment/Retirement/Protection/ExchangeControl); Estate partial; Goals+Coordination dead APIs; 6 modules absent | ⚠️ **OVERSTATED as "complete"** |
| **Test Gauntlet** | G-0→G-7 quality gate before prod | Machine layers done + verified; human layers (G-6/G-7) untouched; freeze in undefined state | ⚠️ **PARTIAL** |
| **Deployed** | Dev green + smoke-tested | Dev is 2 months / ~200 commits / one major-version stale | ❌ **NOT DEPLOYED** |

Designed-in gaps (correctly deferred, not failures): cross-border pack (Phase 3), dual-user `<CountryView>`/`<Money>` UX (Phase 2), `fx_rates`, vue-i18n per-pack scopes.

---

## 2. Intent iterations — the spec drifted and orphaned things

The April architecture doc (`multi_country_architecture.md` v1.1) was **never formally amended**, but later decisions (in `MEMORY.md`, spec v3, and commits) silently reversed several of its pillars. Anything built to the old intent is now orphaned; anything a reader trusts in the old doc is now wrong.

| April intent (v1.1) | Superseded by (later) | Consequence |
|---|---|---|
| Signup asks country of residence | Geo-registration, no country picker | Neither is implemented — registration attaches no jurisdiction at all |
| `Settings → Jurisdictions` management UI | "No jurisdiction management UI" (product model) | Add/remove-jurisdiction flows (v1.1 §11.4/11.5) don't exist |
| `fynla.co.za` + `fynla.co.uk` separate domains | Single domain `fynla.org` | Domain/SEO/marketing strategy (§12) is moot |
| Dual-user sidebar with "United Kingdom" header | Single flat list, no country headers | Cross-border UX (§11.3) contradicts the current rule; dual users are *worse* off than SA-only (ternaries flip back to UK) |
| Stripe UK + Paystack/Payfast ZA billing | Revolut | **No ZA payment processor exists** — the `PaymentProcessor` contract has zero implementers; SA users can't pay |
| 2 iOS bundles (GB/ZA) | Single bundle | `build-ios-gb.sh`/`build-ios-za.sh` (§13) never created |

**Action:** either amend `multi_country_architecture.md` with a "Superseded decisions" changelog, or mark it historical. Right now it's the most authoritative-looking doc and it's materially misleading.

---

## 3. Blindspots — known-ish, but not tracked or acted on

These are things the team half-knows or should see, but nothing is surfacing them.

1. **Deploy has been blocked for 4→13 sessions and nobody resolved the mechanism.** Every May handover front-loaded "resolve the dev-deploy blocker"; it never happened. The documented option (iii) "git pull on server" is **impossible** — the server isn't a git checkout. Dev has been frozen at 12 May since before the L12 upgrade.

2. **Web builds cannot run on this machine.** Local Node is 18.15; the PWA build chain needs ≥20 (the known `crypto is not defined` failure). No `engines` field, no guard in the build scripts. This is in `MEMORY.md` but in no backlog, so the first deploy attempt fails cryptically.

3. **The L12 deploy is a big-bang nobody has a runbook for.** The routine flow ("upload changed files") has **no `composer install` step**, so L12 vendor never reaches the server → fatal. First post-upgrade deploy = code + vendor + migrate + Node-20 build, with no rehearsal environment.

4. **No CI exists.** No `.github/workflows` despite a GitHub remote. The 612-route auth gate, pack isolation, design-guide rules, and Larastan are enforced **only by local discipline** — a human push to `main` bypasses all of them.

5. **The prod freeze is in an undefined state.** It nominally ended ~12 July; its exit gates (G-6 user test, G-7 go/no-go) are untouched; no document records a lift or extension. Today is 15 July.

6. **Documentation has rotted.** Vault (`FynlaInter`) unsynced since May (no June/July folders). `CSJTODO.md` frozen at 13 May. `TODO.md` is March-era legacy. Root `CLAUDE.md` still says "Laravel 10", wrong component/model/test counts, missing Thabo persona, wrong dev ports. Sub-`CLAUDE.md` files cite the `/fynla/` base path (the exact blank-page hazard) and claim agents live in `app/`. The `vault-context` skill points at dead paths.

7. **ADR-005 (money-as-int) silently downscoped** from "all money columns" to "a dozen service signatures" with no doc recording the retreat; `NoFloatMoneyTest` never even scans `app/`, and ~40 files are whole-file-wildcard exempted — the debt was *relocated with the code, not paid down.*

8. **SA pack "COMPLETE" is overstated.** Accurate against its own deliberately narrow specs, but two of the five claimed items (Goals, Coordination) are **dead APIs with no UI**, and the flagship Estate dashboard **renders in £**.

---

## 4. Unknown unknowns — surfaced only by this audit

Nobody was tracking these. Ranked by blast radius.

1. **Two-writer production database.** fynla.org runs the **legacy** repo (95% confidence), which kept shipping schema changes *during* International's freeze (~2,100 commits since the April fork, migrations added through June). International plans to take over the prod DB *in place*, but **~48 legacy migrations already on prod** — including shared-table ALTERs like an `eval_user_id → preview_user_id` **rename** and `users.marital_status` enum changes — are **unreconciled** with International's migration chain. Running `migrate --force` on prod meets a `migrations` table full of entries International has never seen. No cutover runbook exists (G-7-a/b are the *tasks to write one*).

2. **A wrong regulatory value is being served for the active tax year.** FSCS deposit protection rose to **£120k/£240k joint** for 2026/27 (legacy fixed this 8 June). International's active-year seeder still seeds **£85k/£170k** — there is no `120000` anywhere in the repo. Four other post-fork legacy fixes are also unported: an auth throttle-bucket collision (MFA reset self-429s), unconditional IHT trust advice to sub-NRB estates, an uncapped pension top-up recommendation, and lifecycle SMTP pacing.

3. **A live TypeError is entombed in the Larastan baseline.** `packs/country-gb/src/Investment/ScenarioService.php:165` dispatches `RunMonteCarloSimulation` with positional args against a keyword constructor → crashes whenever `InvestmentScenarioController` runs it. The 7/6 audit said "add to triage"; it never got an E-number, and the Larastan baseline of 11 errors + "don't add to baseline" policy appears to be *hiding it.*

4. **E-24: an unfixed HIGH cross-user data-injection vuln** with the same shape as the one that *was* fixed (S4-H1). `StorePropertyRequest` accepts `trust_id`/`joint_owner_id` with unscoped `exists:` rules; `TrustAssetAggregatorService` aggregates by `trust_id` alone. An attacker injects assets into a victim's IHT trust aggregation and pollutes their net-worth/estate views. The recommended shared caller-scoped rule was never built.

5. **A real SA user cannot enter the SA product.** Registration assigns no jurisdiction; geo-registration is unimplemented; the only auto-activation path needs a `country_code='ZA'` asset that can only be created from screens gated behind having ZA active. Chicken-and-egg → SA modules are unreachable except for the seeded preview persona.

6. **SA money data rides GB tables.** ZA savings → `Gb\SavingsAccount`, holdings → `Gb\Holding`, RA → `Gb\DCPension` (enum extended), discriminated by `country_code`. GB observers/services watching those shared models may fire on ZA rows — untested. `ZaSavingsController::listAccounts` has **no `country_code` filter**, so a cross-border user sees UK bank accounts inside the ZA TFSA screen.

7. **Morph-safety hangs on an unenforced invariant.** User identity resolves via `class_alias(Fynla\Core\Models\User, 'App\Models\User')`. If provider boot-ordering ever makes a real `App\Models\User` autoloadable first, legacy Sanctum token rows resolve to the wrong class. Nothing in the test suite pins this.

8. **The smoke-test pack ships to production.** `country-xx-smoke` registers unconditionally via composer discovery, so `api/xx/health` is live in prod builds.

9. **`fynla-fixes` worktree holds 86 uncommitted modified files** on legacy `dev` (4 July) while `origin/dev` has moved on — possible lost work, worth triaging before it's clobbered.

10. **Backend/frontend ZAR formats disagree** (`R 1 234,56` vs `R 1 234.56`), the POPIA privacy notice carries **no demonstration caveat** (reads as a real legal commitment), and "FAIS" is never spelled out in rendered text (acronym-rule violation).

---

## 5. Deployed-state facts (probed this session)

- **Dev (csjones.co/fynla_inter):** PHP 8.2.32, **Laravel 10.50.2**, not a git checkout, `composer.lock` dated 12 May, log last written 12 May. ~2 months / ~200 commits / one major version behind local. Publicly reachable **with the May HIGH security holes still live** (the fixes are in git only).
- **Prod (fynla.org):** SSH probe **denied by policy** (not named as an authorized target). State unverified from the server; lineage established from docs → runs the legacy repo.

---

## 6. What I'd do next (recommended order)

Nothing below was applied — this is a read-only reconciliation. Suggested sequence:

**Stop-the-bleeding (correctness/security, do regardless of cutover timing):**
1. Fix FSCS 2026/27 values (£120k/£240k) — wrong regulatory guidance shipping to users on the active year.
2. Fix E-24 unscoped-FK (build the shared caller-scoped `exists` rule; apply to Property/Trust/joint-owner requests).
3. Fix the `ScenarioService` Monte Carlo TypeError and *remove it from the Larastan baseline* (then audit what else the baseline hides).
4. Port the 5 legacy fixes (auth throttle buckets, IHT gating, pension cap, SMTP pacing, NetWorth lazy-load).

**Unblock delivery:**
5. Upgrade local Node to ≥20; add an `engines` field + preflight guard to both build scripts; fix the three stale deploy-script paths.
6. Write the real dev-deploy procedure *including* server-side `composer install` and `packs/` rsync; do one clean L12 dev deploy; smoke-test.
7. Stand up minimal CI (GitHub Actions: Pest + Architecture suite + Larastan) so the invariants stop depending on memory.

**Decide the two big questions (these are yours, not mine):**
8. **Cutover reconciliation** — before any prod migrate, diff legacy-prod schema vs International's chain column-by-column; decide how the two `migrations` tables merge. This is the highest-risk unknown.
9. **The international layer** — decide whether SA is actually launching. If yes, the real work is: geo/jurisdiction-on-registration, a ZA payment path, un-Null the query repos (so SA money reaches the dashboard), and finish R-13 (pack-owned frontend). If SA is a demo for now, say so in the docs and stop the "COMPLETE" language.

**Housekeeping:**
10. Amend or retire `multi_country_architecture.md`; refresh `CLAUDE.md` counts + version; sync the vault; delete the two dead branches; move closed triage rows.

---

*Full evidence with file:line references retained in `findings.md` at repo root (this session's working notes).*

---

## Addendum — fixes applied this session (branch `fix/reconciliation-fixes`)

After the reconciliation, CSJ confirmed **Fynla International is dev-only, separate from legacy in all respects, and never production** — which retires the §4.1 cutover hazard entirely (there is no cutover). CSJ then approved the §6 recommendation. The stop-the-bleeding and unblock-delivery items were implemented; each is a real bug in International's own code (the legacy repo's equivalent fixes were used as references, not "ported").

### Correctness & security (each with a passing test)

| Fix | Change | Test |
|---|---|---|
| **E-24 IDOR (HIGH)** | New `core/app/Core/Rules/BelongsToCurrentUser.php` (generic, no pack knowledge) scopes `trust_id` to the caller's own trust across all 8 asset requests (Property ×2, Chattel ×2, Business ×2, Savings, Investment). `ChattelController` no longer trusts `household_id` from input. | `tests/Feature/Security/TrustFkScopingTest.php` (4) |
| **FSCS regulatory value** | 2026/27 config now serves £120,000 / £240,000 joint / £1,400,000 THB (was £85k/£170k/£1m); `SavingsActionDefinitionSeeder` thresholds made config-driven (rule #3). | `tests/Feature/Tax/FscsProtectionLimitTest.php` |
| **Monte Carlo TypeError** | `ScenarioService::runScenario` maps scenario params + converts percent→decimal, throws a clear `FinancialCalculationException` on incomplete input instead of a TypeError; removed the entombed `phpstan-baseline.neon` entry (`composer analyse` clean). | `tests/Feature/Investment/ScenarioMonteCarloDispatchTest.php` (2) |
| **IHT over-advice** | `ComprehensiveEstatePlanService` emits the "Immediate Actions" trust/gifting block only when `currentIHTLiability > 0`, savings capped at the liability. | `tests/Unit/Services/Estate/OptimizedStrategyGatingTest.php` (2) |
| **Pension top-up advice** | `RetirementActionDefinitionService` caps tax-relievable headroom at relevant earnings (£3,600 gross / £2,880 net non-earner floor, new `TaxDefaults::NON_EARNER_PENSION_NET_CONTRIBUTION`) and gates out age ≥ 75. | `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php` |
| **Auth throttle collision** | Named per-endpoint limiters `auth-{3,5,10}` in `RouteServiceProvider`, keyed by `path\|ip` so login/register/verify/reset no longer share one bucket (multi-step MFA reset no longer self-429s). | `tests/Feature/Auth/AuthThrottleBucketTest.php` |
| **NetWorth lazy-load** | `NetWorthService` eager-loads `jointOwner` on joint queries (was throwing `LazyLoadingViolationException` on dev/staging). | `tests/Unit/Services/NetWorthServiceTest.php` |
| **Lifecycle SMTP pacing** | Added `config('lifecycle.throttle_ms')` (default 150). Engine wiring N/A until the batch-send loop exists — `LifecycleEngine` is still the 2-method MVP stub. | `tests/Unit/Services/Lifecycle/LifecycleEngineTest.php` |

All 8 new/modified test files pass together (54 tests). The full-suite regression caught one collateral break from A2 — making `SavingsActionDefinitionSeeder` config-driven meant it now needed an active tax config, which broke 4 `CrossModuleIntegrationTest` cases that seed it in isolation. Fixed with a try/catch fallback in the seeder (authoritative value still from config in normal seeding). Full suite re-run confirmed green: **3067 passing, 0 failing, 1 pre-existing skip** (12k assertions).

### Delivery unblock

- `package.json` `engines >= 20`; Node-20 guard added to `deploy/csjones-fynla/build.sh` (the PWA/terser chain fails on Node 18).
- Fixed two stale server paths in the dev build script (double-`public` upload target; `cd` that missed the app dir) and documented the missing `composer install --no-dev` step for the Laravel 12 vendor tree.
- `.github/workflows/ci.yml` — Larastan gate (no DB) + full Pest with a MySQL service. **Needs a first-run check** once pushed (env details can't be verified locally).

### Docs & memory

- `CLAUDE.md`: `Laravel 10 → 12`; Production line reframed dev-only; a lineage caveat added to the Deployment section (fynla.org = legacy, not an International target). The detailed fynla.org deploy procedure was left in place but flagged — **recommend deciding whether to delete it** rather than leaving a prod procedure for a dev-only app.
- Memories corrected: International is dev-only / never fynla.org / no cutover.

### Flagged as tracked tasks (too big to inline safely)

1. **`joint_owner_id` consent model** — the remaining E-24 tail; can't be scoped like `trust_id` (it references another person), needs a consent decision so it doesn't break spouse-linking.
2. **8 `$taxCalculator` baseline entries** — un-entombing the Monte Carlo error surfaced a sibling cluster rooted in the `ResolvesIncome` trait; some may be live null-access crashes depending on call paths.
3. **InvestmentScenario feature** — half-built: DB enum ↔ factory ↔ controller validation for `scenario_type` all disagree, and no frontend drives the Monte Carlo path. Finish or delete.

### Still held (need product decision + spec→plan→PRD)

The international layer itself: geo/jurisdiction-on-registration, a ZA payment path, un-Nulling the query repos so SA money reaches the dashboard, and finishing R-13 (pack-owned frontend). These are features, not fixes — untouched.

