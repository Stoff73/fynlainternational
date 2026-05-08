---
type: handover
mode: end-of-day
date: 2026-05-09
session: 1
branch: refactor/uk-pack-relocation
previous_session: 2026-05-08 session 10 (R-14a-Tax 4/5 SHIPPED — Tax-i through Tax-iv)
---

# Handover — 2026-05-09, Session 1

## Where we left off

**R-14a-Tax 4/5 SHIPPED — Tax-i through Tax-iv shipped clean across one session, all pushed to `refactor/uk-pack-relocation`.** Branch tip `28c537a`, **77 commits ahead of `main`**, working tree clean. Pest **2,825 passing** maintained throughout, Architecture **130/130** maintained throughout, NoFloatMoneyTest green every commit. The v3-plan-original 14-target list is now down to **3 entries** — UKTaxCalculator + FormatsCurrency + CalculatesOCF. Tomorrow morning picks up cold on R-14a-Tax-v (UKTaxCalculator), the heaviest single sub-batch in the entire R-14a workstream.

## What shipped today (session 10, 2026-05-08)

- `fd8d3d2` — R-14a-Tax-i: TaxActionDefinitionService relocated to `packs/country-gb/src/Tax/`. Internal helpers (`determineMarginalRate`, `determineTaxBand`) ratcheted to `int $grossIncomeMinor` signatures. All 5 evaluators' local money state in pence. Template-rendered `£` display strings render from pence at print time. `estimated_impact` shared cross-pack action key preserved as float pounds (matches EstateActionDefinitionService / SavingsActionDefinitionService pack convention). Allow-list -1.
- `b3665fc` — R-14a-Tax-ii: IncomeDefinitionsService relocated. Full int-minor refactor across `calculate()` + 6 private helpers (`getIncomeComponentsMinor`, `calculateRentalIncomeMinor`, `calculatePensionIncomeMinor`, `getPensionContributionsMinor`, `calculateGiftAidGrossUpMinor`, `calculateAdjustedAllowances(int $aniMinor, int $tiMinor, int $aiMinor)` — the float-money signature that would have tripped NoFloatMoneyTest once the file entered `packs/`). Output keys preserved as float-pounds because three downstream consumers (AnnualAllowanceChecker R-14a-deferred core, IncomeDefinitionsController, CoordinatingAgent AI cache) read them as pounds and aren't ready to migrate. 5 caller imports updated. Allow-list -1.
- `4bfb5b2` — R-14a-Tax-iii: TaxOptimisationService relocated. Three private float-money signatures ratcheted to int-minor (`determineTaxBand`, `buildPensionStrategy`, `buildSpousalStrategy`). `resolveGrossAnnualIncome()` (float pounds via `ResolvesIncome` trait) converted to pence at every call site. Public output keys remain float-pounds (TaxOptimisationAgent → core TaxOptimisationEngine contract → frontend; renaming would break the cross-pack agent contract one service ahead of coordination). 2 caller imports updated (TaxOptimisationAgent in core, test). Allow-list comment for `App\Agents\TaxOptimisationAgent` rewritten to reflect that its dependency now lives in pack. Allow-list -1.
- `28c537a` — R-14a-Tax-iv: TaxBandTracker relocated. All 6 internal money fields (`personalAllowance`, `basicRateLimit`, `higherRateLimit`, `usedPersonalAllowance`, `usedBasicBand`, `usedHigherBand`) refactored to int-minor (pence); the 3 rate fields stay float (rates are dimensionless ratios, not money). `allocateIncome(float $income)` ratcheted to `allocateIncome(int $incomeMinor)`. Public getter / output shapes preserved as float pounds (cross-pack contract for the two R-14a-deferred callers — UKTaxCalculator in Tax-v scope + RetirementIncomeService). Tax computed via `(int) round($bandUsedMinor * $rate)` (Option A rounding — total-preserving). Two caller sites converted pounds → pence at the `allocateIncome` boundary; UKTaxCalculator + RetirementIncomeService both got import updates. Allow-list -1.

## What's in flight (NOT done)

- **R-14a-Tax-v — UKTaxCalculator** (deferred to next session, ~2-3 hr alone per session 9 handover):
  - File: `app/Services/UKTaxCalculator.php` (847 lines)
  - 14 callers (5 core R-14a-deferred services + 5 pack services + 1 trait + 4 test files + 2 architecture-test references)
  - ~10 float-money method signatures need ratcheting:
    - `calculateDetailedNetIncome(float $employmentIncome, float $selfEmploymentIncome, float $rentalIncome, float $pensionIncome, float $trustIncome, float $interestIncome, float $dividendIncome, ?string $trustType, float $pensionContributions, float $section24Credit)`
    - `calculateNetIncome(float $employmentIncome, float $selfEmploymentIncome, float $rentalIncome, float $dividendIncome, float $interestIncome, float $otherIncome)`
    - `calculateClass1NIDetailed(float $employmentIncome)`
    - `calculateClass4NIDetailed(float $selfEmploymentIncome)`
    - `calculateInterestTaxDetailed(float $interestIncome, TaxBandTracker $tracker)`
    - `calculateDividendTaxDetailed(float $dividendIncome, TaxBandTracker $tracker)`
    - `calculateTrustIncomeTax(float $trustIncome, ?string $trustType, TaxBandTracker $tracker)`
    - `calculateIncomeTax(float $nonDividendNonInterestIncome, float $interestIncome, float $dividendIncome): float`
    - `calculateClass1NI(float $employmentIncome): float`
    - `calculateClass4NI(float $selfEmploymentIncome): float`
  - The two `allocateIncome` call sites in UKTaxCalculator (lines 81, 343) were already updated this session as part of Tax-iv — they pass `(int) round($x * 100)` correctly; that work carries forward unchanged.
  - Output keys: ~30 money-shape keys across `calculateDetailedNetIncome` and `calculateNetIncome` outputs. **Decision needed at kickoff:** rename to `*_minor` or preserve float-pounds. Convention so far in R-14a-Tax has been preserve float-pounds (cross-pack contract + R-14a-deferred peer reads). Same call here likely — UKTaxCalculator output flows into ResolvesIncome trait, several pack services, and test assertions.
  - The pre-existing allow-list comment for `App\Services\UKTaxCalculator` ("stays put — float-money signatures (ADR-005) prevent moving into pack scope until int-minor refactor") will need updating when the entry is removed.

- **R-14a-Traits — final R-14a sub-batch** (after Tax-v closes):
  - `App\Traits\FormatsCurrency` — 12 callers across pack code; touches every formatted currency output
  - `App\Traits\CalculatesOCF` — 2 callers
  - **Consider extracting the recursive `convertMinorKeysToPoundsRecursive` walker** (currently duplicated in 5 sites: GiftingController, ComprehensiveEstatePlanService, EstateAgent, IHTController, EstatePlanService) into FormatsCurrency at this point. Convergence is strong; the trait is the natural home.

- **R-14b** — container query layer + 6 deferred core models (User, Household, Goal, GoalContribution, LifeEvent, LifeEventAllocation), ~5 hr provisional.

- **R-15** — full regression sweep, browser-tested across all preview personas, deploy to dev csjones.co/fynla then prod fynla.org, ~3 hr provisional.

- **Plan budget remaining:** ~10 hr (Tax-v ~2-3 hr + Traits ~2 hr + R-14b ~5 hr + R-15 ~3 hr ≈ ~12 hr; the 4 Tax sub-batches this session were lighter than the v3-plan provisional, leaving buffer).

## Deploy status

**Nothing to deploy** — refactor branch only. Production fynla.org and dev csjones.co/fynla are still at session 7's deploy state (`3c47e2a` and `2153fb2` respectively per the session 7 handover). R-14a is a long-running refactor branch that won't deploy until R-15 closes the workstream.

## Tech debt found this session

- **0 critical, 0 warnings, 0 suggestions.** Pure mechanical int-minor refactor across 4 services — no new domain logic, no convention drift, no new debt. Skipped heavyweight `tech-debt-session` invocation per the convention crystallised across sessions 6, 7, 8, 9 for similar mechanical work.
- The `poundsToMinor` helper duplication across 4 Tax services (Tax-i, Tax-ii, Tax-iii, Tax-iv) plus `minorToPounds` in 2 of them (Tax-ii, Tax-iv) is **expected pre-extraction state** by design, not debt. Will be addressed in R-14a-Traits when FormatsCurrency relocates and absorbs both helpers + the recursive walker.

## Known issues / blockers

- **None.** No conflict markers. No pending migrations. No dirty worktrees. Both dev servers running on the correct ports (PHP 8001, Vite 5174 — confirmed at session start; hot-file points to 5174 correctly).
- **One known flaky test, NOT R-14a-related** (carried from session 7): `Tests\Unit\Agents\SavingsAgentGoalsTest > recommends increasing contributions for behind-schedule savings goal` at `tests/Unit/Agents/SavingsAgentGoalsTest.php:54`. Confirmed flaky: passes 3/3 in isolation; fails ~50% of the time in full suite. Likely cause: clock-dependent assertion. Did not fire this session. Optional ~30 min pre-task before R-14a-Tax-v starts; not blocking.

## Rules reinforced this session

- **No new memories needed.** Vault-sync subagent confirmed the int-minor pattern is already captured across 4 commit messages (Tax-i through Tax-iv) + git-history entries in May08.md; the recursive walker pattern is documented from session 8/9; helper-methods-at-bottom is established convention across 11 sub-batches now (7 Estate + 4 Tax). No new feedback this session that wasn't already in the existing 5 memory files.

## Next session should

1. **Map UKTaxCalculator surface** (~15 min) — re-read `app/Services/UKTaxCalculator.php` (847 lines), confirm the 10 float-money method signatures, count the ~30 output keys consumed by callers, and re-grep all 14 caller sites to plan the boundary conversion strategy. Sample callers to verify output-key consumption patterns: `app/Services/Investment/Recommendation/UserContextBuilder.php` (FQCN reference), `app/Services/Retirement/RetirementStrategyService.php`, `app/Services/Protection/CoverageGapAnalyzer.php`, `app/Services/UserProfile/PersonalAccountsService.php`, `app/Services/UserProfile/UserProfileService.php`, `packs/country-gb/src/Investment/AssetLocation/TaxDragCalculator.php`, `packs/country-gb/src/Goals/GoalAffordabilityService.php`, `packs/country-gb/src/Goals/GoalsProjectionService.php`, `packs/country-gb/src/Goals/FinancialForecastService.php`, `packs/country-gb/src/Traits/ResolvesIncome.php`. **Decide at kickoff whether to rename output keys to `*_minor` or preserve float-pounds** — strong default is preserve float-pounds (matches Tax-i through Tax-iv convention; the 4 R-14a-deferred core peers + ResolvesIncome trait read pounds today).
2. **Ship R-14a-Tax-v** — relocate `UKTaxCalculator.php` → `packs/country-gb/src/Tax/UKTaxCalculator.php`, namespace `Fynla\Packs\Gb\Tax`. Refactor the 10 float-money signatures to `int $xMinor`. Convert internal arithmetic to pence. Add `poundsToMinor` (and likely `minorToPounds`) helpers at the file bottom. Update 14 caller sites with `(int) round($x * 100)` boundary conversion at every UKTaxCalculator method invocation. Update 4 test files (`tests/Unit/Services/UserProfileServiceTest.php`, `tests/Unit/Services/Protection/CoverageGapAnalyzerTest.php`, `tests/Unit/Services/UserProfile/FinancialCommitmentsTest.php`, plus the existing PackIsolationTest comment block). Verify Phase02ArchitectureTest still passes (it has 2 references — likely just narrative). PackIsolationTest allow-list -1: remove `'App\\Services\\UKTaxCalculator',` AND the surrounding "stays put" comment block (lines 251–253 currently). `composer dump-autoload` → arch + Tax + Retirement + UserProfile + Protection + full Pest. Commit + push as `refactor(uk-pack): R-14a-Tax-v — int-minor money for UKTaxCalculator`.
3. **Then R-14a-Traits closes R-14a:**
   - `R-14a-Traits-i: FormatsCurrency` (12 callers — touches every formatted currency output) — relocate `app/Traits/FormatsCurrency.php` → `packs/country-gb/src/Traits/FormatsCurrency.php`. Consider extracting `convertMinorKeysToPoundsRecursive` here (5+ sites of convergence).
   - `R-14a-Traits-ii: CalculatesOCF` (2 callers).
4. **After R-14a closes:** R-14b container query layer + 6 deferred core models (~5 hr), then R-15 full regression + dev/prod deploy (~3 hr). **~10 hr total to plan close.**

**DO NOT** start R-14a-Tax-v with the heaviest signature first (`calculateDetailedNetIncome` — 10 float-money params). Start with the smaller helpers (`calculateClass1NIDetailed`, `calculateInterestTaxDetailed`) to re-warm the pattern, then work up. Pattern is now fully crystallised over 11 sub-batches — no surprises expected.

## Context hints

- Active branch type: refactor (long-running)
- Behind origin/main by: 0 commits ahead, branch is 77 ahead of `main` (was 72 at session 9 close + 4 this session + 1 docs commit from this session-end at the end of Phase 10)
- Uncommitted: none, working tree clean
- Last commit: `28c537a` refactor(uk-pack): R-14a-Tax-iv — int-minor money for TaxBandTracker (after Phase 10 docs commit: this handover + CSJTODO update)
- Origin sync: clean — all pushed
- Dev servers: PHP on `:8001` (PID 6995, fynlaInternational), Vite on `:5174` (PID 7138, fynlaInternational). Ports 8000 + 5173 are the **legacy `/Users/CSJ/Desktop/fynla` UK-only repo** — ignore them. Always `ps aux | grep artisan` before browser testing if there's any doubt.
- Vault root for fynlaInternational = `/Users/CSJ/Desktop/fynlaInter/FynlaInter/` (NOT `fynlaBrain` — that's the legacy project's vault). The session-end and session-start skill texts still reference the legacy path; project memory has the override.
- Test baseline: Pest **2,825 passing / 1 skipped / 0 failing** (1 known flake unrelated to R-14a). Architecture **130/130**. NoFloatMoneyTest green throughout.

## What the next Claude needs to know

1. **R-14a-Tax pattern is fully crystallised.** Don't reinvent it. Read either Tax-ii (`b3665fc` — IncomeDefinitionsService — full int-minor with output preservation, the canonical reference for medium-heavy services) or Tax-iv (`28c537a` — TaxBandTracker — full int-minor on a stateful class) commit diff to see the template. Apply mechanically to UKTaxCalculator.

2. **Output-key preservation is the default.** All 4 Tax sub-batches this session preserved float-pounds output keys because R-14a-deferred peers (AnnualAllowanceChecker, UKTaxCalculator, RetirementIncomeService, etc.) still read them that way. UKTaxCalculator's output keys feed ResolvesIncome trait, multiple pack services, and 4 test files — preserve float-pounds. The full pence-shape contract migration is an R-14a tail / R-14a-2 batch that touches all R-14a-deferred peers in lockstep.

3. **Helper duplication is expected.** Each of the 4 Tax services has its own `private static poundsToMinor` (Tax-ii and Tax-iv also have `minorToPounds`). UKTaxCalculator will add a 5th. R-14a-Traits then absorbs all 5 + the recursive walker into FormatsCurrency. Don't pre-extract.

4. **R-14a scope is the v3-plan-original 14 files only.** This session shipped Tax-i through Tax-iv (4 files), bringing the list from 6 to 3. After Tax-v + 2 Traits, R-14a closes. The 30 R-9-era PackIsolationTest deferrals stay in the allow-list with R-14a comments and address in R-14a-2 / R-14b — NOT this session, NOT next session.

5. **NoFloatMoneyTest scope** — only flags `function ... (float $<money_param>)` parameter signatures. It does NOT flag float return types, internal float locals, internal float fields, or float-money output array keys. So the minimum bar to pass when relocating into `packs/` is just refactoring float-money param signatures to int-minor; everything else is a quality choice within the int-minor convention.

6. **Boundary-conversion pattern proven across 4 callers updated this session** — at every `pack-resident TaxBandTracker::allocateIncome` call site (UKTaxCalculator x2, RetirementIncomeService x1) and at every `Fynla\Packs\Gb\Tax\IncomeDefinitionsService::calculate` consumer (AnnualAllowanceChecker x1, IncomeDefinitionsController x1, CoordinatingAgent FQCN x1) the imports were updated and the boundary contract preserved. Same shape for UKTaxCalculator's 14 callers next session: import update + `(int) round($x * 100)` at every method-call boundary that has a money param.

7. **CLAUDE.md metric drift continues** (per sessions 6/7/8/9/10) — Vue Components 713 → 382 actual core, PHP Services 240 → 129 core, Controllers 99 → 51 core, Models 94 → 6 core, Agents 9 → 2 core, GB Pack Vue 287, GB Pack Services ~80 (Calculator/Analyzer/Service-suffixed), GB Pack Models 53. Vault-sync flagged but did not auto-fix. Re-baseline at R-15.

8. **`UKTaxes.md` Current State doc may be stale** relative to int-minor refactor (vault-sync flagged this in Phase 9). Not blocking — informational. Check if the doc references float-money signatures of Tax-i through Tax-iv services and update if so. Vault path: `/Users/CSJ/Desktop/fynlaInter/FynlaInter/Current State/UKTaxes.md` (or wherever the vault organises Current State).

## Branch / deploy state

- **Branch:** `refactor/uk-pack-relocation` at `28c537a`, **77 commits ahead of `main`** (after Phase 10 docs commit: `+1` more = 78), all pushed.
- **Origin sync:** clean — `0 0` with `origin/refactor/uk-pack-relocation` (post-Phase 10 push).
- **Deploy state:** Refactor branch only. NOTHING DEPLOYED THIS SESSION. fynla.org production and csjones.co/fynla dev untouched (still at session 7's deploy state — `3c47e2a` and `2153fb2` respectively per session 7 handover). R-14a is a long-running refactor branch that won't deploy until R-15 closes the workstream.

## Untracked carry-over (intentional, NOT introduced this session or any prior R-14a session)

- `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCAsuperchargeApp.md`, `FCA/`
- `Fynla-Narrative-Memo-Template.docx`
- `May/May1Updates/deployFynFix.md`
- `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`
- (Carry-over outstanding from session 2 — decision still deferred. Sessions 7, 8, 9, and 10 have NOT touched this carry-over and will not absorb it during R-14a — it's a separate cleanup workstream gated on UI/marketing decisions outside R-14a's scope.)
