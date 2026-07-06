---
type: prd
workstream: R-17 — final relocation batch (pack boundary closure)
version: v1
date: 2026-07-06
status: autonomous-generated (loop mode) — CSJ review invited; assumptions in spec §6 are veto points
spec: Plans/r17-spec-v1.md
plan: Plans/r17-plan-v1.md
---

# PRD — R-17 Pack Boundary Closure

## 1. Overview
Complete the architecture campaign's unmet R-15 acceptance gate: every
UK-specific class leaves `App\` for `Fynla\Packs\Gb\`, every pack-consumed
neutral class lands in `Fynla\Core\`, and the `PackIsolationTest` allow-list
(81 entries) reaches zero. Pure relocation — no behaviour change.

## 2. Problem & context
See spec §1. The bidirectional core↔pack import cycle (80 app files → pack;
76 pack files → app) makes the pack architecture unenforceable and blocks
credible multi-country expansion. All future packs inherit whatever
discipline exists when the second production pack ships.

## 3. Users & stakeholders
Internal-only: future maintainers and pack authors. No end-user-visible
change permitted (acceptance criterion 6 — browser walkthrough identical).

## 4. Functional requirements
- FR1: 43 target files relocate per `r17-plan-v1.md` batches 1-8; long-tail
  sweep (batch 9) clears the remaining allow-list groups.
- FR2: The allow-list ratchets down in the same commit as each batch.
- FR3: Container aliases `pack.gb.tax_optimisation` and
  `pack.gb.exchange_control` rebind to their new FQCNs when their targets move.
- FR4: Core call-sites of pack agents (2 mobile controllers,
  AgentInternalController, RecommendationCacheObserver) consume agents via a
  core contract/binding — zero `Fynla\Packs\` imports in core when done.

## 5. Non-functional requirements
- Full Pest suite green after every batch (≥ 2,978 passing; no new skips).
- Larastan level 0 clean; baseline must not grow.
- No compatibility aliases; no behaviour drift (existing tests are the oracle).
- Each batch is one revertable commit, pushed.

## 6. Out of scope
God-file splitting, LegacyApiRewrite deletion, ZA/_template changes beyond
import-path updates, new features, performance work.

## 7. Success metrics
Spec §5 acceptance table (allow-list = 0; core grep clean; suite green;
Larastan clean; browser smoke clean; stranded-service inventory sign-off).

## 8. Rollout
Local-only build (no dev server yet — CSJ 2026-07-06). Land on
`refactor/uk-pack-relocation`; each batch pushed on green. No deploy step.

## 9. Open questions / decision log
- Q1 (batch 7): NetWorth core call-sites — new `NetWorthProvider` contract vs
  moving NetWorthController into the pack. Decide at execution; default = contract
  (matches the four-contract precedent).
- Q2 (batch 8): agent dispatch contract shape for mobile controllers.
  Default = smallest registry contract that zeroes core imports.
- Q3 (batch 9): framework bases (`Controller`, `SanitizedErrorResponse`,
  `RiskRecalculationObserver`) — lift to core vs documented permanent
  exemption. Default = lift to core.
- Q4: `App\Jobs\RunMonteCarloSimulation` — lift to core alongside
  MonteCarloEngine. Default = yes, batch 9.
