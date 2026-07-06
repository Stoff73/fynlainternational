---
type: plan
workstream: R-17 — final relocation batch (pack boundary closure)
version: v1
date: 2026-07-06
status: ACTIVE — this file is the batch tracker; update the Status column as batches land
spec: Plans/r17-spec-v1.md
inventory_basis: full dependency inventory 2026-07-06 (agent-verified against live tree)
---

# R-17 Plan — Batch Tracker

**Corrected headline figures** (audit estimates → verified): 43 target files
(25,466 lines), 80 `app/` files importing `Fynla\Packs\Gb\*`, **81 unique
allow-list entries** in `tests/Architecture/PackIsolationTest.php` (lines
201-417), of which 3 are already stale. No container bindings reference any
target service except `pack.gb.tax_optimisation → App\Agents\TaxOptimisationAgent`
and `pack.gb.exchange_control → App\Services\ExchangeControl\UkExchangeControl`
(GbPackServiceProvider lines 45-46). Zero factory/morph-map/string-class-ref
impact (verified). No class-name collisions in any target namespace.

## Per-batch ritual (every batch, no exceptions)

1. `git mv` the files; update `namespace` + `use` lines (movers AND importers).
2. Ratchet `tests/Architecture/PackIsolationTest.php` — delete the entries the
   batch resolves. A batch that doesn't shrink the list needs a written reason here.
3. `composer dump-autoload -q`
4. `./vendor/bin/pest --parallel` green + `composer analyse` clean (baseline
   must not grow) + `./vendor/bin/pint --dirty`.
5. Browser smoke if the batch touches request-path services (dashboard +
   affected module page; check `storage/logs/laravel.log` for new errors).
6. Update this tracker; commit (`refactor(r17): batch N — …`) + push.

## Batch tracker

| # | Scope | Files | Risk | Status |
|---|-------|-------|------|--------|
| 1 | Neutral lift to `Fynla\Core` + delete 3 stale allow-list entries | 6 | LOW | **DONE 2026-07-06** — 9 allow-list entries removed (81→72). Course-correction: AiToolDefinitions + XaiToolDefinitions contain UK product copy (ISA/SIPP — caught by NoHardcodedLegalCopyTest core ratchet) and their only consumers are pack files → landed in `Fynla\Packs\Gb\AI\` instead of core. XaiClient → core as planned. MonteCarloEngine float signatures pinned in NoFloatMoneyTest ADR-005 list. Suite 2,979 green; Larastan baseline regenerated (path-only changes, still 11). |
| 2 | Risk/Settings foundation → pack | 6 | **HIGH** (RiskPreferenceService fan-in 16 app + 6 pack) | **DONE 2026-07-06** — RiskPreferenceService + AutoRiskCalculator → `Gb\Risk`, AssumptionsService → `Gb\Settings`, RecalculateRiskProfileJob → `Gb\Jobs` (new dir), RiskPreferenceController + AssumptionsController → pack controllers. Assumptions routes moved to pack (`/api/gb/settings/assumptions`, legacy URL shim-covered, frontend service updated). Allow-list 72→71 (−2 resolved, +1 pint-surfaced latent `StructuredResponseValidator` import in HasAiChat — batch 9). AssumptionsService float OCF helper pinned ADR-005. Suite 2,978 green (1 parallel flake passes isolated), Larastan clean, live smoke 200×3. |
| 3 | Investment module → `Gb\Investment` | 19 | MED (FeeAnalyzer 5 pack consumers) | **DONE 2026-07-06** — whole `App\Services\Investment` namespace → `Fynla\Packs\Gb\Investment` (subdirs preserved; Fees/ + Goals/ created; zero collisions). Allow-list 71→55 (−16). The 19 files ARE the R-14a float-money set (51 signatures) — pinned via new file-level `path:*` mechanism in NoFloatMoneyTest (new files still ratchet). Suite 2,979 green, Larastan baseline regenerated (path-only, 11), arch 133. |
| 4 | Retirement module → `Gb\Retirement` (depends on 3) | 8 | MED (2×2,000+-line files) | **DONE 2026-07-06** (`b9b4b83`) — wholesale namespace move, allow-list 55→47, float pins added. |
| 5 | Savings + Goals + Protection → pack | 7 | LOW-MED | **DONE 2026-07-06** (`e469558`) — allow-list 47→40, float pins added, suite 2,979 green validating both batches. |
| 6 | Plans + Coordination → pack (depends on 3+4) | 7 | MED | **DONE 2026-07-06** (`4b16bc3`) — allow-list 40→33; a baselined Larastan error fixed by the move (11→10). |
| 7 | NetWorth → pack | 4 | **HIGH** | **DONE 2026-07-06** — PRD Q1 resolved by R-9 precedent: NO new contract; NetWorthService→`Gb\NetWorth`, CrossModuleAssetAggregator→`Gb\Shared`, NetWorthController+routes→pack (`/api/gb/net-worth/*`, shim-covered, frontend updated), NetWorthCacheObserver→pack Observers (registration stays in EventServiceProvider per R-9b precedent). Allow-list 33→31. Shim regression test fixture updated. Suite 2,979 green, live smoke 200×2. |
| 8 | Agents | 2 | **HIGHEST** | **DONE 2026-07-06** — BaseAgent neutralised (TTL inlined as core const; GBP-hardcoded FormatsCurrency pushed down to the 3 agents that use it) → `Fynla\Core\Agents`; TaxOptimisationAgent → `Gb\Agents` with `pack.gb.tax_optimisation` rebound (tinker-verified); arch expectations repointed from empty `App\Agents` to real locations; BaseAgentTest double given the trait. Allow-list 31→29. roundToPenny float pin. Suite 2,978 green + known DomicileInfo parallel flake (passes isolated ×2 — add to flake watch). PRD Q2 note: the 4 core call-sites keep direct pack-agent imports for now — they already imported 6 pack agents pre-batch, so no NEW debt; registry decision folded into batch 9's core→pack sweep. |
| 9 | Sweep: remaining UK-specific `app/Services` outside original target set (NetWorth done in 7; Property, Trust, WhatIf, UserProfile-UK, Benefits, Business, Chattel, LifeStage, Dashboard aggregators, Documents UK mappers, AI UK prompts, ExchangeControl rebind) → zero allow-list | ~30 | MED, mechanical after 1-8 | TODO |
| 10 | Gate check: allow-list = 0, `grep Fynla\\Packs app/ core/` clean outside sanctioned wiring, full suite + Larastan + browser walkthrough | — | — | TODO |

## Batch details

### Batch 1 — Neutral lift (LOW)
Move to `Fynla\Core\...`:
- `app/Services/Cache/CacheInvalidationService.php` → `core/app/Core/Services/CacheInvalidationService.php` (5 app + 9 pack importers)
- `app/Services/Shared/MonteCarloEngine.php` → `core/app/Core/Services/MonteCarloEngine.php`
- `app/Services/Auth/PermissionService.php` → `core/app/Core/Services/PermissionService.php`
- `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiClient.php`, `app/Services/AI/XaiToolDefinitions.php` → `core/app/Core/AI/`
Delete stale allow-list entries (no code change): `DividendTaxCalculator` (L319), `Recommendation\LifeEventAssessmentService` (L332), `Recommendation\UserContextBuilder` (L333).

### Batch 2 — Risk/Settings foundation (HIGH)
- `app/Services/Risk/RiskPreferenceService.php` → `packs/country-gb/src/Risk/` (imports GB RiskProfile model). 16 app importers incl. `RiskPreferenceController`, `RecalculateRiskProfileJob`, 13 target services (which move in batches 3-4 — their imports fix themselves; only controller/job/AssumptionsService need same-commit updates).
- `app/Services/Risk/AutoRiskCalculator.php` → `packs/country-gb/src/Risk/`
- `app/Services/Settings/AssumptionsService.php` → `packs/country-gb/src/Settings/` (core `AssumptionsController` updates import — acceptable transitional core→pack import, pinned in allow-list REVERSE note? No: core→pack isn't allow-listed; instead move `AssumptionsController` + `RiskPreferenceController` INTO the pack controllers dir in the same batch — they are UK-module controllers that escaped R-9).
- Also move `app/Jobs/RecalculateRiskProfileJob.php` → pack if it imports the service (verify at execution).

### Batch 3 — Investment (19 files, MED)
All of `app/Services/Investment/**` per inventory → `packs/country-gb/src/Investment/**` mirroring subdirs (AssetLocation, Fees, Goals, ModelPortfolio, Performance, Recommendation, Tax). Includes orphan `DividendTaxCalculator` (still moves — UK logic), `UserContextBuilder`, `LifeEventAssessmentService`. Update pack consumers (FeeAnalyzer×5, PortfolioAnalyzer×4, etc.) and `app/Services/Plans/InvestmentPlanService` (moves later, batch 6 — transitional app→pack import is ALREADY allow-list-tolerated direction? No — app→pack imports aren't the allow-list's concern (that's pack→App). Fine transitionally; Larastan keeps it honest.)

### Batch 4 — Retirement (8 files, MED)
`app/Services/Retirement/**` → `packs/country-gb/src/Retirement/**`. RetirementIncomeService imports InvestmentProjectionService (fixed by batch 3). AnnualAllowanceChecker has 4 pack consumers.

### Batch 5 — Savings + Goals + Protection (7 files)
`ISATracker` → `Gb\Savings`; `LifeEventAllocationService`, `GoalAssignmentService`, `GoalProgressService` → `Gb\Goals`; `ProtectionActionDefinitionService`, `ComprehensiveProtectionPlanService`, `CoverageGapAnalyzer` → `Gb\Protection`.

### Batch 6 — Plans + Coordination (7 files)
`BasePlanService`, `DistributionAccount`, `InvestmentPlanService`, `RetirementPlanService` → `Gb\Plans`; `HouseholdPlanningService`, `CrossModuleStrategyService`, `CashFlowCoordinator` → `Gb\Coordination`.

### Batch 7 — NetWorth (HIGH)
`NetWorthService` (+ `Shared/CrossModuleAssetAggregator`) → `Gb\NetWorth`. Core call-sites (`NetWorthController`, `NetWorthCacheObserver`, `AutoRiskCalculator`(already pack by then)) — introduce `Fynla\Core\Contracts\NetWorthProvider` bound by GbPackServiceProvider (precedent: the four existing contracts), OR move NetWorthController into the pack. Decide at execution; prefer whichever leaves core clean.

### Batch 8 — Agents (HIGHEST)
1. Neutralise `BaseAgent`: move `Gb\Constants\TaxDefaults` cache-TTL constant + `Gb\Traits\FormatsCurrency` to `Fynla\Core\{Constants,Traits}` (or inject), then `BaseAgent` → `core/app/Core/Agents/BaseAgent.php`; update 7 pack agents' extends.
2. `TaxOptimisationAgent` → `Gb\Agents`; rebind `pack.gb.tax_optimisation` (GbPackServiceProvider L46); fix PackIsolationTest hard assertions (L676-685).
3. Core call-sites (`Mobile/ModuleSummaryController` — imports 6 pack agents + TaxOptimisationAgent, `Mobile/InsightsController` — CoordinatingAgent, `AgentInternalController`, `RecommendationCacheObserver`): route through a core agent-dispatch contract (`Fynla\Core\Contracts\ModuleAgentRegistry` or reuse existing `pack.gb.*` bindings via the container) — design at execution, smallest contract that zeroes the imports.

### Batch 9 — Long-tail sweep
Remaining group-(a) allow-list entries: Property(4)+PropertyCalculationService decision, Trust(2), WhatIf(1), UserProfile UK(3+service), Benefits, Business, Chattel, LifeStage, Dashboard/MobileDashboard aggregators, PrerequisiteGateService, Documents UK mappers(8+3), AI UK(KycGateChecker, QueryClassifier, SystemPromptBuilder, StructuredResponseValidator, QueryKnowledge, FcaProcessInstructions), 8 UK Http\Requests, `ExchangeControl/UkExchangeControl` (+ rebind pack.gb.exchange_control), `Observers/RiskRecalculationObserver` + framework bases (`Controller`, `SanitizedErrorResponse`) → these last two either lift to core or stay via explicit permanent-exemption note replacing the allow-list (decide: prefer lift to core).
Also: `App\Jobs\RunMonteCarloSimulation` — stays in app/Jobs (neutral engine job) → lift to core with MonteCarloEngine consumer check.

### Batch 10 — Gate
Acceptance criteria from spec §5, all six. Then update `Plans/architecture-plan-v3.md` frontmatter note: R-15 gate met via R-17, date. Update CLAUDE.md counts if drifted.

## Test impact map (from inventory)
BaseAgent 6 test files, ISATracker 4, AnnualAllowanceChecker 4, TaxOptimisationAgent 3 (+2 hard arch assertions), PortfolioAnalyzer 3, CoverageGapAnalyzer 3, FeeAnalyzer 2, DividendTaxCalculator 2, DecumulationPlanner 2, singles: UserContextBuilder, TaxEfficiencyCalculator, RetirementProjectionService, ProtectionActionDefinitionService, PensionProjector, HouseholdPlanningService, GoalProgressService, GoalAssignmentService, DistributionAccount, CrossModuleStrategyService, ContributionOptimizer, CashFlowCoordinator, BasePlanService. Update FQCNs in the same batch as the class moves.
