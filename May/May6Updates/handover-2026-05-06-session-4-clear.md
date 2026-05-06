---
type: handover
mode: context-clear
date: 2026-05-06
session: 4
branch: refactor/uk-pack-relocation
previous_session: 2026-05-06-session-3 (context-clear)
---

# Context Clear Handover — 2026-05-06, Session 4

## Immediate state

R-3, R-4, R-5 shipped on `refactor/uk-pack-relocation` (6 commits pushed). Working tree clean. Pest **2,788 passing**, 0 failing, 1 skipped. Architecture suite **123 passing**. ~11.5 of ~50 hours of plan budget shipped. R-6 → R-15 **not started**. The user paused before R-6 to assess deferrals; deferral list has grown enough that the int-minor (ADR-005) cleanup and the User/Household/Goal relocation should now be planned as explicit follow-up workstreams BEFORE R-15.

## The thread

- User asked at session start to "go through r-3 - r-15" — autonomous run.
- Executed R-3 (3 sub-commits), R-4 (2 sub-commits including polymorphic data migration), R-5 (1 commit with 7 Estate + 5 Tax services explicitly deferred for ADR-005 float-money signatures).
- Each batch hit a recurring pattern that ate ~30–60 min of fix iteration: bare class references inside files that didn't move (e.g. `User.php` bare-references `Property::class` after Property moves; resolves to wrong namespace). Solved each time by injecting explicit `use` statements via awk loops.
- ADR-005 NoFloatMoneyTest fired on every service moved into `packs/` that has `float $amount`-style parameters. So far 12 services explicitly deferred — list in CSJTODO.
- The architecture-test allow-list grew from 5 entries (R-3) to ~25 entries (R-5) tracking deferrals. Every entry is tagged with a ratchet target (R-6/R-7/R-15).
- User stopped me at the R-5/R-6 boundary, not because of a problem, but because the time-vs-progress arithmetic was clear: R-6 → R-15 is ~38.5 hours of plan budget; current pace per workstream is significantly slower than plan estimates due to the bare-reference + ADR-005 surface.

## Files touched this session

Committed (6 commits on `refactor/uk-pack-relocation`, all pushed):

- **`55e3726` — R-3a**: 4 jurisdiction-agnostic traits → core (`Auditable`, `StructuredLogging`, `HasJointOwnership`, `CalculatesOwnershipShare`). `FormatsCurrency` deferred (ADR-005). 78 files changed.
- **`260e2b7` — R-3b**: Exception/constants split. `FinancialCalculationException` → core, `InvestmentDefaults` → core, `QuerySchemas` → GB pack (audit reclassified — body references the FCA, UK-specific). 24 files.
- **`9033763` — R-3c**: 4 GB constants + 6 GB traits → GB pack. `CalculatesOCF` deferred (ADR-005). PackIsolationTest exemption + paralleling allow-list assertion added (mirrors ZA Http one). 67 files.
- **`ab5c08a` — R-4a**: 35 jurisdiction-agnostic models → core. `Factory::guessFactoryNamesUsing` + `guessModelNamesUsing` overrides added to AppServiceProvider so factories resolve relocated models. User/Household/Goal/GoalContribution/LifeEvent/LifeEventAllocation explicitly DEFERRED (heavy cross-pack relationships — relocating them would trip CoreIndependenceTest after R-4b). 214 files.
- **`dc07be3` — R-4b**: 53 UK models → GB pack (35 top-level + 12 Estate + 6 Investment). Polymorphic data migration (`2026_05_06_120000_backfill_polymorphic_morph_map_aliases.php`) backfills `joint_account_logs.loggable_type` and `holdings.holdable_type` from legacy `App\Models\X` to relocated `Fynla\Packs\Gb\Models\X`. Plan §6 option B (FQCN backfill) chosen over option A (morph map aliases) — existing test investment in FQCN-based morph data made `enforceMorphMap` unreasonable in the time budget. JointAccountLogController match arms + filter switched to new FQCNs via `::class`. 412 files.
- **`b10d2fb` — R-5**: 21 of 28 Estate services + 2 of 7 Tax services → GB pack. Pack provider bindings updated (`pack.gb.tax`, `pack.gb.estate`). 81 files added explicit `use Fynla\Packs\Gb\Tax\TaxConfigService;` for bare type-hints. **12 services deferred** (see CSJTODO). 169 files.

No memory files written this session.

## What the next Claude needs to know

1. **The deferral list is the load-bearing surface for R-15**. Twelve UK services, two traits, and six core models stayed put in `app/` because either (a) they have float-money parameters that trip ADR-005, or (b) they have heavy cross-pack relationships that would trip CoreIndependenceTest. These deferrals are tracked in:
   - `tests/Architecture/PackIsolationTest.php` allow-list (in-source ratchet markers)
   - `CSJTODO.md` Deferred section (human-readable list)
   - `Plans/architecture-plan-v3.md` workstream notes (need updating before R-15 plans the close-out)

2. **`Factory::guessFactoryNamesUsing` and `guessModelNamesUsing` are now load-bearing.** Both registered in `app/Providers/AppServiceProvider::boot()`. They walk a fixed namespace list (`Fynla\Core\Models\`, `Fynla\Packs\Gb\Models\`, `App\Models\`) — if a future workstream relocates `User` to core or adds a new pack with its own model namespace, that list must be extended.

3. **Polymorphic data migration is a one-shot**. The migration converts old `App\Models\X` values to new `Fynla\Packs\Gb\Models\X`. It runs automatically on `php artisan migrate`. After it runs once, repeated runs are idempotent (the WHERE clause matches the legacy strings, which are gone after first run). On a fresh seed, holdings/joint_account_logs are empty → migration is a no-op → tests work because new factories use the new FQCN. This means: if anyone re-introduces `App\Models\X` writes anywhere, those rows will need a fresh migration to convert. Don't.

4. **PackIsolationTest exemption pattern**: every relocation that creates a cross-namespace `use` statement adds a named exemption (path-based directory + allow-list entry). The pattern is well-established now (Providers/, Constants/, Traits/, Models/, Estate/, Tax/ are all exempt with allow-listed App\ classes). R-6/R-7 will continue this pattern; R-15 closes them.

5. **Two architectural tensions are NOT YET RESOLVED:**
   - The deferred core models (User, Household, Goal, GoalContribution, LifeEvent, LifeEventAllocation) need a container-resolved query layer before they can move to core. This is a separate workstream that should run BEFORE R-15 but AFTER R-6/R-7/R-8 (so the query layer can be designed around the actual relocated services).
   - The ADR-005 (int-minor money) refactor needs to land before the 12 deferred float-money services can move. This is also a separate workstream — does NOT belong in R-6 because it's an orthogonal concern.

## What's NOT done (ordered)

- **R-6 Retirement + Investment + Protection + Savings (~4.5 hr)** — not started. Next workstream. Expect significant ADR-005 surface (Investment fee/OCF math, Retirement projection math).
- **R-7 Goals + Plans + Coordination (~3 hr)** — not started.
- **R-8 Agents incl. CoordinatingAgent (~1.5 hr)** — not started.
- **R-9 Controllers + Requests + Resources + Observers (~6 hr)** — not started.
- **R-10 Migrations + Seeders (~1.5 hr)** — not started.
- **R-11 Real GB contract impls (~2 hr)** — not started.
- **R-12 Per-pack navigation() (~2 hr)** — not started.
- **R-13a UK frontend (~7 hr)** — not started.
- **R-13b SA frontend (~3 hr)** — not started.
- **R-14 Routing realignment (~3 hr)** — not started.
- **R-15 Full regression (~3 hr)** — not started.
- **NEW: ADR-005 int-minor refactor for 12 deferred services + 2 traits** — needs an explicit workstream before R-15.
- **NEW: User/Household/Goal/LifeEvent container-resolved query layer + relocation** — needs an explicit workstream before R-15.

## Tech debt found this session

Not formally invoked tech-debt-session — these are mechanical relocations, no domain logic added. But surfaced incidentally:

- `app/Traits/FormatsCurrency.php` — every method outputs `£` (UK currency symbol) hardcoded. UK-specific by content, but float-money signatures keep it deferred. Should ideally use core `Localisation` contract via the pack registry once relocated.
- `app/Services/Estate/IHTFormattingService.php`, `app/Services/Estate/PersonalizedTrustStrategyService.php`, `app/Services/Estate/PersonalizedGiftingStrategyService.php`, `app/Services/Estate/TrustService.php`, `app/Services/Estate/GiftingStrategy.php`, `app/Services/Estate/IntestacyCalculator.php`, `app/Services/Estate/FutureValueCalculator.php` — all use `float $amount`-style. ADR-005 candidates.
- `app/Services/Tax/IncomeDefinitionsService.php`, `app/Services/Tax/TaxOptimisationService.php`, `app/Services/Tax/TaxActionDefinitionService.php`, `app/Services/UKTaxCalculator.php`, `app/Services/TaxBandTracker.php` — same.

None blocking; all tagged with R-15 ratchets in the architecture test.

## Known issues / blockers

None. All workstreams green and pushed.

A note about the cycle that's slowing things down: each workstream needs (a) batch moves, (b) namespace updates inside moved files, (c) sed updates of all callers, (d) bare-class-reference fixes inside still-in-`app/` files (User.php, AppServiceProvider, etc.) and inside the moved files themselves (using deferred `App\Models\User` etc.), (e) architecture-test allow-list extension, (f) Pest run + iteration. Steps (d) and (e) consistently take longer than steps (a)–(c). Future Claude should plan to spend 30–90 min per workstream on step (d) alone.

## Pick up from here

```bash
# 1. Confirm clean state
git checkout refactor/uk-pack-relocation
git pull origin refactor/uk-pack-relocation
git status                            # must be clean

# 2. Inventory R-6 services
git ls-files 'app/Services/Retirement/*.php' | wc -l
git ls-files 'app/Services/Investment/**/*.php' | wc -l
git ls-files 'app/Services/Protection/*.php' | wc -l
git ls-files 'app/Services/Savings/*.php' | wc -l

# 3. Before moving anything, scan for float-money signatures (ADR-005 hazard)
for f in app/Services/Retirement/*.php app/Services/Investment/**/*.php app/Services/Protection/*.php app/Services/Savings/*.php; do
  grep -lE 'function\s+\w+\s*\([^)]*float\s+\$\w*(amount|balance|value|price|cost|salary|income|premium|fee|payment)' "$f" 2>/dev/null
done

# 4. Plan the deferral list BEFORE moves to avoid mid-batch reverts.
#    Decide which files move + which stay (based on float-money + cross-pack-ref scan).

# 5. Move in batches per module: Retirement → Investment → Protection → Savings.
#    After each batch: composer dump-autoload -o && ./vendor/bin/pest --testsuite=Architecture
#    Full pest only at workstream end.

# 6. Update GbPackServiceProvider bindings:
#    pack.gb.retirement → \Fynla\Packs\Gb\Retirement\UkRetirementEngine
#    pack.gb.investment → \Fynla\Packs\Gb\Investment\UkInvestmentEngine
#    pack.gb.protection → \Fynla\Packs\Gb\Protection\UkProtectionEngine
#    pack.gb.savings    → \Fynla\Packs\Gb\Savings\UkSavingsEngine
```

**Strong suggestion before starting R-6**: run `tech-debt-session` against the R-3/R-4/R-5 changes to surface anything the relocation introduced. Cheap insurance.

## Decision waiting on user

The user paused at the R-5/R-6 boundary and asked whether to keep going or stop and write a handover. They chose handover. The implicit decision the next session should pick up:

> **Should the int-minor (ADR-005) refactor and the User/Household/Goal relocation be explicit workstreams scheduled BEFORE R-15, or should the relocation continue to defer them and rely on the architecture-test allow-list (which now has ~25 deferral entries)?**

The deferral list has grown from "FormatsCurrency" (1 file in R-3) to "12 services + 2 traits + 6 models" (R-5). The trajectory suggests R-6/R-7 will add another 10–20 deferrals. If the answer is "schedule the refactors before R-15", the plan needs amending to insert two workstreams. If the answer is "defer all the way and clean up in R-15", R-15 grows from 3 hr to ~10–15 hr and becomes the actual hard workstream.

Surface this verbatim at session start; don't pick a path on the user's behalf.

## Context hints

- **Active branch:** `refactor/uk-pack-relocation`
- **Behind/ahead:** main at `d8bd867`; relocation branch tip `b10d2fb`, **11 commits ahead of main**, all pushed
- **Uncommitted:** none (this handover commit will be the only outstanding change)
- **Last commit:** `b10d2fb` refactor(uk-pack): R-5 — relocate UK Estate + Tax services to GB pack
- **Pest:** 2,788 passing, 1 skipped, 0 failed
- **Architecture suite:** 123 passing
- **Polymorphic data migration:** ran successfully on local DB (auto-applied via RefreshDatabase in tests; one-shot for production deploy)
- **v3 plan budget:** ~50 hr total — R-0 through R-5 shipped (~11.5 hr); ~38.5 hr remaining (R-6 → R-15) PLUS new workstreams for ADR-005 + deferred core model relocation
