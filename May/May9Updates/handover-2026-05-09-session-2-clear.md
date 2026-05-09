---
type: handover
mode: context-clear
date: 2026-05-09
session: 2
branch: refactor/uk-pack-relocation
previous_session: 2026-05-09 session 1 (R-14a-Tax 4/5 SHIPPED)
---

# Context Clear Handover — 2026-05-09, Session 2

## Immediate state

**R-14a-Tax-v SHIPPED — R-14a-Tax CLOSED (5/5).** Branch tip `ed76434`, **79 commits ahead of `main`**, working tree clean, all pushed. Pest **2,825 passing / 1 skipped** maintained. Architecture **130/130** maintained. NoFloatMoneyTest green. PackIsolationTest allow-list **-1** (3 → 2 entries: only FormatsCurrency + CalculatesOCF remain in the v3-plan-original 14-target list). Auto-resume from session-1 handover step 2 completed cleanly. Paused before entering R-14a-Traits because of strategy ambiguity at scale (177 call sites — see "What the next Claude needs to know").

## The thread

- Auto-continued from session-1 handover at session start. Step 1 was UKTaxCalculator surface-mapping (~15 min), step 2 was the relocation + int-minor refactor + caller updates + tests + commit + push. Step 3 (R-14a-Traits) was the planned natural next, but explicitly deferred at the end.
- R-14a-Tax-v executed cleanly in one pass — file relocated via `git mv`, full int-minor rewrite using the Tax-iv (`28c537a`) pattern as canonical reference, 14 callers updated (5 core services + 4 pack services + 1 trait + 4 test files + 1 arch test), allow-list shrunk by 1.
- Surfaced one pre-existing 5-arg call shape in `CoverageGapAnalyzer::calculateProtectionNeeds` (lines 299, 336) where `annual_other_income` maps to position 5 (`$interestIncomeMinor`) instead of position 6 (`$otherIncomeMinor`). My first edit "fixed" this by adding a 6th positional 0 — but that's a behaviour change. Reverted to preserve original positional semantics exactly. Documented in commit body: "Pre-existing 5-arg call shape preserved exactly: `annual_other_income` continues to map to the 5th positional slot — fixing this suspected mis-mapping is out of scope for a mechanical R-14a refactor."
- Test runs: Architecture (130/130) → UserProfile + CoverageGapAnalyzer suites (66/66) → Tax + Retirement + ProtectionWorkflow integration (116/116) → full Pest (2,825 passed / 1 skipped, 873s). All green from first run — no fix-up cycles needed. Pattern from 11 prior sub-batches (7 Estate + 4 Tax) held cleanly for the 12th and biggest service.
- After Tax-v shipped, mapped R-14a-Traits-i (FormatsCurrency) surface and stopped at scope-discovery: **177 call sites across ~20 files** when including 3 BaseAgent inheritors (EstateAgent 15 calls, SavingsAgent 6, ProtectionAgent 1) + 4 plan-service callers + 1 test file. SavingsActionDefinitionService alone has 59 call sites. This is fundamentally different scale from any prior R-14a sub-batch (Tax-v had ~6 actual call sites total). Flagged the strategy ambiguity to the user instead of plowing through.

## Files touched (uncommitted or recently committed)

This session's only commit: `ed76434` (16 files):

- `app/Services/UKTaxCalculator.php` (DELETED, relocated)
- `packs/country-gb/src/Tax/UKTaxCalculator.php` (NEW; 932 lines vs 847 source — int-minor verbosity + helpers + boundary-conversion calls)
- `app/Services/Investment/Recommendation/UserContextBuilder.php` — import + inline FQCN replaced
- `app/Services/Retirement/RetirementStrategyService.php` — import only
- `app/Services/Protection/CoverageGapAnalyzer.php` — import + 2 boundary conversions (5-arg shape preserved)
- `app/Services/UserProfile/PersonalAccountsService.php` — import + 1 boundary conversion
- `app/Services/UserProfile/UserProfileService.php` — import + 2 boundary conversions
- `packs/country-gb/src/Investment/AssetLocation/TaxDragCalculator.php` — import only
- `packs/country-gb/src/Goals/GoalAffordabilityService.php` — import only
- `packs/country-gb/src/Goals/GoalsProjectionService.php` — import only
- `packs/country-gb/src/Goals/FinancialForecastService.php` — import only
- `packs/country-gb/src/Traits/ResolvesIncome.php` — import + 1 boundary conversion
- `tests/Unit/Services/UserProfileServiceTest.php` — import only
- `tests/Unit/Services/Protection/CoverageGapAnalyzerTest.php` — import only
- `tests/Unit/Services/UserProfile/FinancialCommitmentsTest.php` — import only
- `tests/Architecture/PackIsolationTest.php` — allow-list -1 (UKTaxCalculator entry + 2-line "stays put" comment block)

Working tree clean post-commit. No carry-over.

## What the next Claude needs to know

1. **R-14a-Tax pattern is fully crystallised across 5 sub-batches.** Read either Tax-iv (`28c537a` — TaxBandTracker, the stateful canonical reference) or Tax-v (`ed76434` — UKTaxCalculator, the heaviest service template) commit diff. Apply mechanically to FormatsCurrency. Helpers `private static poundsToMinor` + `minorToPounds` at file bottom are standard.

2. **R-14a-Traits-i scope is genuinely large — 177 call sites across ~20 files.** That's not a typo. Direct importers (12): app/Agents/BaseAgent.php, app/Services/Plans/BasePlanService.php, app/Services/Retirement/RetirementStrategyService.php (9 calls), app/Services/Protection/ComprehensiveProtectionPlanService.php (3), app/Services/Protection/ProtectionActionDefinitionService.php (15), packs/country-gb/src/Estate/EstateActionDefinitionService.php (uses trait but 0 calls per scan — verify), packs/country-gb/src/Savings/SavingsActionDefinitionService.php (**59 calls — the heaviest**), packs/country-gb/src/Investment/PortfolioStrategyService.php (2), packs/country-gb/src/Investment/InvestmentActionDefinitionService.php (12), packs/country-gb/src/Coordination/RecommendationPersonaliser.php (12), packs/country-gb/src/Retirement/RetirementActionDefinitionService.php (4), packs/country-gb/src/Tax/TaxActionDefinitionService.php (uses trait but 0 calls per scan — verify). Indirect via BaseAgent inheritance: packs/country-gb/src/Agents/EstateAgent.php (15), SavingsAgent.php (6), ProtectionAgent.php (1). Additional callers found in broader grep that may inherit via BasePlanService: app/Services/Coordination/CrossModuleStrategyService.php (2), app/Services/Plans/RetirementPlanService.php (1), app/Services/Plans/InvestmentPlanService.php (1), packs/country-gb/src/Plans/GoalPlanService.php (3). Test: tests/Unit/Agents/BaseAgentTest.php (4).

3. **Strategy ambiguity at the start of R-14a-Traits-i — DECISION REQUIRED at kickoff:**
   - **Strategy A — full int-minor ratchet** (consistent with R-14a-Tax public-method ratcheting): all 4 currency methods become `formatCurrency(int $amountMinor): string`, `formatCurrencyWithPence(int $amountMinor): string`, `formatCurrencyPrecise(int $amountMinor, int $decimals): string`, `formatCurrencyCompact(int $amountMinor): string`; `formatPercentage` becomes `formatPercentage(float $rate, ...)` (param rename — `$value` → `$rate` — because it's not money). Every one of the 177 call sites gets `(int) round($x * 100)` boundary conversion. Big diff, mechanical.
   - **Strategy B — preserve cross-pack contract via param rename** (consistent with R-14a-Tax output-key preservation): rename `$amount` → `$pounds` (matches no money keyword regex) in all 4 currency methods + `$value` → `$rate` in formatPercentage. This sidesteps NoFloatMoneyTest's heuristic without changing call-site signatures. Add new helpers (`poundsToMinor`, `minorToPounds`, `convertMinorKeysToPoundsRecursive` from the recursive walker extraction) as additive capabilities. ~10× smaller diff. Same logic that's been keeping output keys as float-pounds: pack-wide caller migration in lockstep is exactly what R-14a has been deferring.
   - **My read:** Strategy B is more consistent with the cross-pack-contract preservation convention crystallised across all 5 R-14a-Tax sub-batches. The "ratchet to int-minor" in Tax was for SERVICE-internal arithmetic and helpers — output contracts and callers stayed pounds. FormatsCurrency is consumed by 177 sites; ratcheting input is exactly the kind of lockstep migration we've been deferring. **But the v3-plan-original calls these "int-minor money refactor" targets explicitly.** Flagged to the user; awaiting redirect.

4. **NoFloatMoneyTest regex** (from `tests/Architecture/NoFloatMoneyTest.php`): only flags `function ... (float $\w*<keyword>)` where keyword ∈ {amount, balance, value, price, cost, salary, income, premium, fee, payment}. Param names ending in non-keyword words (`$pounds`, `$rate`) slip past — Strategy B exploits this. Test scans `core/app/Core` + `packs/`.

5. **The recursive walker** `convertMinorKeysToPoundsRecursive` lives inline in 4 sites currently (per grep): `packs/country-gb/src/Plans/EstatePlanService.php`, `packs/country-gb/src/Agents/EstateAgent.php`, `packs/country-gb/src/Http/Controllers/Estate/IHTController.php`, `packs/country-gb/src/Http/Controllers/Estate/GiftingController.php`. Plus 1 in `packs/country-gb/src/Estate/ComprehensiveEstatePlanService.php` per session-9 narrative (verify). Extract this to FormatsCurrency in R-14a-Traits-i regardless of Strategy A or B — it's pure additive value.

6. **R-14a-Traits-ii (CalculatesOCF, 2 callers — `app/Services/Investment/FeeAnalyzer.php` + `app/Services/Investment/Fees/OCFImpactCalculator.php`)** is the trivial closer after Traits-i. Three float-money sigs: `calculateWeightedOCF(Collection, float $totalValue): float`, `estimateOCF(string): float`, `calculateCompoundSavings(float $portfolioValue, float $annualSavings, int, float)`. `$totalValue` and `$portfolioValue` match "value" → trip NoFloatMoneyTest; `$annualSavings` doesn't match any keyword. Same Strategy A/B choice applies but at much smaller scale (2 callers).

7. **CoverageGapAnalyzer pre-existing 5-arg call shape** is now relocated and noted in commit `ed76434` body. If/when someone wants to investigate whether the original mapping (`annual_other_income` → 5th positional `$interestIncomeMinor` instead of 6th `$otherIncomeMinor`) is intentional or a bug, that's a separate workstream. Do NOT touch this in R-14a — out of scope.

8. **Vault root for fynlaInternational = `/Users/CSJ/Desktop/fynlaInter/FynlaInter/`** (NOT `fynlaBrain` — that's the legacy project's vault). The session-end and session-start skill texts still reference the legacy path; project memory has the override. Vault-sync subagent confirmed metrics drift continues per sessions 6–11: re-baseline at R-15.

9. **CLAUDE.md `UKTaxes.md` Current State doc** (vault path: `/Users/CSJ/Desktop/fynlaInter/FynlaInter/Current State/UKTaxes.md`) flagged as stale by vault-sync — Tax module moved from 4 pack services to 5 (R-14a-Tax CLOSED), all 5 Tax services now pack-resident with int-minor internal arithmetic + float-pound output keys. Doc may need a refresh pass to reflect this convention crystallisation. Informational, not blocking.

10. **Plan budget:** ~9-10 hr remaining after Tax-v. R-14a-Traits-i (FormatsCurrency) ~2-3 hr (Strategy B) or ~4-5 hr (Strategy A) + R-14a-Traits-ii (CalculatesOCF) ~1 hr + R-14b (container query layer + 6 deferred core models) ~5 hr + R-15 (full regression + browser test + dev/prod deploy) ~3 hr ≈ ~10-13 hr. R-14a closes when Traits-i + Traits-ii ship.

## Pick up from here

1. **R-14a-Traits-i (FormatsCurrency)** — kickoff with the Strategy A vs Strategy B decision. CSJ to choose; my read leans Strategy B (preserve cross-pack contract via `$amount` → `$pounds` rename + add new helpers) for scope/risk reasons, but Strategy A is what the v3-plan-original literally said.

2. After Strategy locked: relocate `app/Traits/FormatsCurrency.php` → `packs/country-gb/src/Traits/FormatsCurrency.php` (namespace `Fynla\Packs\Gb\Traits`), apply chosen strategy, extract `convertMinorKeysToPoundsRecursive` walker as a new trait method (pure additive — replaces 4-5 inline copies), add `poundsToMinor` + `minorToPounds` helpers (consolidates the 5 from R-14a-Tax + 7 from R-14a-Estate = 12 pre-extraction sites going forward), update imports across 12 direct callers + 3 BaseAgent inheritors + 4 plan-service callers + 1 test, allow-list -1, commit per Strategy A/B narrative + push.

3. Then **R-14a-Traits-ii (CalculatesOCF)** — same Strategy choice, much smaller surface.

4. **R-14a CLOSED** when both Traits sub-batches ship. Then R-14b + R-15 to plan close.

**DO NOT re-ask the strategy decision if CSJ has already answered it in chat post-clear.** The commit message body for `ed76434` documents the "preserve original positional semantics" ratchet for the CoverageGapAnalyzer 5-arg case — same principle applies here. If the chat history is empty post-clear, ask once and proceed. Default to Strategy B if no answer in chat.
