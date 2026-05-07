---
type: handover
mode: context-clear
date: 2026-05-07
session: 3
branch: refactor/uk-pack-relocation
previous_session: 2026-05-07-session-2 (context-clear)
---

# Context Clear Handover — 2026-05-07, Session 3

## Immediate state

R-6 + R-7 are SHIPPED. Five commits pushed to `origin/refactor/uk-pack-relocation` (tip `c74fa2c`). Working tree clean. Pest 2,788 passing, 0 failing, 1 skipped throughout. Architecture suite 123 passing throughout. Branch is **27 commits ahead of `main`**. Next workstream is **R-8** (~1.5 hr — relocate 7 module agents; CoordinatingAgent moves with them per § E of plan).

## The thread

- Picked up R-6c per session-2 handover. Same mechanical relocation pattern from R-6a/R-6b: `git mv` → namespace rewrite → bidirectional `use` for cross-boundary peers → sed bulk-update of caller imports → `composer dump-autoload` → architecture suite → full Pest → commit → push.
- R-6c (5 Protection clean) shipped first. The 3 R-14a deferrals (ComprehensiveProtectionPlanService, CoverageGapAnalyzer, ProtectionActionDefinitionService) stay in `app/`.
- R-6d (9 Savings clean) shipped — closes R-6. ISATracker is the sole R-14a deferral (`?float $amount` on `updateISAUsage`). Pack `RateComparator` imports `App\Services\Savings\ISATracker` across the boundary.
- R-7 split into 3 sub-commits per the per-subsystem pattern: R-7a Goals, R-7b Plans, R-7c Coordination. Each sub-commit ran the architecture suite + full Pest before commit; both green every time.
- Provider bindings updated: `pack.gb.protection` → `Fynla\Packs\Gb\Protection\UkProtectionEngine` (R-6c), `pack.gb.savings` → `Fynla\Packs\Gb\Savings\UkSavingsEngine` (R-6d).

## What shipped today (session 3)

- `14c3561` — `refactor(uk-pack): R-6c — relocate 5 clean UK Protection services to GB pack`
- `a1b5712` — `refactor(uk-pack): R-6d — relocate 9 clean UK Savings services to GB pack (closes R-6)`
- `b507db6` — `refactor(uk-pack): R-7a — relocate 9 clean Goals services to GB pack`
- `6f9ed2f` — `refactor(uk-pack): R-7b — relocate 7 clean Plans services to GB pack`
- `c74fa2c` — `refactor(uk-pack): R-7c — relocate 5 clean Coordination services to GB pack (closes R-7)`

Total: 5 commits, all pushed; 35 services relocated; 14 R-14a deferrals carried.

## Files touched this session

### Relocated to GB pack (35 files)

**Protection (5)** — `packs/country-gb/src/Protection/`:
AdequacyScorer, ProtectionDataReadinessService, RecommendationEngine, ScenarioBuilder, UkProtectionEngine.

**Savings (9)** — `packs/country-gb/src/Savings/`:
EmergencyFundCalculator, FSCSAssessor, GoalProgressCalculator, LiquidityAnalyzer, PSACalculator, RateComparator, SavingsActionDefinitionService, SavingsDataReadinessService, UkSavingsEngine.

**Goals (9)** — `packs/country-gb/src/Goals/`:
FinancialForecastService, GoalAffordabilityService, GoalCalculationService, GoalRiskService, GoalsProjectionService, GoalStrategyService, LifeEventCashFlowService, LifeEventIntegrationService, LifeEventService.

**Plans (7)** — `packs/country-gb/src/Plans/`:
DisposableIncomeAccessor, EstatePlanService, GoalPlanService, PlanConfigService, ProtectionPlanService, SavingsPlanService, WhatIfCalculator.

**Coordination (5)** — `packs/country-gb/src/Coordination/`:
ConflictResolver, HolisticPlanner, PriorityRanker, RecommendationPersonaliser, RecommendationsAggregatorService.

### Deferred — kept in `app/Services/` (14 R-14a)

- **Protection (3)**: ComprehensiveProtectionPlanService, CoverageGapAnalyzer, ProtectionActionDefinitionService.
- **Savings (1)**: ISATracker.
- **Goals (3)**: GoalAssignmentService, GoalProgressService, LifeEventAllocationService.
- **Plans (4)**: BasePlanService, DistributionAccount, InvestmentPlanService, RetirementPlanService.
- **Coordination (3)**: CashFlowCoordinator, CrossModuleStrategyService, HouseholdPlanningService.

### Cross-boundary import additions

**Pack files importing deferred App peers (use App\…):**
- `packs/country-gb/src/Goals/GoalStrategyService.php` — `use App\Services\Goals\{GoalAssignmentService, GoalProgressService};`
- `packs/country-gb/src/Plans/EstatePlanService.php` — `use App\Services\Plans\BasePlanService;`
- `packs/country-gb/src/Plans/GoalPlanService.php` — `use App\Services\Plans\{BasePlanService, DistributionAccount};`
- `packs/country-gb/src/Plans/ProtectionPlanService.php` — `use App\Services\Plans\BasePlanService;`
- `packs/country-gb/src/Plans/SavingsPlanService.php` — `use App\Services\Plans\BasePlanService;`
- `packs/country-gb/src/Plans/WhatIfCalculator.php` — `use App\Services\Plans\{InvestmentPlanService, RetirementPlanService};`
- `packs/country-gb/src/Savings/RateComparator.php` — `use App\Services\Savings\ISATracker;`
- `app/Services/Protection/ComprehensiveProtectionPlanService.php` — `use Fynla\Packs\Gb\Protection\{AdequacyScorer, RecommendationEngine};` (deferred file referencing relocated peers)

**Deferred app services importing relocated pack peers:**
- `app/Services/Plans/InvestmentPlanService.php` — `use Fynla\Packs\Gb\Plans\{PlanConfigService, DisposableIncomeAccessor};`
- `app/Services/Plans/RetirementPlanService.php` — same.

### Architecture suite changes

- Exempt-dir list extended from 7 → 12 dirs: `Protection`, `Savings`, `Goals`, `Plans`, `Coordination` added. Now: `Providers, Constants, Traits, Models, Estate, Tax, Retirement, Investment, Protection, Savings, Goals, Plans, Coordination`.
- Target-dir list (3rd assertion) extended same way; assertion title now reads `Constants/Traits/Models/Estate/Tax/Retirement/Investment/Protection/Savings/Goals/Plans/Coordination`.
- Allow-list grew net by ~14 entries:
  - **Added:** 3 Goals R-14a + 4 Plans R-14a + 1 Savings R-14a + 2 Protection R-14a (used by pack ProtectionPlanService) + 6 R-8 deferred Agents (`App\Agents\{EstateAgent, GoalsAgent, InvestmentAgent, ProtectionAgent, RetirementAgent, SavingsAgent}`) + 1 NetWorthService bridge.
  - **Removed:** 4 entries that R-7 relocated (`App\Services\Goals\LifeEventIntegrationService`, `App\Services\Goals\LifeEventService`, `App\Services\Plans\PlanConfigService`, `App\Services\Coordination\RecommendationPersonaliser`).
- Contract impl tests repointed to pack: `UkProtectionEngine` → `Fynla\Packs\Gb\Protection\UkProtectionEngine`; `UkSavingsEngine` → `Fynla\Packs\Gb\Savings\UkSavingsEngine`.

## What the next Claude needs to know

- **The bidirectional import pattern is still load-bearing.** Every pack-side file with bare same-namespace refs to a deferred peer needs explicit `use App\…;`; every deferred file with bare refs to a relocated peer needs explicit `use Fynla\Packs\Gb\…;`. Forgetting one direction breaks `TypeError` on constructor type hints.
- **Sed bulk-update of caller imports** continues to use the per-class `for cls in ...; do files=$(grep -rln); while read f; do sed; done <<< "$files"` pattern. R-7 had 21 service classes total; sed handled all of them cleanly.
- **R-7b's BasePlanService deferral was the load-bearing complication** — 4 of 7 clean Plans services extend `BasePlanService` (which is R-14a deferred). Each needed explicit `use App\Services\Plans\BasePlanService;` after relocation. Same shape for `DistributionAccount` (deferred, used bare in `GoalPlanService`).
- **The R-7b allow-list expansion brought 6 App\Agents\* entries onto the list.** These are all R-8 deferrals — pack Plans + Coordination services orchestrate via App\Agents. R-8 will close all 6.
- **R-14a deferral list now sits at ~38 entries.** Through R-7c: 8 Retirement + 19 Investment + 3 Protection + 1 Savings + 3 Goals + 4 Plans + 3 Coordination = 41 service-level R-14a deferrals (plus 7 Estate/Tax services + 2 traits from R-5 = 50-ish total when R-9 lands its share). Re-scope R-14a at kickoff per § H of plan.
- **No deploys.** Pure refactor — no functional change. Production / dev are unaffected.
- **A leftover comment in `tests/Architecture/PackIsolationTest.php` line 80** (inside the R-6b exempt-dir block) was sed-rewritten to mention `Fynla\Packs\Gb\Plans\PlanConfigService`. The replacement is technically correct (PlanConfigService is in pack now) but reads slightly awkwardly inside a comment about R-6b's allow-list. Harmless — clean up at leisure.

## Pick up from here

**Start R-8.** Per `Plans/architecture-plan-v3.md` § R-8, scope is ~1.5 hr — relocate the 7 module agents (`EstateAgent`, `GoalsAgent`, `InvestmentAgent`, `ProtectionAgent`, `RetirementAgent`, `SavingsAgent`, plus per § E `CoordinatingAgent` moves with the module agents to GB pack).

Procedure (well-rehearsed):
1. Inspect imports and bare same-namespace cross-refs in all 7 agent files.
2. `git mv` 7 files; rewrite namespace from `App\Agents` to `Fynla\Packs\Gb\Agents`.
3. Add explicit cross-boundary `use` for any deferred peer the relocated agents reference bare.
4. Update any provider bindings if the agents are bound by container key.
5. Sed bulk-update of caller imports (per-class iteration).
6. Update `tests/Architecture/PackIsolationTest.php`:
   - Add `Agents` to exempt-dir list (8th entry).
   - Add `Agents` to target-dir list (13th entry).
   - REMOVE the 6 `App\Agents\*` allow-list entries added in R-7b/R-7c (they're now pack code).
7. `composer dump-autoload`.
8. `./vendor/bin/pest --testsuite=Architecture` (expect 123 passing).
9. `./vendor/bin/pest` (expect 2,788 passing).
10. Commit as `refactor(uk-pack): R-8 — relocate 7 UK module agents to GB pack`.

R-8 will be the first workstream that significantly shrinks the allow-list (closing the 6 R-8 markers added during R-7). After R-8: **R-9 Controllers + Requests + Resources + Observers (~6 hr)** — the largest single mechanical workstream.

## Current state references

- **Active branch:** `refactor/uk-pack-relocation` at `c74fa2c`, **27 commits ahead of `main` (`d8bd867`)**, all pushed.
- **Pest:** 2,788 passing, 1 skipped, 0 failing.
- **Architecture suite:** 123 passing, 240 assertions.
- **Allow-list watch:** `tests/Architecture/PackIsolationTest.php` allow-list has accumulated through R-3 + R-4 + R-5 + R-6a + R-6b + R-6c + R-6d + R-7a + R-7b + R-7c. R-8 will close 6 App\Agents\* entries. R-9 + R-10 continue accumulation. R-14a then closes ~30 R-14a-tagged entries; R-14b closes 6 core-model entries; R-15 verifies empty.
- **Plan budget:** ~61 hr total. Through R-7c: ~22 hr shipped (R-0 through R-7 complete). Remaining: ~39 hr (R-8 → R-15 + R-14a + R-14b).
- **CSJTODO.md sequence:** updated locally to mark R-6c, R-6d, R-7a, R-7b, R-7c complete; R-8 next.
