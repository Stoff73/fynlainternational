---
type: handover
mode: context-clear
date: 2026-05-06
session: 3
branch: refactor/uk-pack-relocation
previous_session: 2026-05-06-session-2 (context-clear)
---

# Context Clear Handover — 2026-05-06, Session 3

## Immediate state

R-0a + R-1 + R-2 shipped on `refactor/uk-pack-relocation`. Three commits pushed. Working tree clean. Pest 2,787 passing, 0 failing, 1 skipped. Architecture suite 122 passing. The architecture fence now stands — both packs have App\ ban assertions with named, R-15-ratchet-tagged exemptions. Ready for **R-3** (Constants/Traits/Exceptions, ~3 hr) — first real-code workstream that moves UK files into `packs/country-gb/src/`.

## The thread

- User opened with frustration that session-start was re-asking about the agreed default path. Cut straight to executing R-0a per the plan default ("core-mediated query layer + skipped architecture assertions ratcheted in R-15") without re-surfacing.
- **R-0a SA HTTP relocation** (`d20e10f`). 5 controllers + 21 form requests + 13 resources + `ZaJurisdictionSeeder` moved out of `app/Http/{Controllers/Api/Za,Requests/Za,Resources/Za}/` and `database/seeders/` into `packs/country-za/src/Http/` and `packs/country-za/database/seeders/`. Namespaces updated to `Fynla\Packs\Za\Http\…` and `Fynla\Packs\Za\Database\Seeders\…`. `routes/api.php` FQCN refs updated at 43 callsites. `ZaTaxConfigurationSeeder` cross-ref updated. The now-stale `App\Http\Controllers\Api\Za\ZaProtectionController` entry in the `App\Http\Controllers` DB-facade allow-list trimmed (the controller is no longer in that namespace). Pest 2,783 → 2,784.
- **PackIsolationTest ratchet for ZA**: the pre-existing strict `App\` ban (lines 65-90) would have failed once the relocated controllers landed, because they still import `App\Models\DCPension`, `App\Models\FamilyMember`, `App\Models\Mortgage`, `App\Models\SavingsAccount`, `App\Models\Investment\Holding`, `App\Models\Investment\InvestmentAccount`, and the `App\Http\Controllers\Controller` base. Per the audit-amended plan default, scoped the strict ban to exclude `src/Http/`, and added a second narrow assertion that the Http adapters may only import the named allow-list (any other `App\` import fails). Both annotated `R-15 ratchet`.
- **R-1 TaxOptimisationEngine contract** (`910a75a`). 14th country-pack contract added at `core/app/Core/Contracts/TaxOptimisationEngine.php`. `TaxOptimisationAgent` now `implements TaxOptimisationEngine`. `GbPackServiceProvider` gains `pack.gb.tax_optimisation → \App\Agents\TaxOptimisationAgent`. Contract docblock initially named ISA / Section 11F as examples — caught by `NoHardcodedLegalCopyTest` (banned terms list includes `ISA`); rewrote the docblock jurisdiction-neutral. Provider docblock updated `13 contracts → 14`. Architecture suite 119 → 121 (+2: contract impl assertion + container resolution assertion).
- **R-2 GB architecture-test ratchet** (`26aae03`). Added `country-gb does not import the App namespace (outside provider wiring)` to `PackIsolationTest`. Mirrors the ZA shape — strict ban scoped, with `src/Providers/` as the named exemption (the container bindings still point at `\App\Services\…` while UK code moves in across R-3 → R-9). Initial state passes — only file currently in `src/` outside `Providers/` is `Support/PackManifest.php`, App-clean. Architecture suite 121 → 122.

## Files touched this session

Committed (3 commits on `refactor/uk-pack-relocation`):

- **`d20e10f`** — R-0a SA HTTP relocation
  - 39 file renames (5 controllers + 21 requests + 13 resources moved into `packs/country-za/src/Http/…`)
  - `database/seeders/ZaJurisdictionSeeder.php` → `packs/country-za/database/seeders/ZaJurisdictionSeeder.php`
  - `packs/country-za/database/seeders/ZaTaxConfigurationSeeder.php` (use-statement updated)
  - `routes/api.php` (43 FQCN refs)
  - `tests/Architecture/ApplicationArchitectureTest.php` (DB-facade allow-list trimmed)
  - `tests/Architecture/PackIsolationTest.php` (ZA assertion ratcheted + new Http allow-list assertion)
- **`910a75a`** — R-1 TaxOptimisationEngine contract
  - `core/app/Core/Contracts/TaxOptimisationEngine.php` (NEW)
  - `app/Agents/TaxOptimisationAgent.php` (`implements TaxOptimisationEngine`)
  - `packs/country-gb/src/Providers/GbPackServiceProvider.php` (new binding + docblock)
  - `tests/Architecture/PackIsolationTest.php` (+2 assertions)
- **`26aae03`** — R-2 GB architecture-test ratchet
  - `tests/Architecture/PackIsolationTest.php` (+1 assertion, +43 lines)

No memory files written this session.

## What the next Claude needs to know

1. **Both packs now have an `App\` ban** with explicitly named exemptions:
   - **GB pack**: exempts `src/Providers/` (provider wiring with `\App\Services\…` FQCNs in container bindings). Ratchet target: R-15.
   - **ZA pack**: exempts `src/Http/` AND constrains it via a narrow allow-list (`App\Http\Controllers\Controller`, `App\Models\DCPension|FamilyMember|Mortgage|SavingsAccount|Investment\Holding|Investment\InvestmentAccount`). Anything outside the list fails the build. Ratchet target: R-15.
   - **R-3 → R-9 must keep these clean.** When you move a UK file into `packs/country-gb/src/<Module>/`, swap every `use App\…` reference in the same commit. Either rename to `Fynla\Packs\Gb\…` if the target also moved this commit, or leave it as a known cross-pack ref BUT **add it to the ban exemption explicitly** (don't just punt to "the test passes because we forgot to extend the ban"). The audit's purpose was to make off-piste impossible; weakening the exemption surface defeats that.

2. **`TaxOptimisationAgent` is now contract-bound.** `pack.gb.tax_optimisation` resolves it; class implements `Fynla\Core\Contracts\TaxOptimisationEngine`. R-8 may relocate it into the GB pack — at that point the binding FQCN moves with it (`\App\Agents\TaxOptimisationAgent` → `\Fynla\Packs\Gb\Agents\TaxOptimisationAgent`). The interface does not need to change at relocation; only the binding target.

3. **Ratchet TODOs are tagged in-source.** Both `PackIsolationTest` exemption blocks contain explicit `R-15` comments. When R-15 lands, grep the file for `R-15` and tighten the exemptions; the test will then guard the closed boundary going forward.

4. **R-3 is the first real-code workstream.** Plan estimate 3 hr. Targets: `app/Constants/{TaxDefaults,ValidationLimits,EstateDefaults}.php` (and any others under `Constants/`), Traits in `app/Traits/`, and the `app/Exceptions/FinancialCalculationException.php` factory class. Each moves to `packs/country-gb/src/{Constants,Traits,Exceptions}/`. Direct relocation only — namespace + use-site updates in the same commit. Watch for cross-cutting traits (`Auditable`, `HasJointOwnership`, `CalculatesOwnershipShare`, `FormatsCurrency`, `StructuredLogging`, `PolicyCRUDTrait`, `ResolvesExpenditure`, `ResolvesIncome`, `TracksGoalContributions`) — some are UK-specific (move to GB pack), some are domain-agnostic (might belong in `core/app/Core/`). Decide per-trait at the top of R-3.

## What's NOT done (ordered by readiness)

- **R-3 Constants/Traits/Exceptions (~3 hr)** — not started. Next workstream.
- **R-4 Models 91 files (~5 hr)** — not started.
- **R-5 → R-15** — not started. ~43 hr remaining of plan budget.

Three decisions still gated to their workstreams (do not re-surface at session start — see `feedback_follow_handover_dont_re_ask.md`):

1. Backend URL strategy — gated to R-9 kickoff
2. UK table renames — gated to R-14 kickoff
3. R-0a cross-pack-imports approach — already resolved this session (plan default applied: allow-list + R-15 ratchet)

## Tech debt found this session

None new. R-0a was pure relocation. R-1 added a contract interface + 1 implements clause. R-2 added one architecture assertion. No new domain logic; no opportunities for duplicate-code drift, hardcoded values, or convention violations. Tech-debt-session not invoked — would have no actionable findings. Tech-debt audit deferred to R-3 (first workstream that moves real service code).

## Known issues / blockers

None. All three workstreams green and pushed.

## Pick up from here

```bash
# 1. Confirm branch + clean state
git checkout refactor/uk-pack-relocation
git status                            # must be clean
git pull origin refactor/uk-pack-relocation

# 2. Start R-3 (Constants/Traits/Exceptions, ~3 hr).
#    Inventory first:
git ls-files 'app/Constants/*.php'
git ls-files 'app/Traits/*.php'
git ls-files 'app/Exceptions/*.php'

# 3. For each, decide GB-pack-specific vs core-domain-agnostic. Move accordingly.
#    If GB-pack-specific: packs/country-gb/src/{Constants,Traits,Exceptions}/
#    If core: core/app/Core/<area>/  (rare — most likely all UK-specific)

# 4. After every batch of moves: composer dump-autoload -o && ./vendor/bin/pest
#    Pest must stay green at every commit boundary.
```

## Context hints

- **Active branch:** `refactor/uk-pack-relocation`
- **Behind/ahead origin/main:** main at `d8bd867`; relocation branch tip `26aae03`, 5 commits ahead
- **Uncommitted:** none — working tree clean (after this handover commit)
- **Last commit:** `26aae03` refactor(uk-pack): R-2 — GB pack architecture-test ratchet
- **Pest:** 2,787 passing, 1 skipped, 0 failed
- **Architecture suite:** 122 passing
- **v3 plan budget:** ~50 hr total — R-0 (1.5 hr) + R-0a (~2 hr) + R-1 (~1 hr) + R-2 (~1 hr) shipped; ~44.5 hr remaining
