---
type: prd
workstream: R-14b
date: 2026-05-09
status: Draft — codebase audit complete; ambiguities resolved with documented defaults; ready for implementation
spec: Plans/architecture-spec-v3.md (§§ 4-5, 7 unchanged)
plan: Plans/architecture-plan-v3.md (§ 16b amended 2026-05-09)
predecessor: R-14a CLOSED 2026-05-09 (14/14 sub-batches)
successor: R-15 (full regression + dev/prod deploy)
branch: refactor/uk-pack-relocation
owner: CSJ
---

# PRD — R-14b: Container-Resolved Query Layer + Relocation of 6 Deferred Core Models

**Project:** UK Pack Relocation, Phase 1 — close the deferred core-model batch
**Owner:** CSJ
**Status:** Draft
**Date:** 9 May 2026
**Spec:** `Plans/architecture-spec-v3.md` (unchanged — design intent already captures the 6 deferred models staying in core; see § 5 line 106 and § 7 lines 152-159)
**Plan:** `Plans/architecture-plan-v3.md` § 16b (amended 2026-05-09 from 5 hr → 11 hr; single contract → three; R-9-final pre-condition added; UserResource added to scope)
**Codebase audit:** Completed 2026-05-09 — see § 8 for residual concerns

---

## 1. Context & Why

### Problem

R-4 relocated 53 UK-specific Eloquent models into `packs/country-gb/src/Models/` but deliberately left 6 models behind in `app/Models/` because they hold relationship literals that point into the GB pack (`hasMany(\Fynla\Packs\Gb\Models\Estate\Trust::class)`, etc.). Moving these 6 models to `core/app/Core/Models/` without first reversing the dependency arrow would put pack-namespace literals inside core code — a structural violation that `CoreIndependenceTest` catches.

The 6 deferred models:

| Model | Lines | Pack-coupled methods | Pack imports |
|------|-------|----------------------|--------------|
| `app/Models/User.php` | 816 | 7 active relationship methods (Estate × 6 + Investment × 1) plus pack-imported asset relations (Savings, Retirement, Protection, Property paths) | 18 |
| `app/Models/Household.php` | 97 | 6 (`properties`, `businessInterests`, `chattels`, `cashAccounts`, `investmentAccounts`, `trusts`) | 6 |
| `app/Models/Goal.php` | 343 | 3 typed FK arrows (`linkedSavingsAccount`, `linkedInvestmentAccount`, `savingsAccounts` belongsToMany) | 2 |
| `app/Models/GoalContribution.php` | 73 | 0 (clean — only `belongsTo(Goal/User)`) | 0 |
| `app/Models/LifeEvent.php` | 282 | 0 (clean — only `belongsTo(User)` + `hasMany(LifeEventAllocation)`) | 0 |
| `app/Models/LifeEventAllocation.php` | 46 | 0 (clean) | 0 |

`tests/Architecture/PackIsolationTest.php` lines 201-211 hold 6 R-14b-tagged allow-list entries (one per deferred model) plus a 7th R-14b-tagged entry at lines 224-228 for `App\Http\Resources\UserResource` ("Stays in app/Http/Resources/ until User relocation in R-14b"). Until R-14b ships, the architectural test suite cannot prove pack isolation against core.

### Business case

R-14b is the penultimate workstream in the v3 architecture plan. Its successor R-15 (full regression + deploy) requires the PackIsolationTest allow-list to be empty; without R-14b, R-15 cannot reach plan close. The architecture-plan-v3 sequence is `R-0 … R-14a CLOSED → R-14b → R-15 → plan close`. R-14b unblocks plan close (~67 hr cumulative budget; ~14 hr remaining: 1.5 R-9-final + 11 R-14b + 3 R-15, less the slack absorbed across R-14a).

The strategic value is structural: with the 6 models in core, every UK feature can work on the same `User` / `Household` / `Goal` instances every other pack works on (today and future), without core reaching into pack namespaces. This is the precondition for SA pack data model work in Phase 1 and cross-border relationship logic in Phase 2.

### Strategic fit

R-14b touches all 7 Fynla modules indirectly (every module reads `User` and most read `Household`/`Goal`). Direct surface area is concentrated in:

- **Goals & Life Events** (`app/Services/Goals/`, `packs/country-gb/src/Goals/`, `packs/country-gb/src/Plans/GoalPlanService.php`) — the linked-account FK contracts.
- **Estate** (`packs/country-gb/src/Estate/`, `packs/country-gb/src/Plans/EstatePlanService.php`) — the 6 Estate query methods (liabilities, trusts, IHT profile, assets, gifts, LPAs).
- **Investment / Savings / Retirement / Protection** (pack services that read aggregations) — `userAccounts()` listings.
- **Coordination** (`packs/country-gb/src/Coordination/`) — orchestrator reads household assets.

Phase 0 (Workstream 0.6 jurisdiction plumbing) is already complete. Phase 1 SA pack work depends on the new contracts being available so the SA pack can ship Null implementations on day one. R-14b lands cleanly between R-14a (CLOSED) and R-15 (plan close).

---

## 2. Target Persona

**Infrastructure — indirectly benefits all personas.** R-14b has no user-facing change. It restructures internal namespaces and dependency arrows. After it ships, every persona (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple) sees identical behaviour at every URL.

The internal beneficiaries are:

- **Engineers maintaining the codebase** — fewer namespace exceptions to keep in their head; PackIsolationTest allow-list shrinks by 7 entries; CoreIndependenceTest activates against `core/app/Core/Models/`.
- **Future country-pack authors** (Phase 2+) — the `PackAssetRepository` / `PackEstateRepository` / `PackAssetResolver` contracts give a clear extension point. Adding country #3 means implementing 3 contracts, not editing core models.

**Primary:** Infrastructure
**Secondary:** None — no user-facing surface

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| PackIsolationTest allow-list R-14b-tagged entries | 7 (6 core models + UserResource) | 0 | Read `tests/Architecture/PackIsolationTest.php` after R-14b-ix |
| `CoreIndependenceTest` active for `core/app/Core/Models/` | inactive (skipped or empty target) | active and green | `./vendor/bin/pest --testsuite=Architecture --filter=CoreIndependenceTest` |
| `Fynla\Packs\Gb\` literals in `core/app/Core/Models/*.php` | 0 (currently empty namespace target) | 0 (after 6 models land) | `grep -r "Fynla\\\\Packs" core/app/Core/Models/` |
| Pest suite | 2,825 passed / 1 skipped | 2,825 passed / 1 skipped (no regressions) | `./vendor/bin/pest` |
| `App\Models\(User\|Household\|Goal\|GoalContribution\|LifeEvent\|LifeEventAllocation)` imports remaining in repo | ~417 import lines (audit count) | 0 (all migrated to `Fynla\Core\Models\`) | `grep -rn "use App\\\\Models\\\\" --include="*.php" \| count` |
| Login + dashboard render time (UK persona) | ~unknown — measure baseline before R-14b-vii | within 10% of baseline | Browser test in Playwright; observe network tab + console |
| Plan-close hour budget | ~67 hr provisional total | within 10% (75 hr ceiling) | Sum of commit-attribution hours across R-9-final + R-14b-i…ix + R-15 |

---

## 4. User Stories & Scenarios

### User stories

R-14b is infrastructure. The "user" stories are framed as engineering-experience stories:

- As a **GB pack author**, I want core's `User` model to expose pack-asset listings via a contract so that I can move my pack's models without editing core code.
- As a **future SA pack author**, I want to implement three small contracts (`PackAssetRepository`, `PackEstateRepository`, `PackAssetResolver`) instead of editing core models, so that adding SA assets to a user's portfolio doesn't require a core change.
- As a **dual-jurisdiction user** (Phase 2 — out of scope for R-14b but must not be blocked), I want my UK and SA assets to merge transparently when listed against my user record, so that I don't have to flip a jurisdiction toggle.
- As a **CI engineer**, I want `CoreIndependenceTest` and `PackIsolationTest` allow-lists to ratchet down to zero R-14b entries, so that the architecture suite proves pack isolation rather than tolerating exceptions.

### Key scenarios

**Scenario 1 — UK user logs in, dashboard renders identically (regression-only)**

1. UK user (`john@example.com`) logs in at `https://csjones.co/fynla` (dev) or `localhost:8000` (local).
2. Dashboard loads. Sidebar lists the same modules as before R-14b (Protection, Savings, Investment, Retirement, Estate, Goals, Coordination).
3. Each module's main view renders identical totals to the pre-R-14b baseline. Investment account list, savings account list, household property list, IHT profile, gifts, trusts, liabilities, LPAs all populated.
4. Network tab shows no new endpoints. No console errors. No 500s.

**Expected outcome:** Identical UX. R-14b is structural-only.

**Scenario 2 — Goal links to a savings account via FK; goal page renders the linked account name**

1. UK user navigates to Goals.
2. Selects a goal that has `linked_savings_account_id` set.
3. Page shows the linked savings account's display name and current balance (resolved through `PackAssetResolver::resolveAccount('gb.savings_account', $id)` rather than the legacy `belongsTo(SavingsAccount::class)` relation).

**Expected outcome:** Identical render. The change from `belongsTo` to `PackAssetResolver` is invisible to the user.

**Scenario 3 — Architecture test suite green after sub-batch ix**

1. Engineer runs `./vendor/bin/pest --testsuite=Architecture`.
2. `CoreIndependenceTest` activates for `core/app/Core/Models/` and finds zero `Fynla\Packs\` imports.
3. `PackIsolationTest` allow-list returns no R-14b-tagged entries.

**Expected outcome:** 130/130 architecture tests green (or 131/131 if `CoreIndependenceTest` was previously skipped and now activates).

**Scenario 4 — Sanctum token resolution (regression check)**

1. UK user with an active Sanctum token (issued before R-14b) hits an authenticated API endpoint (e.g. `GET /api/protection`).
2. Sanctum resolves the token via `personal_access_tokens.tokenable_type` lookup.
3. If the row contains the legacy `App\Models\User` string, the morph alias `'user' => Fynla\Core\Models\User::class` registered in `CoreServiceProvider` resolves it to the new namespace.

**Expected outcome:** Existing tokens continue to work. If row count is non-zero, sub-batch viii's backfill migration also normalises the column to the new FQCN.

**Unhappy path: morph alias missing → 500 on API call**

If sub-batch viii forgets to register the morph alias OR fails to backfill stale rows, Sanctum throws `Class "App\Models\User" not found` on token resolution. Mitigation: sub-batch viii commits the morph alias and the User move together; sub-batch ix browser-tests an authenticated API call.

---

## 5. Functional Requirements

Prioritised using MoSCoW. Each requirement references the sub-batch from `Plans/architecture-plan-v3.md` § 16b and the specific touchpoints.

### Must-have

- **FR-M1:** Define three typed contracts in `core/app/Core/Contracts/`:
  - `PackAssetRepository` with `userAccounts(int $userId): Collection<AssetSummary>` and `householdAssets(int $householdId): Collection<AssetSummary>`.
  - `PackEstateRepository` with `liabilitiesForUser`, `trustsForUser`, `ihtProfileForUser`, `estateAssetsForUser`, `giftsForUser`, `lpasForUser` (each `(int $userId): Collection|?Model`).
  - `PackAssetResolver` with `resolveAccount(string $assetType, int $id): ?Model`.
  _Touches: `core/app/Core/Contracts/PackAssetRepository.php` (NEW), `core/app/Core/Contracts/PackEstateRepository.php` (NEW), `core/app/Core/Contracts/PackAssetResolver.php` (NEW), `core/app/Core/Query/AssetSummary.php` (NEW)._ Sub-batch R-14b-i.

- **FR-M2:** Define core composite default implementations that iterate `PackRegistry::all()` and merge results across registered packs.
  _Touches: `core/app/Core/Query/CompositePackAssetRepository.php` (NEW), `core/app/Core/Query/CompositePackEstateRepository.php` (NEW), `core/app/Core/Query/CompositePackAssetResolver.php` (NEW). Bind defaults in `core/app/Core/Providers/CoreServiceProvider.php`._ Sub-batch R-14b-i.

- **FR-M3:** Provide GB pack implementations of all three contracts.
  _Touches: `packs/country-gb/src/Query/GbPackAssetRepository.php` (NEW), `packs/country-gb/src/Query/GbPackEstateRepository.php` (NEW), `packs/country-gb/src/Query/GbPackAssetResolver.php` (NEW). Add 3 bindings (`pack.gb.asset_repo`, `pack.gb.estate_repo`, `pack.gb.asset_resolver`) to `packs/country-gb/src/Providers/GbPackServiceProvider.php`._ Sub-batch R-14b-ii.

- **FR-M4:** Provide ZA pack Null implementations of all three contracts (empty collections / null returns).
  _Touches: `packs/country-za/src/Query/ZaPackAssetRepository.php` (NEW), `packs/country-za/src/Query/ZaPackEstateRepository.php` (NEW), `packs/country-za/src/Query/ZaPackAssetResolver.php` (NEW). Add 3 bindings to `packs/country-za/src/Providers/ZaPackServiceProvider.php`._ Sub-batch R-14b-iii.

- **FR-M5:** Relocate 3 clean models (`GoalContribution`, `LifeEvent`, `LifeEventAllocation`) to `core/app/Core/Models/` under namespace `Fynla\Core\Models\`. Update all callers' `use` imports.
  _Touches: `app/Models/{GoalContribution,LifeEvent,LifeEventAllocation}.php` (DELETED), `core/app/Core/Models/{GoalContribution,LifeEvent,LifeEventAllocation}.php` (NEW), all caller imports per audit count._ Sub-batch R-14b-iv.

- **FR-M6:** Relocate `Goal` to core. Replace 3 pack FK relations (`linkedSavingsAccount`, `linkedInvestmentAccount`, `savingsAccounts` belongsToMany) with `PackAssetResolver` calls. FK columns stay in `goals` table.
  _Touches: `app/Models/Goal.php` (DELETED), `core/app/Core/Models/Goal.php` (NEW), `app/Observers/GoalObserver.php` (import update), `packs/country-gb/src/Goals/*` callers, R-9-final-relocated `packs/country-gb/src/Http/Controllers/GoalsController.php` (import + `Goal::` static call updates — 15 sites)._ Sub-batch R-14b-v.

- **FR-M7:** Relocate `Household` to core. Replace 6 pack `hasMany` literals with `PackAssetRepository::householdAssets()` calls.
  _Touches: `app/Models/Household.php` (DELETED), `core/app/Core/Models/Household.php` (NEW), R-9-final-relocated `HouseholdController`, household-asset consumers in `app/Services/` and `packs/country-gb/src/`._ Sub-batch R-14b-vi.

- **FR-M8:** Relocate `User` to core (the largest single sub-batch). Replace 6 Estate `hasMany` with `PackEstateRepository` calls. Replace pack-asset `hasMany` (Investment / Savings / Retirement / Protection / Property paths) with `PackAssetRepository::userAccounts()`. Verify `HasApiTokens` morph resolution. Co-relocate `app/Http/Resources/UserResource.php` to `core/app/Core/Http/Resources/UserResource.php` (namespace `Fynla\Core\Http\Resources`).
  _Touches: `app/Models/User.php` (DELETED), `core/app/Core/Models/User.php` (NEW), `app/Http/Resources/UserResource.php` (DELETED), `core/app/Core/Http/Resources/UserResource.php` (NEW), 4 controller imports for UserResource (`AuthController`, `MFAController`, `UserProfileController`, plus `GoalResource` + `GoalContributionResource` nested wraps)._ Sub-batch R-14b-vii.

- **FR-M9:** Register Eloquent morph alias `'user' => Fynla\Core\Models\User::class` in `core/app/Core/Providers/CoreServiceProvider::boot()`. Run row-count check on `personal_access_tokens.tokenable_type` and `audit_logs.model_type`; if non-zero, write a backfill migration in `database/migrations/`. Update `config/auth.php` line 65 from `App\Models\User::class` to `Fynla\Core\Models\User::class`. Sed-replace `App\Models\(User|Household|Goal|GoalContribution|LifeEvent|LifeEventAllocation)` across all consumers atomically with the User move.
  _Touches: `config/auth.php`, `core/app/Core/Providers/CoreServiceProvider.php`, optional `database/migrations/2026_05_*_backfill_user_morph_namespace.php`, ~417 import lines across `app/`, `core/`, `packs/country-gb/src/`, `tests/`, `database/`._ Sub-batch R-14b-viii.

- **FR-M10:** Architecture suite green. PackIsolationTest allow-list -7 entries (6 core models + UserResource). Full Pest suite at 2,825 passed / 1 skipped (or higher if `CoreIndependenceTest` activates as a new active test).
  _Touches: `tests/Architecture/PackIsolationTest.php` (allow-list edits + comment updates)._ Sub-batch R-14b-ix.

### Should-have

- **FR-S1:** Document the contract surface in `core/app/Core/Contracts/README.md` (or extend `docs/adr/` with a new ADR-008 — "Pack-mediated query contracts for cross-pack relationship reads"). Records why three contracts and not one, and what the type tag conventions are.
  _Touches: `docs/adr/ADR-008-pack-query-contracts.md` (NEW)._ Optional during R-14b; can defer to a follow-up doc commit if R-14b runs long.

- **FR-S2:** Update `MEMORY.md` index with a new memory file capturing the contract pattern (`feedback_pack_query_contracts.md` or similar) so future sessions don't redesign the surface from scratch.
  _Touches: `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/memory/`._ Optional.

### Nice-to-have

- **FR-N1:** Consider extending the contract pattern to `User::familyMembers()` (currently `core/app/Core/Models/FamilyMember.php`) if a future country needs jurisdiction-aware family members. Not needed for R-14b.
- **FR-N2:** Lint rule (PHPStan or custom static analyser) that fails any new `hasMany(\Fynla\Packs\…)` literal in `core/app/Core/`. PackIsolationTest already catches imports at the use-statement level; a stricter Eloquent-relation check would catch bare-class-string usage. Future hardening.

---

## 6. User Flow & UX/Design

### Flow

```
[Pre-condition: R-9-final batch — relocate 8 UK-specific controllers from app/ to packs/country-gb/]
    │
    ▼
[R-14b-i: Contracts] ──▶ [R-14b-ii: GB impls] ──▶ [R-14b-iii: ZA Null impls]
    │                                                      │
    ▼                                                      ▼
[R-14b-iv: GoalContribution + LifeEvent + LifeEventAllocation (clean models)]
    │
    ▼
[R-14b-v: Goal] ──▶ [R-14b-vi: Household] ──▶ [R-14b-vii: User + UserResource]
                                                      │
                                                      ▼
                              [R-14b-viii: morph alias + sed-replace + config/auth.php]
                                                      │
                                                      ▼
                              [R-14b-ix: Pest green + PackIsolationTest allow-list -7 + CoreIndependenceTest active]
                                                      │
                                                      ▼
                                                  [R-15 kickoff]
```

One commit per sub-batch, pushed individually. Pest runs after every commit. Strategy B caller-rename trick (R-14a-only) does not apply — every relationship method becomes a service call, and callers that previously chained `->liabilities` / `->trusts` / `->properties` on User or Household are updated to read through the contract.

### UX/Design notes

- **Design system:** Not applicable — no UI changes. R-14b is purely backend.
- **Jurisdiction visibility:** Not applicable — no user-facing surface changes. Contracts route through `PackRegistry::all()` so dual-jurisdiction users transparently see merged results once Phase 2 ships SA equivalents.
- **Reusable components:** None — backend-only work.
- **New components:** None — backend-only work.
- **Responsive behaviour / accessibility:** N/A.
- **Reference artefacts:** This PRD; `Plans/architecture-plan-v3.md` § 16b (amended); the two audit reports stored as agent outputs (architectural validation + consumer-surface enumeration) — not persisted to disk; reproducible by re-running the same prompts.

---

## 7. Out of Scope

- **Frontend changes.** R-14b is backend-only. No Vue components, Vuex modules, or sidebar manifests change.
- **`familyMembers()` / `householdMembers()` (already in core).** `FamilyMember` lives in `core/app/Core/Models/FamilyMember.php` already; it's not pack-coupled and stays as-is.
- **SA pack feature work.** R-14b ships ZA Null implementations only. SA assets, SA Estate, SA savings, SA goals are separate Phase 1 workstreams (WS 1.1 onwards) gated by their own PRDs.
- **Cross-border merge logic / Phase 2.** R-14b establishes the pattern (composite implementation iterates `PackRegistry::all()`) but does not implement the dual-jurisdiction asset-merge UX. That is Phase 2.
- **R-9 residuals beyond the 8 UK-specific controllers** named in the pre-condition. The 42 controllers still in `app/Http/Controllers/Api/` include legitimate core controllers (auth, payment, GDPR, admin, AI chat, mobile dashboard) that stay in core. Only 8 are in scope for the R-9-final pre-condition.
- **`UserProfileController`, `WhatIfScenarioController`, `LetterToSpouseController` and their service peers** (already deferred as R-14a-style float-money holdouts per `PackIsolationTest.php` lines 339-346, 354-357). Out of scope for R-14b; relocate in a dedicated UserProfile/WhatIf workstream after plan close.
- **ADR write-ups beyond ADR-008** if the user wants formal documentation. R-14b ships the code; ADR-008 is FR-S1 (Should-have, can be deferred).
- **Float-money refactor for the 6 deferred core models.** The 6 models do not hold money columns directly (User has no money, Goal has `target_amount` but the int-minor refactor is already complete on Goal per ADR-005). No money work in R-14b.
- **Renaming UK tables to `gb_*` prefix.** Per § 7 of `architecture-spec-v3.md`, table renames are deferred. R-14b does not touch table names.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Morph-map breakage on Sanctum token resolution after User namespace change | Medium | High | Register morph alias `'user' => Fynla\Core\Models\User::class` in `CoreServiceProvider`. Row-count `personal_access_tokens.tokenable_type` for `App\Models\User` strings; backfill if non-zero. Browser-test an authenticated API call in sub-batch ix. |
| Polymorphic `audit_logs.model_type` rows holding `App\Models\User` literals fail post-rename lookup | Low (legacy rows only) | Medium | Same: morph alias + optional backfill. `AuditLog::auditable()` (`core/app/Core/Models/AuditLog.php` ~line 106) is the lookup site. |
| Mass sed-replace across 162 test files leaves a partial-import state | Low | High (tests fail mid-suite if partial) | Sub-batch viii commits User move + sed-replace + morph alias + `config/auth.php` together as a single atomic commit. Run full Pest before committing; do NOT push until green. |
| Sub-batch vii (User) blow-up — too large to keep Pest green throughout | Medium | Medium | If User relocation alone breaks too many tests at once, split into vii-a (relocate User class only, keep `App\Models\User` as a class_alias() shim for one commit) → vii-b (sed-replace consumers in waves: app/, then packs/, then tests/) → vii-c (remove the alias). Re-budget +0.5 hr. |
| `CompositePackAssetRepository` performance regression on dashboard | Low | Medium | First implementation is a simple `PackRegistry::all()->map(fn($p) => app($p->bindings()['asset_repo'])->userAccounts($id))->flatten()`. If single-pack users see a real slowdown on dashboard, profile and add a single-pack short-circuit (skip the iteration when only one pack is active for the user). Spot-check in browser test, sub-batch ix. |
| R-9-final pre-condition takes longer than 1.5 hr | Medium | Low | The 8 controllers are a known shape (R-9 mechanics are settled). If one controller surfaces an unexpected dependency, defer to a separate R-9j commit and document the deferral in `PackIsolationTest.php` (same convention as R-14a deferrals). |
| `Goal::linkedSavingsAccount()` semantics change subtly via PackAssetResolver | Low | Low | Goal's current `belongsTo(SavingsAccount::class)` returns an Eloquent relation object (lazy-loaded, eager-loadable). The replacement is a method that returns `?Model` directly. Eager-loading via `Goal::with('linkedSavingsAccount')` no longer works the same way. Audit caller usage; if any caller relies on `with()`-style eager loading on this relation, redesign. From audit: `GoalsController` is the heaviest caller (15 static calls); spot-check eager-load patterns. |
| `UserResource` co-relocation surfaces unexpected coupling | Low | Low | Resource is import-clean (no `use App\Models\User;` import); 5 caller files (4 controllers + 2 nested resources) need import bumps only. |

### Technical dependencies

- `core/app/Core/Contracts/` directory exists — confirmed.
- `Fynla\Core\` PSR-4 autoload mapping in root `composer.json` line 43 — already wired. No `core/composer.json` needed.
- `PackRegistry` (`core/app/Core/Registry/PackRegistry.php`) — exists, already used by 13 contract bindings. Composite implementations consume `PackRegistry::all()`.
- `GbPackServiceProvider` and `ZaPackServiceProvider` — both exist with established binding patterns. R-14b adds 3 bindings to each.
- `HasApiTokens` trait (Laravel Sanctum) — standard; morph alias is the standard mitigation.
- `AuditLog::auditable()` (`core/app/Core/Models/AuditLog.php`) — uses manual `model_type`/`model_id` lookup; mitigation is identical to Sanctum's.

### Sequencing dependencies

- **Pre-requisite: R-9-final batch must close first** — see § 5 FR-M1 / Plan § 16b pre-condition. ~1.5 hr; 8 UK-specific controllers (`GoalsController`, `LifeEventController`, `LifeEventAllocationController`, `HouseholdController`, `PropertyController`, `MortgageController`, `BusinessInterestController`, `ChattelController`).
- **Successor: R-15** — full regression and dev/prod deploy. R-14b's deliverable (allow-list -7, CoreIndependenceTest active) feeds directly into R-15's acceptance criteria per `Plans/architecture-plan-v3.md` § 17.
- **Phase 2 cross-border work** consumes the contracts but is not blocked by R-14b shipping any Phase-2 logic — only the contract shape needs to land.

### External dependencies

- Production database access for `personal_access_tokens.tokenable_type` and `audit_logs.model_type` row-count check. If the count is zero, no backfill migration is needed; if non-zero, the migration is small (single UPDATE).

### Residual concerns from codebase audit

1. **Three contracts vs one — CSJ confirmation deferred.** Architectural audit recommends three; PRD adopts three as the documented default. CSJ may redirect at implementation kickoff if they prefer to start with one and split later. Reasonable-call rationale: User's 6 Estate methods are independently queried (no caller merges them into a flat collection), Goal's typed FK arrows don't fit `ownedBy()` shape — single-contract design forces type-filtering inside core, which is worse abstraction than starting typed.

2. **`personal_access_tokens.tokenable_type` row count** — must be checked at sub-batch viii kickoff. If zero, no backfill migration required (saves ~0.25 hr).

3. **Eager-loading semantics on Goal's pack FK relations** — `Goal::with('linkedSavingsAccount')` may behave differently after the relation becomes a method returning `?Model`. Audit caller usage at sub-batch v kickoff; may require a shim that returns a deferred-resolution object or accept the change.

4. **Sub-batch vii (User) split contingency** — if User relocation alone breaks too many tests at once for atomic commit, split into vii-a/b/c with a temporary `class_alias()` shim. Budget +0.5 hr.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 2026-05-09 | Initial draft via prd-writer skill. Codebase audit via parallel `feature-dev:code-architect` + `feature-dev:code-explorer` subagents. Plan § 16b amended (5 hr → 11 hr; one contract → three; R-9-final pre-condition added; UserResource added). Spec unchanged (design intent already correct). Ambiguities resolved with documented defaults per CSJ no-stopping instruction. | prd-writer skill (Claude Opus 4.7) |
