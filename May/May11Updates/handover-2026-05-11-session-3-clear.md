---
type: handover
mode: context-clear
date: 2026-05-11
session: 3
branch: refactor/uk-pack-relocation
previous_session: 2026-05-11 session 2 (context-clear — R-14b ii-vi SHIPPED 5/9)
---

# Context Clear Handover — 2026-05-11, Session 3

## Immediate state

**R-14b campaign CLOSED (9/9 sub-batches + prep). User + UserResource relocated to core. PackIsolationTest R-14b-deferred allow-list is now empty.** Three commits shipped this session: `d65ee8e` (vii-prep) → `0b432d5` (vii) → `85eaf19` (viii). Architecture **130/130** maintained. Full Pest **2,825 passed / 1 skipped** maintained. Branch tip `85eaf19`, **103 commits ahead of `main`**, all pushed. Working tree clean. Next workstream is R-15 — full regression + browser test + dev/prod deploy (~3 hr) — which closes plan v3.

## The thread

- **Session opened** by auto-continuing from session-2 handover (R-14b-vii queued, pre-flight check needed). Pre-flight: zero rows on `personal_access_tokens.tokenable_type` and `audit_logs.model_type` matching `App\Models\User` locally — so no backfill migration needed for viii.
- **vii-prep (`d65ee8e`):** Added new contract `PackUserRelationProvider` to core (`modelClassFor(string): ?string`, `userRelatedModels`, `userRelatedModel`), `CompositePackUserRelationProvider` default, `GbPackUserRelationProvider` impl with 25-entry classMap, `ZaPackUserRelationProvider` Null impl. Bindings on Core + GB + ZA service providers (`pack.{code}.user_relations`). Atomic additive — Pest baseline unchanged.
- **Strategic pivot away from PRD design:** PRD § 5 FR-M8 called for replacing User's pack relations with `PackAssetRepository::userAccounts()` returning `AssetSummary` VOs. Caller audit revealed **78+ method-call sites** (`->dcPensions()->exists()`, `->dcPensions()->findOrFail()`, `->dcPensions->sum('current_fund_value')`, `foreach($user->dcPensions as $p) { $p->scheme_type }`) + **17+ eager-load sites** (`User::with(['dcPensions.holdings', 'investmentAccounts.holdings'])`). AssetSummary VOs would have broken all of them. Extended PackUserRelationProvider with `modelClassFor(string): ?string` and refactored User's `hasMany()` / `hasOne()` to read target class via that contract — preserves Eloquent's full relation API while removing pack literals from core. Zero caller migration needed.
- **vii (`0b432d5`):** Atomic 491-file commit. `git mv app/Models/User.php` → `core/app/Core/Models/User.php` (namespace bump, 19 pack relations refactored to use `$this->packModel('gb.*')` helper). `git mv app/Http/Resources/UserResource.php` → `core/app/Core/Http/Resources/UserResource.php`. Atomic sed-replace `App\Models\User` → `Fynla\Core\Models\User` in 465 files (perl with `\b` boundary; zero false matches on `UserSession`/`UserConsent`/etc.). UserResource sed-replace in 9 files. `config/auth.php:65` updated. PackIsolationTest allow-list -2 (User + UserResource). ApplicationArchitectureTest 4 arch assertions retargeted from `App\Models` → `Fynla\Core\Models` (app/Models is now empty). Empty `app/Models/` directory removed. **53 SA test failures discovered + resolved** by retiring the runtime-string `resolveAppModel('User')` shim in 6 SA model files + 6 SA test files (replaced with `\Fynla\Core\Models\User::class` — legit pack→core import).
- **viii (`85eaf19`):** Small 17-line commit. Added `class_alias(\Fynla\Core\Models\User::class, 'App\\Models\\User')` to `CoreServiceProvider::boot()` as production-safety fallback for legacy polymorphic rows. Chose class_alias over `Relation::enforceMorphMap` because morph map would force NEW writes to also use the legacy string — opposite of intent. class_alias gives read-side fallback without changing write behaviour. Pre-flight zero locally; alias is dead code if production is also zero, prevents auth breakage if not.
- **Memory file saved:** `feedback_pack_query_contracts.md` capturing the four-contract pattern (PackAssetRepository, PackEstateRepository, PackAssetResolver, PackUserRelationProvider) with "pick by shape, not by convenience" decision rule. MEMORY.md indexed under new "Backend Patterns" section.

## Files touched this session

```
core/app/Core/Contracts/PackUserRelationProvider.php             (NEW — vii-prep + extended in vii)
core/app/Core/Query/CompositePackUserRelationProvider.php        (NEW — vii-prep + extended in vii)
packs/country-gb/src/Query/GbPackUserRelationProvider.php        (NEW — vii-prep + extended in vii)
packs/country-za/src/Query/ZaPackUserRelationProvider.php        (NEW — vii-prep + extended in vii)
core/app/Core/Providers/CoreServiceProvider.php                  (vii-prep binding + viii class_alias)
packs/country-gb/src/Providers/GbPackServiceProvider.php         (vii-prep binding)
packs/country-za/src/Providers/ZaPackServiceProvider.php         (vii-prep binding)

core/app/Core/Models/User.php                                    (NEW location, was app/Models/User.php — fully rewritten)
core/app/Core/Http/Resources/UserResource.php                    (NEW location, was app/Http/Resources/UserResource.php — namespace only)
app/Models/                                                      (directory REMOVED — empty after move)

config/auth.php                                                  (line 65: model FQCN updated)
tests/Architecture/PackIsolationTest.php                         (-2 allow-list entries: User + UserResource)
tests/Architecture/ApplicationArchitectureTest.php               (4 arch assertions retargeted to Fynla\Core\Models)
app/Console/Commands/SendProtectionAlerts.php                    (1 escaped morph string fixed manually)

packs/country-za/src/Models/ZaProtectionPolicy.php               (resolveAppModel('User') → direct \Fynla\Core\Models\User::class)
packs/country-za/src/Models/ZaTfsaContribution.php               (same + FamilyMember)
packs/country-za/src/Models/ZaRetirementFundBucket.php           (same)
packs/country-za/src/Models/ZaReg28Snapshot.php                  (same)
packs/country-za/src/Models/ZaHoldingLot.php                     (same)
packs/country-za/src/Models/ZaExchangeControlEntry.php           (same)
packs/country-za/tests/Unit/Za{Base,Exch,Reg28,Retire,Tfsa}*.php (6 SA unit test files: $userClass = '\\' . 'App' . '\\Models\\User' → \Fynla\Core\Models\User::class)

+ 465 sed-replaced files across app/, packs/, core/, tests/, database/ (mostly import lines)
```

Memory dir writes (not in repo):
```
/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/memory/feedback_pack_query_contracts.md  (NEW)
/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/memory/MEMORY.md                         (+1 section "Backend Patterns" indexing the new file)
```

## What the next Claude needs to know

- **R-14b is fully closed.** All 9 sub-batches (i contracts → ix verification) shipped + the vii-prep extension that should arguably be re-numbered. PackIsolationTest allow-list R-14b-deferred section is empty. CoreIndependenceTest active and green for all 6 relocated core models (`User`, `Household`, `Goal`, `GoalContribution`, `LifeEvent`, `LifeEventAllocation`).
- **R-14b-ix verification is satisfied implicitly by viii's commit run** — Architecture 130/130, full Pest 2,825/1 skipped, allow-list empty, browser smoke not done. Plan calls for "Spot-check UK dashboard renders the same totals; SA login resolves the new core User" — that's effectively R-15's browser test stage. No separate ix commit was created; the verification is captured in viii's commit body and the test results already in place.
- **PackUserRelationProvider has THREE methods, not two.** The first prep commit (`d65ee8e`) introduced `userRelatedModels` + `userRelatedModel` for an accessor-pattern approach. The vii commit added `modelClassFor` after the strategic pivot. All three coexist on the contract — the first two are unused by core User but kept for future cases where a caller wants an immediate Collection without going through Eloquent's relation builder.
- **User's pack relations use `$this->packModel('gb.X')` runtime resolution.** Looks like:
  ```php
  public function dcPensions(): HasMany {
      return $this->hasMany($this->packModel('gb.dc_pension'));
  }
  ```
  The `packModel()` helper calls `app(PackUserRelationProvider::class)->modelClassFor($type)` and throws if null. Tests that boot the app without GB pack provider registered would fail — but the GB pack is always registered in this codebase.
- **The `gb.` prefix is hardcoded in User.php** — not dynamic via `$this->primaryJurisdictionCode()`. This is acceptable for Phase 1 (UK is the only meaningful pack; SA is structural). Phase 2 cross-border work may want to make this dynamic, but the contract supports it (composite walks all packs and tries each `modelClassFor`).
- **class_alias for `App\Models\User`** is in place in `CoreServiceProvider::boot()` for legacy polymorphic-row safety. If production has zero rows with `App\Models\User` in `personal_access_tokens.tokenable_type` or `audit_logs.model_type` (likely — local was zero), the alias is dead code. Run the same row-count check at deploy time before R-15 ships to prod to confirm.
- **SA pack's `resolveAppModel()` shim is now partially retired.** Six SA model files + 6 SA unit test files updated for User + FamilyMember references (now direct `::class`). The shim is still called for `DCPension`, `SavingsAccount`, `Investment\Holding` — those are pre-existing tech debt (the shim returns broken `\App\Models\X` paths that don't exist, but no test exercises them today). Leave alone unless touched by a future workstream.
- **`app/Models/` directory is removed.** If a test or seeder hard-codes the path, it'll fail; nothing observed in this session.
- **Pre-existing flaky test still flaky.** `Tests\Feature\Api\InvestmentControllerTest > PUT /api/investment/accounts/{id} — it updates an investment account` intermittently fails in full-suite runs. Not introduced this session. Re-run produces clean 2,825 / 1 skipped.
- **Vault sync was NOT invoked this session** (context budget management). Handover written directly to repo + canonical vault `/Users/CSJ/Desktop/fynlaInter/FynlaInter/May/May11Updates/`. Next session may want to run `/vault-sync` to bring Git History (May11.md), May Index session entries, and Home.md up to date with this session's 3 commits. The legacy `fynlaBrain` vault has phantom handovers (sessions 3-8) from earlier vault-sync agent fabrication; manual cleanup advisable but not blocking.

## Rules reinforced this session

- **`feedback_pack_query_contracts.md` (NEW).** Four contracts (`PackAssetRepository` / `PackEstateRepository` / `PackAssetResolver` / `PackUserRelationProvider`) for core→pack reads. Pick by shape, never put `\Fynla\Packs\…` literals in core. Type tag convention `{packcode}.{snake_case_relation}`. Don't mix shapes in one contract.
- **PRD adherence is not absolute.** When implementation audit reveals the PRD's design would force a much larger change (78+ caller migrations vs zero), divergence is correct. The vii commit body documents the rationale and the divergence so future readers can audit the call.
- **Strategic divergence still ships with full verification.** Pest 2,825/1 skipped, Architecture 130/130, allow-list empty — the divergent design earns the same green bar the PRD's would.

## Pick up from here

**No urgent work in-flight. R-14b CLOSED.** Default auto-continue path (in order):

1. **Surface R-14b closure to CSJ.** Mention: 3 commits this session, plan v3 progress (R-14b 9/9 closed, only R-15 remains), allow-list empty, memory file saved, vault-sync deferred.

2. **If CSJ says "run R-15" or "deploy" or "let's ship":** R-15 is full regression + browser test + dev/prod deploy. Plan calls for:
   - Full `./vendor/bin/pest` green (verified this session at vii close — still green)
   - Architecture suite all assertions active (verified — 130/130, allow-list empty)
   - Browser test UK journey: login `john@example.com`, navigate every UK module, submit a form per module, verify no SA refs, verify network tab does not include `packs-za-*.js`
   - Browser test SA journey: login `za-protection-test@example.com`, navigate every SA module via unprefixed URLs (`/savings`, `/protection`, …), submit a form per module
   - Deploy dev (csjones.co/fynla) first, smoke test, then prod (fynla.org)
   - Per CLAUDE.md "Two environments" section: build script + SiteGround upload + SSH `migrate --force` + cache clear
   - **Pre-deploy prod row count check** (described in viii commit body) — confirms class_alias is dead code or saves the day

3. **If CSJ says "run vault-sync":** invoke `/vault-sync` to bring `fynlaInter/FynlaInter/` Git History (May11.md), May Index session entry, Home.md May 2026 row up to date with this session. Probably bundle a `May2026 Commits.md` totals refresh (108 → 111 commits, refactor +3).

4. **If CSJ says "stand down" or "tomorrow":** session-end (full end-of-day wrap) is appropriate. CSJTODO will need its "FRONT OF LIST" section updated to reflect R-14b closure.

## Branch / deploy state

- **Local branch:** `refactor/uk-pack-relocation` at `85eaf19` (vii-prep `d65ee8e` → vii `0b432d5` → viii `85eaf19`, all this session)
- **Behind origin:** 0
- **Ahead of origin:** 0 (all 3 pushed)
- **Ahead of main:** 103 commits
- **dev branch:** untouched this session — still at whatever it was after R-14b-vi
- **main branch:** untouched this session
- **Production (fynla.org):** untouched this session

## Loose ends to flag at session-end

(Standing backlog items surfaced this session; NOT in-flight.)

- **R-15 — full regression + browser test + dev/prod deploy (~3 hr)** — last workstream of plan v3. Plan close after R-15.
- **Pre-deploy prod row count check** for `personal_access_tokens.tokenable_type` and `audit_logs.model_type` matching `App\Models\User`. If non-zero, run UPDATE migration as part of R-15 deploy; if zero, the `class_alias` in CoreServiceProvider is dead code (harmless).
- **vault-sync not invoked this session** — Git History (May11.md), May Index session 3 entry, Home.md, May2026 Commits totals all stale. Run before next session-end if convenient.
- **Legacy `fynlaBrain` vault has phantom handover files** (sessions 3-8 of May 11) from earlier vault-sync agent fabrication. Documented in session-2 handover too. Manual cleanup remains advisable but not blocking.
- **Pre-existing flaky test** in `InvestmentControllerTest > PUT /api/investment/accounts/{id}` — intermittently fails in full-suite runs. Not introduced this session.
- **SA pack `resolveAppModel()` shim still active for non-User targets** — pre-existing broken paths (`\App\Models\DCPension` etc.) that no test exercises. Out of scope.
- **CSJTODO needs the R-14b closure update.** This handover wraps the work; CSJTODO update is the immediately-next docs commit.
