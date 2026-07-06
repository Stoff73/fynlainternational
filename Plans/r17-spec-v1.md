---
type: spec
workstream: R-17 — final relocation batch (pack boundary closure)
version: v1
date: 2026-07-06
status: draft (autonomous loop — assumptions flagged for CSJ veto)
parent: Plans/architecture-plan-v3.md (§17 R-15 gate, §16a R-14a deferrals)
successor_gate: PackIsolationTest allow-list = 0 entries
---

# R-17 Spec — Close the Core↔Pack Boundary

## 1. Problem

The architecture campaign (R-0 → R-14b) moved the files but not the dependency
graph. As of the 2026-07-06 audit:

- **41 UK service files** remain in `app/Services/{Investment,Retirement,
  Protection,Savings,Goals,Plans,Coordination}` plus `app/Agents/{BaseAgent,
  TaxOptimisationAgent}.php` — all tagged as R-14a deferrals.
- **~78 files in `app/`** import `Fynla\Packs\Gb\*` directly.
- **~76 files in `packs/country-gb/`** import `App\Services\*` / `App\Agents\*`
  back — a hard bidirectional cycle. Neither layer can be built, tested, or
  reasoned about independently.
- `tests/Architecture/PackIsolationTest.php` carries a **~67-entry allow-list**
  whose own design says it must ratchet to **zero** (plan v3 §17, the unmet
  R-15 acceptance gate).
- Two core mobile controllers (`ModuleSummaryController`, `InsightsController`)
  import GB agents directly.

Until this closes, every future pack (ZA is already built, more planned) sits
on a foundation whose central promise — country code isolated in packs, core
jurisdiction-neutral — is not actually enforced anywhere except `core/app/`.

## 2. Goal

**The PackIsolationTest allow-list reaches zero and stays there.** Concretely:

1. Every UK-specific class under `App\` moves into `Fynla\Packs\Gb\*`.
2. Every jurisdiction-neutral class the GB pack depends on moves into
   `Fynla\Core\*` (or stays in `App\` with the pack consuming it via a
   contract/binding — decided per class, prefer the move).
3. Core (app/ + core/) contains **zero** `Fynla\Packs\` references outside the
   four sanctioned contracts' implementations wiring.
4. All existing tests stay green after every batch (no big-bang).

## 3. Non-goals

- No behaviour changes, no refactors-for-elegance, no god-file splitting
  (that's separate backlog). Pure relocation + import/binding rewiring.
- No new contracts unless a concrete cycle cannot be broken by relocation —
  the four existing contracts are believed sufficient (assumption A2).
- LegacyApiRewrite shim removal is NOT in scope (separate item — gated on
  shim-log silence after the frontend migration of 2026-07-06).
- The `_template` pack and ZA pack are untouched except where a moved core
  class forces an import path update.

## 4. Constraints

- **Batch-wise green**: each relocation batch must end with the full Pest
  suite passing and `composer analyse` (Larastan level 0) clean. Commit + push
  per green batch.
- **No compatibility aliases** (architecture decision, 2026-05-06): direct
  relocation, importers updated in the same commit. The single existing
  `class_alias` (App\Models\User) is legacy R-14b scope, not extended.
- **String references are first-class risk**: container bindings, queued job
  class names, config values, morph maps, factory resolution
  (`guessFactoryNamesUsing`), seeder FQCNs must be swept per batch.
- The allow-list in PackIsolationTest is ratcheted DOWN in the same commit as
  each batch — a batch that doesn't shrink the list needs a written reason.

## 5. Acceptance criteria

| # | Criterion | Verification |
|---|-----------|--------------|
| 1 | PackIsolationTest allow-list = 0 entries | read the file; arch suite green |
| 2 | `grep -r "Fynla\\\\Packs" app/ core/` returns only the contracts wiring sanctioned by plan v3 (target: zero outside providers) | grep + arch suite |
| 3 | `app/Services/` contains only jurisdiction-neutral services | inventory sign-off table in the plan doc |
| 4 | Full Pest suite green (≥ 2,978 passing, no new skips) | CI run |
| 5 | Larastan level 0 clean, baseline not grown | `composer analyse` |
| 6 | App boots + preview persona walkthrough clean (dashboard, protection, estate, goals, retirement) | browser smoke |

## 6. Assumptions taken autonomously (CSJ may veto)

- **A1**: Jurisdiction-neutral infrastructure (Cache, Auth, Payment, AI,
  Documents, Marketing, Admin, Mobile aggregators) STAYS in `app/` for now —
  moving it to `core/app/Core` is cosmetically nicer but not required to zero
  the allow-list; only classes the PACK imports must move to `Fynla\Core`.
  Minimises churn.
- **A2**: No new contracts. Where the pack imports a neutral `App\` class,
  that class moves to `Fynla\Core\*`; where core imports a UK class, the UK
  class moves to the pack and core reaches it via existing bindings/contracts.
- **A3**: `BaseAgent` becomes `Fynla\Core\Agents\BaseAgent` (it is a generic
  orchestrator base; its GB imports — TaxDefaults, FormatsCurrency — are
  resolved by moving those two to core or parameterising them).
- **A4**: The exact batch order comes from the dependency inventory
  (leaf-most first) — see plan doc.

## 7. Risks

- **Queued jobs / morph maps referencing moved classes by string** — payload
  in flight is nil (local build, no prod queue), so rename freely; morph map
  backfill migration (2026_05_06) pattern already exists as precedent.
- **Factory resolution**: GB model factories resolve via
  `guessFactoryNamesUsing` in `app/Providers/AppServiceProvider.php` — moving
  SERVICES doesn't touch this, but any model move would (none planned; models
  already relocated).
- **Context loss across loop iterations** — mitigated by this spec + the plan
  doc's batch checklist being the durable state; each iteration reads the
  tracker, does one batch, updates the tracker.
