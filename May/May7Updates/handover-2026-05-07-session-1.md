---
type: handover
mode: end-of-day
date: 2026-05-07
session: 1
branch: refactor/uk-pack-relocation
previous_session: 2026-05-06-session-5 (end-of-day)
---

# Handover — 2026-05-07, Session 1

## Where we left off

Session 5 was a planning session, not a coding session. CSJ locked the deferral decision: R-14a (ADR-005 int-minor money refactor + relocation, ~6 hr) and R-14b (container-resolved query layer + relocation of 6 deferred core models, ~5 hr) are now explicit workstreams between R-14 and R-15. Plan amended at `Plans/architecture-plan-v3.md` § 1, § G, new § 16a/§ 16b, § 17. Total budget grew from ~50 hr to ~61 hr.

R-6 was started: inventory + float-money scan complete; no files moved. Next session should begin R-6a (Retirement) with the categorised file lists captured below.

## What shipped today (session 5)

- `ee5b723` — `docs(architecture): schedule R-14a + R-14b explicit workstreams before R-15` (+177/-28 lines on `Plans/architecture-plan-v3.md`)
- `CSJTODO.md` updated locally (gitignored) with the locked decision, sequence string updated to 18 workstreams, total budget ~61 hr / ~49.5 hr remaining

## What's in flight (NOT done)

**R-6 starts here next session.** Inventory + float-money scan are done; no relocations have happened.

### R-6 categorised file lists (use these directly — do NOT re-scan)

**Retirement** (13 files total, 5 clean to move, 8 to defer)

Clean (move into `packs/country-gb/src/Retirement/`):
- `app/Services/Retirement/PensionPortfolioAnalyzer.php`
- `app/Services/Retirement/RequiredCapitalCalculator.php`
- `app/Services/Retirement/RetirementActionDefinitionService.php`
- `app/Services/Retirement/RetirementDataReadinessService.php`
- `app/Services/Retirement/UkRetirementEngine.php`

Deferred (R-14a allow-list entries — tag with R-14a marker):
- `app/Services/Retirement/AnnualAllowanceChecker.php`
- `app/Services/Retirement/DecumulationPlanner.php`
- `app/Services/Retirement/PensionContributionOptimizer.php`
- `app/Services/Retirement/PensionProjector.php`
- `app/Services/Retirement/RetirementIncomeService.php`
- `app/Services/Retirement/RetirementProjectionService.php`
- `app/Services/Retirement/RetirementStrategyService.php`
- `app/Services/Retirement/SalarySacrificeAnalyzer.php`

**Investment** (56 files total — plan said 16; subdirectories were under-counted. 38 clean to move, 18 to defer)

Deferred (R-14a):
- `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php`
- `app/Services/Investment/ContributionOptimizer.php`
- `app/Services/Investment/DividendTaxCalculator.php`
- `app/Services/Investment/FeeAnalyzer.php` (11 hits — most-affected single file in R-6)
- `app/Services/Investment/Fees/OCFImpactCalculator.php`
- `app/Services/Investment/Fees/PlatformComparator.php`
- `app/Services/Investment/Goals/GoalProbabilityCalculator.php`
- `app/Services/Investment/Goals/GoalProgressAnalyzer.php`
- `app/Services/Investment/Goals/ShortfallAnalyzer.php`
- `app/Services/Investment/InvestmentProjectionService.php`
- `app/Services/Investment/ModelPortfolio/AssetAllocationOptimizer.php`
- `app/Services/Investment/Performance/PerformanceAttributionAnalyzer.php`
- `app/Services/Investment/PortfolioAnalyzer.php`
- `app/Services/Investment/Recommendation/LifeEventAssessmentService.php` (11 hits)
- `app/Services/Investment/Recommendation/UserContextBuilder.php`
- `app/Services/Investment/Tax/BedAndISACalculator.php`
- `app/Services/Investment/Tax/ISAAllowanceOptimizer.php`
- `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php`
- `app/Services/Investment/TaxEfficiencyCalculator.php`

Clean (38 — re-derive at kickoff): `git ls-files 'app/Services/Investment/*.php' 'app/Services/Investment/**/*.php'` minus the 18 above.

**Protection** (8 files total, 5 clean, 3 to defer)

Clean:
- `app/Services/Protection/AdequacyScorer.php`
- `app/Services/Protection/ProtectionDataReadinessService.php`
- `app/Services/Protection/RecommendationEngine.php`
- `app/Services/Protection/ScenarioBuilder.php`
- `app/Services/Protection/UkProtectionEngine.php`

Deferred (R-14a):
- `app/Services/Protection/ComprehensiveProtectionPlanService.php`
- `app/Services/Protection/CoverageGapAnalyzer.php`
- `app/Services/Protection/ProtectionActionDefinitionService.php`

**Savings** (10 files total, 9 clean, 1 to defer)

Clean:
- `app/Services/Savings/EmergencyFundCalculator.php`
- `app/Services/Savings/FSCSAssessor.php`
- `app/Services/Savings/GoalProgressCalculator.php`
- `app/Services/Savings/LiquidityAnalyzer.php`
- `app/Services/Savings/PSACalculator.php`
- `app/Services/Savings/RateComparator.php`
- `app/Services/Savings/SavingsActionDefinitionService.php`
- `app/Services/Savings/SavingsDataReadinessService.php`
- `app/Services/Savings/UkSavingsEngine.php`

Deferred (R-14a):
- `app/Services/Savings/ISATracker.php`

### R-6 scope summary

| Module | Total | Move | Defer to R-14a |
|---|---|---|---|
| Retirement | 13 | 5 | 8 |
| Investment | 56 | 38 | 18 |
| Protection | 8 | 5 | 3 |
| Savings | 10 | 9 | 1 |
| **Total** | **87** | **57** | **30** |

The plan's 4.5 hr R-6 estimate is going to be ~2× short — the Investment subdirectory tree was significantly under-counted in the plan, and 30 deferral entries means heavy allow-list churn. Realistic R-6 budget: 8–10 hr in batches.

## Deploy status

**Nothing to deploy.** Session 5 was pure documentation — `Plans/architecture-plan-v3.md` only. No PHP, no Vue, no migrations, no seeders. No deploy notes generated.

## Tech debt found this session

None. Pure docs change. No code changed. Tech-debt audit deferred until R-6 lands actual code.

## Known issues / blockers

None. Working tree clean, branch pushed, Pest still 2,788 passing from session 4 (no tests run this session).

## Rules reinforced this session

None new. Existing `feedback_follow_handover_dont_re_ask.md` was followed — when CSJ confirmed the deferral decision, I didn't re-surface the decision, just executed against it.

## Next session should

1. **Start R-6a Retirement.** Move the 5 clean Retirement files into `packs/country-gb/src/Retirement/`. Standard relocation procedure: namespace update (`Fynla\Packs\Gb\Retirement\`) + `use` statements added to caller files + sed of bare class refs. Pattern is now well-rehearsed (R-3/R-4/R-5).

2. **Update `pack.gb.retirement` binding.** Currently in `packs/country-gb/src/Providers/GbPackServiceProvider.php` it points at `\App\Agents\RetirementAgent::class` — leave that alone (Agent moves in R-8); but if `UkRetirementEngine` becomes the engine binding, repoint accordingly.

3. **Add 8 R-14a allow-list entries to `tests/Architecture/PackIsolationTest.php`** for the deferred Retirement files. Tag each with an R-14a marker comment, not R-15. The handover-line:

   > *Per session-5 decision: deferral allow-list entries for float-money services tag with `// R-14a` markers; for cross-pack core-model relationships tag with `// R-14b` markers. R-15 markers are reserved for entries R-14a/R-14b can't cover.*

4. **Run `./vendor/bin/pest --testsuite=Architecture` after R-6a.** Architecture suite was 123 passing at session-4 close; new allow-list entries shouldn't break it.

5. **Then R-6b Investment** — by far the largest batch (38 clean files, 18 deferred). Consider splitting into R-6b-i (Investment top-level), R-6b-ii (Analytics + AssetLocation), R-6b-iii (ModelPortfolio + Performance + Rebalancing), R-6b-iv (Recommendation + Tax + Utilities) so each commit is reviewable.

6. **Then R-6c Protection (5 clean, 3 deferred), then R-6d Savings (9 clean, 1 deferred).** Each is a smaller batch.

7. **Strong suggestion before starting R-6a**: run `tech-debt-session` against the R-3/R-4/R-5 changes from sessions 3+4 — cheap insurance that those mechanical relocations didn't introduce subtle convention drift. Skip if time-pressed.

8. **Full Pest after R-6 closes.** Target: 2,788+ passing (no tests should regress; some allow-listed assertions may need ratcheting).

## Context hints

- **Active branch:** `refactor/uk-pack-relocation` — at `ee5b723`, **15 commits ahead of `main`** (`d8bd867`), all pushed
- **Uncommitted:** none, working tree clean (`CSJTODO.md` is gitignored, so its session-5 edits stay local — that's expected)
- **Last commit:** `ee5b723 docs(architecture): schedule R-14a + R-14b explicit workstreams before R-15`
- **Pest:** 2,788 passing, 1 skipped, 0 failed (last run end of session 4)
- **Architecture suite:** 123 passing (last run end of session 4)
- **Active workstream:** R-6 in progress — inventory + scan done, R-6a Retirement next
- **Plan budget:** ~61 hr total, ~49.5 hr remaining (R-6 → R-14b + R-15)
- **Allow-list watch:** PackIsolationTest currently has ~25 entries from R-3/R-4/R-5; R-6 will add 30 more; expect ~55 entries by R-6 close. R-7 → R-9 will continue accumulating. R-14a then closes ~32-44 of them; R-14b closes the 6 core-model entries; R-15 verifies empty list.

## Files touched this session

- `Plans/architecture-plan-v3.md` — committed in `ee5b723`
- `CSJTODO.md` — local-only (gitignored)
- This handover (committed in session-end Phase 10)
