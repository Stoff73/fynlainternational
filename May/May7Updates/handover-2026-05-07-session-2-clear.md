---
type: handover
mode: context-clear
date: 2026-05-07
session: 2
branch: refactor/uk-pack-relocation
previous_session: 2026-05-07-session-1 (end-of-day)
---

# Context Clear Handover — 2026-05-07, Session 2

## Immediate state

R-6a + R-6b are SHIPPED. Five commits pushed to `origin/refactor/uk-pack-relocation` (tip `87a77ba`). Working tree clean. Pest 2,788 passing, 0 failing, 1 skipped throughout. Architecture suite 123 passing throughout. Branch is **21 commits ahead of `main`**. Next R-6 sub-workstream is **R-6c Protection** (5 clean / 3 deferred).

## The thread

- Picked up R-6a per session-1 handover. Mechanical relocation pattern from R-3/R-4/R-5: `git mv` → namespace rewrite → explicit `use` for cross-boundary peers → sed bulk-update of caller imports → `composer dump-autoload` → architecture suite → full Pest → commit.
- R-6a (5 Retirement services) shipped first as a single commit. Discovered the symmetric problem: 3 deferred services in `app/Services/Retirement/` had bare same-namespace refs to relocated `RequiredCapitalCalculator` — fixed with explicit `use Fynla\Packs\Gb\Retirement\…` in each. Pattern documented for R-6b.
- R-6b split into 4 sub-commits per the session-1 plan (`R-6b-i` top-level, `R-6b-ii` Analytics + AssetLocation, `R-6b-iii` ModelPortfolio + Performance + Rebalancing, `R-6b-iv` Recommendation + Tax + Utilities). Each sub-commit ran the architecture suite + full Pest before commit; both green every time. 37 clean Investment services relocated; 19 R-14a-deferred services remain in `app/Services/Investment/`.
- Provider bindings updated: `pack.gb.retirement` → `Fynla\Packs\Gb\Retirement\UkRetirementEngine` (R-6a), `pack.gb.investment` → `Fynla\Packs\Gb\Investment\UkInvestmentEngine` (R-6b-i).

## What shipped today (session 2)

- `34556e3` — `refactor(uk-pack): R-6a — relocate 5 clean UK Retirement services to GB pack`
- `0aeba5e` — `refactor(uk-pack): R-6b-i — relocate 10 clean top-level Investment services to GB pack`
- `1449325` — `refactor(uk-pack): R-6b-ii — relocate Investment Analytics + AssetLocation services to GB pack`
- `bda56b4` — `refactor(uk-pack): R-6b-iii — relocate Investment ModelPortfolio + Performance + Rebalancing services to GB pack`
- `87a77ba` — `refactor(uk-pack): R-6b-iv — relocate Investment Recommendation + Tax + Utilities services to GB pack`

Total: 85 files changed (5 commits, all pushed).

## Files touched this session

### Relocated to GB pack (42 files)

**Retirement (5)** — `packs/country-gb/src/Retirement/`:
PensionPortfolioAnalyzer, RequiredCapitalCalculator, RetirementActionDefinitionService, RetirementDataReadinessService, UkRetirementEngine.

**Investment top-level (10)** — `packs/country-gb/src/Investment/`:
ContributionEstimatorService, DiversificationAnalyzer, EmployeeSchemeCalculationService, InvestmentActionDefinitionService, MonteCarloSimulator, PortfolioStrategyService, ReturnCalculationService, ScenarioService, SimpleAssetAllocationOptimizer, UkInvestmentEngine.

**Investment Analytics (6)** — `Analytics/`:
CorrelationMatrixCalculator, CovarianceMatrixCalculator, EfficientFrontierCalculator, HoldingsDataExtractor, MarkowitzOptimizer, PortfolioStatisticsCalculator.

**Investment AssetLocation (2)** — `AssetLocation/`:
AccountTypeRecommender, TaxDragCalculator.

**Investment ModelPortfolio (2)** — `ModelPortfolio/`:
FundSelector, ModelPortfolioBuilder.

**Investment Performance (2)** — `Performance/`:
AlphaBetaCalculator, BenchmarkComparator.

**Investment Rebalancing (4)** — `Rebalancing/`:
DriftAnalyzer, RebalancingCalculator, RebalancingStrategyService, TaxAwareRebalancer.

**Investment Recommendation (8)** — `Recommendation/`:
ConflictResolutionService, ContributionWaterfallService, DataReadinessService, GoalAssessmentService, RecommendationOutputFormatter, SafetyCheckService, SpouseOptimisationService, TransferRecommendationService.

**Investment Tax (1)** — `Tax/`:
CGTHarvestingCalculator.

**Investment Utilities (2)** — `Utilities/`:
MatrixOperations, StatisticalFunctions.

### Deferred — kept in `app/Services/`

**Retirement (8)**: AnnualAllowanceChecker, DecumulationPlanner, PensionContributionOptimizer, PensionProjector, RetirementIncomeService, RetirementProjectionService, RetirementStrategyService, SalarySacrificeAnalyzer. All R-14a (float-money signatures, ADR-005).

**Investment (19)**: ContributionOptimizer, DividendTaxCalculator, FeeAnalyzer, InvestmentProjectionService, PortfolioAnalyzer, TaxEfficiencyCalculator (top-level); AssetLocation/AssetLocationOptimizer; Fees/{OCFImpactCalculator, PlatformComparator}; Goals/{GoalProbabilityCalculator, GoalProgressAnalyzer, ShortfallAnalyzer}; ModelPortfolio/AssetAllocationOptimizer; Performance/PerformanceAttributionAnalyzer; Recommendation/{LifeEventAssessmentService, UserContextBuilder}; Tax/{BedAndISACalculator, ISAAllowanceOptimizer, TaxOptimizationAnalyzer}. All R-14a.

### Cross-boundary import additions (deferred peers reference relocated classes)

- `app/Services/Retirement/RetirementIncomeService.php` — `use Fynla\Packs\Gb\Retirement\RequiredCapitalCalculator;`
- `app/Services/Retirement/RetirementProjectionService.php` — same + `use Fynla\Packs\Gb\Investment\MonteCarloSimulator;`
- `app/Services/Retirement/RetirementStrategyService.php` — same as Income
- `app/Services/Investment/InvestmentProjectionService.php` — `use Fynla\Packs\Gb\Investment\{MonteCarloSimulator, ContributionEstimatorService};`
- `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` — `use Fynla\Packs\Gb\Investment\AssetLocation\{AccountTypeRecommender, TaxDragCalculator};`
- `app/Services/Investment/ModelPortfolio/AssetAllocationOptimizer.php` — `use Fynla\Packs\Gb\Investment\ModelPortfolio\ModelPortfolioBuilder;`
- `app/Services/Investment/Performance/PerformanceAttributionAnalyzer.php` — `use Fynla\Packs\Gb\Investment\Performance\{AlphaBetaCalculator, BenchmarkComparator};`

### Symmetric pack→app imports (R-14a deferred peers)

- `RetirementActionDefinitionService` (in pack) — `use App\Services\Retirement\{DecumulationPlanner, PensionContributionOptimizer, SalarySacrificeAnalyzer};`
- `PortfolioStrategyService` + `InvestmentActionDefinitionService` (in pack) — `use App\Services\Investment\FeeAnalyzer;`
- `TransferRecommendationService` (in pack) — `use App\Services\Investment\Tax\BedAndISACalculator;`

### Architecture suite changes

- Exempt-dir list extended: `Estate, Tax, Retirement, Investment` are now all tolerated (was just `Constants, Traits, Models, Estate, Tax`).
- Allow-list refactored:
  - 7 Retirement R-14a markers + 19 Investment R-14a markers (26 total `// R-14a` tags).
  - 3 R-7 cross-module entries: `Plans\PlanConfigService`, `Shared\MonteCarloEngine`, `UserProfile\UserProfileService`.
  - 1 R-9-related entry: `App\Jobs\RunMonteCarloSimulation`.
  - Removed: 3 entries that R-6b relocated (`EmployeeSchemeCalculationService`, `DiversificationAnalyzer`, `SimpleAssetAllocationOptimizer`); 2 cross-subworkstream temporaries (`Rebalancing\DriftAnalyzer`, `Utilities\MatrixOperations`, `Utilities\StatisticalFunctions`).
- Contract impl tests repointed to pack: `UkRetirementEngine` → `Fynla\Packs\Gb\Retirement\UkRetirementEngine`; `UkInvestmentEngine` → `Fynla\Packs\Gb\Investment\UkInvestmentEngine`.

## What the next Claude needs to know

- **The bidirectional import pattern is the load-bearing piece.** Every R-N relocation in this branch needs to handle it: pack code with bare refs to deferred `App\` peers → add `use App\Services\…;` lines; deferred `app/` services with bare refs to relocated pack classes → add `use Fynla\Packs\Gb\…;` lines. The architecture allow-list pins the cross-pack imports either way. Forgetting one direction breaks tests with `TypeError` on constructor type hints (Mockery instances of one namespace failing to satisfy bare same-namespace type hints in the other). I hit this on R-6a; symmetric fix-up is mandatory.
- **Sed bulk-update of caller imports** is the right pattern, but iterate per-class (not per-file). The first attempt used `for caller in $callers; do for cls in ...` which corrupted `$caller`; rewriting as `for cls in ...; do files=$(grep -rln); while read f; do sed; done <<< "$files"` works reliably.
- **CSJTODO.md is gitignored** — repo-local copy is canonical (vault copy is stale at May 1).
- **No deploys.** Pure refactor — no functional change. Production / dev are unaffected.
- **`R-14a` markers on allow-list entries are deliberate** — the original session-5 decision pinned these as belonging to a specific future workstream (int-minor money refactor, ~6 hr). When R-14a runs, those entries close; they are NOT R-15 leftovers.
- The historical-context docblock in `packs/country-gb/src/Investment/Analytics/EfficientFrontierCalculator.php:18` still mentions `App\Services\Investment\Analytics\EfficientFrontierCalculator` as part of a "moved from" note. Harmless — file is in an exempt dir and the App\ string is in a comment, not a `use` line. Can be cleaned up at leisure.

## Pick up from here

**Start R-6c Protection.** Per session-1 handover, scope is 5 clean + 3 deferred:

Clean (move to `packs/country-gb/src/Protection/`):
- `app/Services/Protection/AdequacyScorer.php`
- `app/Services/Protection/ProtectionDataReadinessService.php`
- `app/Services/Protection/RecommendationEngine.php`
- `app/Services/Protection/ScenarioBuilder.php`
- `app/Services/Protection/UkProtectionEngine.php`

Deferred (R-14a markers — float-money):
- `app/Services/Protection/ComprehensiveProtectionPlanService.php`
- `app/Services/Protection/CoverageGapAnalyzer.php`
- `app/Services/Protection/ProtectionActionDefinitionService.php`

Procedure (well-rehearsed at this point):
1. Inspect imports and bare same-namespace cross-refs in all 8 files.
2. `git mv` 5 clean files; rewrite namespace to `Fynla\Packs\Gb\Protection\`.
3. Add explicit `use App\Services\Protection\X;` for any deferred peer the relocated files reference bare.
4. Update `pack.gb.protection` binding in `packs/country-gb/src/Providers/GbPackServiceProvider.php` from `\App\Services\Protection\UkProtectionEngine` to `\Fynla\Packs\Gb\Protection\UkProtectionEngine`.
5. Add `use Fynla\Packs\Gb\Protection\X;` to the 3 deferred services where they have bare same-namespace refs to the moved classes.
6. Sed bulk-update of caller imports (per-class iteration).
7. Update `tests/Architecture/PackIsolationTest.php`:
   - Add `Protection` to exempt-dir list.
   - Add `Protection` to target-dir list (third assertion).
   - Add 3 R-14a-marked allow-list entries for the deferred Protection services.
   - Repoint `UkProtectionEngine` contract test to `Fynla\Packs\Gb\Protection\UkProtectionEngine`.
8. `composer dump-autoload`.
9. `./vendor/bin/pest --testsuite=Architecture` (expect 123 passing).
10. `./vendor/bin/pest` (expect 2,788 passing).
11. Commit as `refactor(uk-pack): R-6c — relocate 5 clean UK Protection services to GB pack`.

Then **R-6d Savings** (9 clean / 1 deferred — smallest of the four), closing R-6.

## Current state references

- **Active branch:** `refactor/uk-pack-relocation` at `87a77ba`, **21 commits ahead of `main` (`d8bd867`)**, all pushed.
- **Pest:** 2,788 passing, 1 skipped, 0 failing.
- **Architecture suite:** 123 passing, 240 assertions.
- **Allow-list watch:** `tests/Architecture/PackIsolationTest.php` has accumulated entries from R-3 + R-4 + R-5 + R-6a + R-6b. R-6c + R-6d will add ~4 more R-14a-tagged Protection/Savings entries. R-7 → R-9 continues accumulation. R-14a then closes ~30 of them; R-14b closes the 6 core-model entries; R-15 verifies empty.
- **Plan budget:** ~61 hr total. Through R-6b: ~16 hr shipped. Remaining: ~45 hr (R-6c → R-15 + R-14a + R-14b).
- **CSJTODO.md sequence:** updated locally to mark R-6a + R-6b complete; R-6c next.
