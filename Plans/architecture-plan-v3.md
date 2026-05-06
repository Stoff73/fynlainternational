---
type: plan
date: 2026-05-06
audited: 2026-05-06 (inline audit step, no formal PRD per user instruction)
status: ready for R-0
companion_spec: Plans/architecture-spec-v3.md
supersedes:
  - Plans/Implementation_Plan_v2.md
  - May/May5Updates/architecture-plan-realigned-2026-05-05.md
re_affirms: Plans/multi_country_architecture.md (v1.1)
---

# Architecture Plan v3 — UK Pack Relocation

This plan describes how the codebase moves from "UK in `app/`, SA in `packs/`" (the v2 hybrid) to "every country in a pack" (the original architecture in `multi_country_architecture.md` v1.1).

The core infrastructure built in 2026-04 (Workstreams A–D — contracts, Money, TaxYear, Jurisdiction, PackRegistry, ActiveJurisdictionMiddleware, smoke pack, ZA pack scaffold) is unchanged and remains correct. This plan moves UK code into the architecture that already exists for SA.

The plan is **execute the original `multi_country_architecture.md` § 15.2 ("Phase 1 — repackage UK logic into country-gb pack")** with the failed April approach replaced. Direct relocation, no compatibility aliases, one module per commit.

---

## 0. Audit Amendments (2026-05-06)

The inline audit step (see `code-explorer` audit report, session 2026-05-06) surfaced gaps and corrections that this section captures. Each item below either changes a workstream's scope or adds a workstream. Effort estimates updated where reality demanded it; "hours not days" mandate held.

### A. Live state corrections (R-0/R-1 already partially exist)

- `app/Providers/GbPackServiceProvider.php` already exists and binds 7 of 13 contracts to UK engines (`UkTaxCalculator` adapter, `UkRetirementEngine`, `UkInvestmentEngine`, `UkProtectionEngine`, `UkEstateEngine`, `UkSavingsEngine`, `UkExchangeControl`). R-0 must **relocate** this provider to `packs/country-gb/src/Providers/GbPackServiceProvider.php` rather than create a new one. R-1 is ~70% pre-existing — its remaining work is the 4 missing contracts (Localisation, Identity, Banking, LifeTables) and the binding namespace updates as files move in R-3 → R-9.
- The 4 missing contract bindings have **no Null implementations in core** today. The container will throw on resolution if anything tries to read them. R-0 must create `Fynla\Core\Localisation\NullLocalisation`, `Fynla\Core\Validation\NullIdentityValidator`, `Fynla\Core\Validation\NullBankingValidator`, `Fynla\Core\LifeTables\NullLifeTableProvider` BEFORE wiring R-1 stub bindings. R-11 then replaces these Null bindings with real GB implementations.
- `tests/Architecture/PackIsolationTest.php` already exists with custom describe/it syntax. R-2 **extends** this file rather than creating `PackBoundaryTest.php`; the ratchet pattern is added as additional `it()` blocks in the same file.
- `TaxOptimisationAgent` is injected into `CoordinatingAgent` but has no contract. Add a 14th contract `TaxOptimisationEngine` to `core/app/Core/Contracts/` in R-1, with `pack.gb.tax_optimisation` binding. Equivalent ZA implementation deferred to a future SA workstream.

### B. Pre-flight workstream R-0a (NEW — SA pack cleanup, ~2 hr)

The architecture fence cannot be erected while SA code is split between `app/` and `packs/country-za/`. The audit found:

- `app/Http/Controllers/Api/Za/` (5 controllers) → must move to `packs/country-za/src/Http/Controllers/`
- `app/Http/Requests/Za/` (21 requests) → must move to `packs/country-za/src/Http/Requests/`
- `app/Http/Resources/Za/` (13 resources) → must move to `packs/country-za/src/Http/Resources/`
- SA controllers currently import UK models (`App\Models\DCPension`, `App\Models\Investment\Holding`, `App\Models\Investment\InvestmentAccount`, `App\Models\SavingsAccount`, `App\Models\FamilyMember`, `App\Models\Mortgage`). Two paths: (1) add SA-equivalent models to `packs/country-za/src/Models/`, or (2) keep the cross-pack reads via core-mediated query layer. **Plan default: option (2) for now** — record the imports as known violations in the architecture test suite (skipped assertions ratcheted in R-15 once UK models have moved and SA can read them via `Fynla\Packs\Gb\Models\…` if absolutely needed; better, factor a core-mediated `AssetQueryService` that abstracts asset lookup. This decision is forced by R-9 and surfaces explicitly there.).
- `database/seeders/ZaJurisdictionSeeder.php` → move to `packs/country-za/database/seeders/`
- SA frontend in `resources/js/`: `components/ZA/` (44), `views/ZA/` (5), `store/modules/za*` (6 modules), `services/za*` (5 services) → relocate to `packs/country-za/resources/js/` as part of **new workstream R-13b** (split from R-13).

R-0a runs after R-0 skeleton creation and before R-1 binding refresh.

### C. Quantification corrections (numbers the plan handwaved)

| Workstream | Plan said | Actual count | Effort delta |
|---|---|---|---|
| R-3 traits | 12 traits, simple split | 12 traits, **non-trivial split** — 5 are core, 4 are GB pack, 3 (`HasAiChat`, `HasAiGuardrails`, `ResolvesExpenditure`) require careful audit (CoordinatingAgent's AI tooling references UK models) | +0.5 hr |
| R-3 constants | 3 files | 6 files (`TaxDefaults`, `EstateDefaults`, `ValidationLimits` to GB; `InvestmentDefaults`, `QuerySchemas` to **core** not GB; `FinancialPlanningKnowledge` to GB) | +0.5 hr |
| R-4 models | 76+ | **91** model files (top-level 64 + Estate/ 12 + Investment/ 6 + Will/Trust models) | +1 hr |
| R-4 polymorphic | "Trust + ProtectionPolicy" | **`Holding.holdable`** (targets InvestmentAccount, DCPension) + **`JointAccountLog.loggable`** (targets Property, Mortgage, InvestmentAccount, SavingsAccount) — 2 backfill data migrations required | already in scope |
| R-6 services | "16 Investment files" | **35+ Investment files** in 10 nested subdirectories (`Recommendation/`, `Rebalancing/`, `Fees/`, `Goals/`, `ModelPortfolio/`, `Tax/`, `Utilities/`, `Performance/`, `Analytics/`, `AssetLocation/`) | +1.5 hr |
| R-9 controllers | "~80 UK controllers" | **93 controllers** in `Api/` (63 flat + 30 across Estate/Investment/Retirement/Tax/Settings/Za) | +1 hr |
| R-10 migrations | handwaved | **130+ UK migration files** since 2025-12 | already in scope |
| R-13 frontend | 13 component dirs | **+ Trusts/, Risk/, WhatIf/, Advisor/, Public/ (30+), NetWorth/ views, Planning/ views** — 7 additional directories | +2 hr |
| R-13 split | one workstream | **R-13a (UK frontend) + R-13b (SA frontend)** — SA relocation is a prerequisite for the architecture fence | R-13b adds 2 hr |

### D. `app/Services/` directories not classified by the plan (X-2 in audit)

The plan's R-5/R-6/R-7 named only the canonical module dirs. The audit identifies 23 additional `app/Services/` subdirectories. Classification table appended to R-5 (see § 7 below). Effort budget for unclassified-services relocation: **+2 hr**, distributed across R-5/R-6/R-7.

### E. `CoordinatingAgent` AI coupling decision (high-impact)

`CoordinatingAgent` uses `HasAiChat` + `HasAiGuardrails` traits. These traits reference `AiToolDefinitions` which encodes UK product structures (SIPP, ISA, IHT). Two paths:

1. **Move `CoordinatingAgent` to GB pack now.** Simpler, faster, no architecture violation, defers core AI generalisation to Phase 2 when SA needs AI chat.
2. **Keep `CoordinatingAgent` in core, generalise AI tools to a pack contract.** Architecturally cleaner long-term, requires designing an `AiToolProvider` contract per pack, adds ~3 hr scope to R-8.

**Plan default (per "hours not days" mandate): Option 1 — `CoordinatingAgent` moves to GB pack as part of R-8.** Generalisation deferred to Phase 2. The existing `AiToolDefinitions` becomes UK-specific by being co-located with the UK agents.

### F. Test relocation

Defer to a follow-up after Phase 1. Tests stay in `tests/` for now; namespace references update as UK code moves. Decision recorded; no workstream change.

### G. Updated total estimate

Plan original total: **~39 hr**. After audit corrections: **~50 hr** broken down as:

| WS | Was | Now |
|---|---|---|
| R-0 | 1 | 1.5 (Null impls) |
| R-0a (new) | — | 2 |
| R-1 | 1 | 1 (much already in place but contracts +1 + binding refresh as files move costs more later) |
| R-2 | 1 | 1 |
| R-3 | 2 | 3 |
| R-4 | 4 | 5 |
| R-5 | 3 | 3.5 |
| R-6 | 3 | 4.5 |
| R-7 | 2 | 3 |
| R-8 | 1 | 1.5 (CoordinatingAgent move) |
| R-9 | 4 | 6 |
| R-10 | 1 | 1.5 |
| R-11 | 2 | 2 |
| R-12 | 2 | 2 |
| R-13a (UK FE) | 6 | 7 |
| R-13b (SA FE, new) | — | 3 |
| R-14 | 3 | 3 |
| R-15 | 3 | 3 |
| **Total** | **39** | **~50** |

The 11-hour delta reflects quantification, not scope creep. Actual file counts are larger than the original plan assumed; SA pack cleanup and SA frontend relocation are net-new prerequisites the plan failed to surface. Hours-not-days mandate still holds at workstream level — flag drift if any single workstream blows past its updated estimate.

### H. Three plan defaults — confirmation deferred

The three decision points in § 18 (URL strategy, table renames, branch strategy) are not blocking the audit or R-0. They surface to the user **before R-9** (URL strategy gates the route restructure) and **before R-14** (table renames). Branch strategy is already locked: single long-lived `refactor/uk-pack-relocation`.

---

## 0. Branch + Commit Strategy

- **New branch:** `refactor/uk-pack-relocation` — branched from `main`, not from `feature/architecture-realignment`.
- The existing `feature/architecture-realignment` branch (commits `f21e939`, `0adf82b`) is **archived as superseded**. Its data-only contributions (TestUsersSeeder jurisdiction pinning) are cherry-picked into the relocation branch as a single early commit; the SideMenu refactor is replaced by per-pack `navigation()` (WS R-12) — no part of `MODULES_BY_JURISDICTION` survives.
- Each workstream below ships as **one or more commits** on the relocation branch. Pest must be green at the end of every workstream.
- Branch merges to `main` only after all workstreams complete + full Playwright regression passes (WS R-15).

---

## 1. The 15-Workstream Sequence

Order is mandatory — earlier workstreams establish the namespace and binding plumbing that later ones rely on.

| # | Workstream | Effort | Output |
|---|---|---|---|
| R-0 | Pre-flight: archive realignment branch, salvage seeder fix, set up GB pack skeleton | 1 hr | `packs/country-gb/` with composer.json, src/ tree, ServiceProvider stub, autoload registered |
| R-1 | Wire 13 container bindings to existing UK services (no file moves yet) | 1 hr | `pack.gb.tax`, `pack.gb.savings`, … all resolvable from `\App\…` classes |
| R-2 | Architecture test suite — initially scoped to SA, ratcheted as UK code relocates | 1 hr | `tests/Architecture/PackBoundaryTest.php` |
| R-3 | Relocate UK Constants + Traits + Exceptions (smallest, lowest-risk batch) | 2 hr | TaxDefaults, EstateDefaults, ValidationLimits, Auditable, FormatsCurrency, FinancialCalculationException → `packs/country-gb/src/{Constants,Traits,Exceptions}/` |
| R-4 | Relocate UK Models (76 + sub-models) | 4 hr | `packs/country-gb/src/Models/` — IsaContribution, LtaRecord, UfplsEvent, all UK financial entities |
| R-5 | Relocate UK Services — Estate (28), Tax, TaxConfigService, IHT/CGT/NRB calculators | 3 hr | `packs/country-gb/src/{Estate,Tax}/` |
| R-6 | Relocate UK Services — Retirement (13), Investment (16), Protection (8), Savings (10) | 3 hr | `packs/country-gb/src/{Retirement,Investment,Protection,Savings}/` |
| R-7 | Relocate UK Services — Goals (12), Plans (11), Coordination (8 — split from CoreCoordinatingAgent) | 2 hr | `packs/country-gb/src/{Goals,Plans}/` |
| R-8 | Relocate UK Agents (7 module agents — `CoordinatingAgent` stays in core) | 1 hr | `packs/country-gb/src/Agents/` |
| R-9 | Relocate UK Controllers (~80) + Requests (~80) + Resources (~12) + Observers (~12) | 4 hr | `packs/country-gb/src/Http/`, `packs/country-gb/src/Observers/` |
| R-10 | Move UK migrations + UK seeders into pack | 1 hr | `packs/country-gb/database/{migrations,seeders}/` |
| R-11 | Implement the 4 missing UK contract bindings (`Localisation`, `IdentityValidator`, `BankingValidator`, `LifeTableProvider`) | 2 hr | Closes contract symmetry with ZA |
| R-12 | Replace `MODULES_BY_JURISDICTION` constant with per-pack `navigation()` providers (frontend) | 2 hr | Each pack's `navigation()` registers via PackRegistry on import; sidebar reads from registry |
| R-13 | Relocate UK frontend — Vue components (~440 UK), views (~70), Vuex modules (~24 UK) | 6 hr | `packs/country-gb/resources/js/{components,views,store,services}/` — dynamic-imported on auth |
| R-14 | Routing realignment — country-prefixed backend URLs (`/api/gb/*`), pack-isolated frontend routes, redirect layer for legacy URLs | 3 hr | UK clients see no break; SA `/za/*` URLs drop to unprefixed inside the SA pack scope |
| R-15 | Full Pest + Playwright regression — UK-only journey, SA-only journey, dual-user smoke | 3 hr | Phase 1 acceptance per Spec § 10 |

**Total: ~39 hours of focused mechanical execution.**

The previous "days/weeks" framing assumed scope drift, off-piste exploration, and re-planning loops. Held to the plan as written — with disciplined namespace-renames, scripted `use`-statement updates, and Pest-green-after-every-commit cadence — this is hours of work, not weeks. Any blow-out beyond the per-workstream estimates is a signal to stop and re-check whether the work has drifted from the plan, not to extend the estimate.

Workstreams R-3 through R-9 are mechanical and can each be done in tighter focus blocks. R-13 is the single largest chunk — frontend has the most files but the simplest namespace story (just import-path updates).

---

## 2. R-0 — Pre-flight + GB Pack Skeleton (1 hr)

### Goal
A buildable `packs/country-gb/` Composer package, registered with `composer.json`, that compiles but contains nothing.

### Tasks

1. Branch off main: `git switch -c refactor/uk-pack-relocation`
2. Cherry-pick the TestUsersSeeder jurisdiction-pinning change from `feature/architecture-realignment` (commit `0adf82b`'s seeder portion only) — write as a single new commit "chore(seeders): pin GB/ZA jurisdictions on test users".
3. Scaffold `packs/country-gb/` from `packs/_template/`:
   - `composer.json` with `"name": "fynla/pack-country-gb"`, autoload `Fynla\\Packs\\Gb\\` → `src/`
   - `src/Providers/GbPackServiceProvider.php` (empty `register()` and `boot()`)
   - `src/{Tax,Estate,Retirement,Investment,Protection,Savings,Goals,Plans,Validation,Localisation,Billing,LifeTables,Models,Http/Controllers,Http/Requests,Http/Resources,Observers,Constants,Agents,Traits,Exceptions}/` empty directories with a `.gitkeep`
   - `database/{migrations,seeders}/` empty
   - `routes/api.php` empty stub
   - `tests/{Unit,Feature,Architecture}/` empty
   - `resources/js/` empty
4. Add path repository to root `composer.json`:
   ```json
   "repositories": [
     { "type": "path", "url": "packs/country-xx-smoke", "options": { "symlink": true } },
     { "type": "path", "url": "packs/country-za", "options": { "symlink": true } },
     { "type": "path", "url": "packs/country-gb", "options": { "symlink": true } },
     { "type": "path", "url": "packs/_template", "options": { "symlink": true } }
   ]
   ```
   ```json
   "require-dev": {
     "fynla/pack-country-gb": "*@dev",
     ...
   }
   ```
5. Run `composer dump-autoload` — verify GB pack autoload resolves.
6. Register `GbPackServiceProvider` with the core `PackRegistry` in its `boot()`.
7. Add `Fynla\Packs\Gb\Support\PackManifest` declaring `code = 'gb'`, `name = 'United Kingdom'`, `currency = 'GBP'`, `locale = 'en_GB'`, `tax_year_start = '04-06'`.
8. Pest: `php artisan tinker --execute="resolve(\Fynla\Core\Registry\PackRegistry::class)->all();"` shows GB and ZA both registered.

### Verification
- `./vendor/bin/pest` — green (zero functional change yet).
- `composer show fynla/pack-country-gb` resolves.
- PackRegistry returns GB metadata.

### Risks
- composer cache. Run `composer dump-autoload --classmap-authoritative=false` if autoload misbehaves.

---

## 3. R-1 — 13 Container Bindings to Existing UK Services (1 hr)

### Goal
Every `pack.gb.*` container key resolves to a working class — initially the existing `\App\Services\…` classes. This decouples *resolution from location*: the rest of the relocation can move classes one at a time without breaking callers.

### Tasks

1. In `GbPackServiceProvider::register()`, bind all 13 contract keys:
   ```php
   $this->app->bind('pack.gb.tax',          \App\Services\Tax\TaxConfigService::class);
   $this->app->bind('pack.gb.savings',      \App\Services\Savings\SavingsAgent::class);  // existing UK service
   $this->app->bind('pack.gb.investment',   \App\Agents\InvestmentAgent::class);
   $this->app->bind('pack.gb.retirement',   \App\Agents\RetirementAgent::class);
   $this->app->bind('pack.gb.protection',   \App\Agents\ProtectionAgent::class);
   $this->app->bind('pack.gb.estate',       \App\Agents\EstateAgent::class);
   $this->app->bind('pack.gb.goals',        \App\Agents\GoalsAgent::class);
   $this->app->bind('pack.gb.exchange_control', \Fynla\Core\ExchangeControl\NullExchangeControl::class);
   // The 4 missing — stub for now, real implementations land in R-11:
   $this->app->bind('pack.gb.localisation',     \Fynla\Core\Localisation\NullLocalisation::class);
   $this->app->bind('pack.gb.identity',         \Fynla\Core\Validation\NullIdentityValidator::class);
   $this->app->bind('pack.gb.banking',          \Fynla\Core\Validation\NullBankingValidator::class);
   $this->app->bind('pack.gb.life_tables',      \Fynla\Core\LifeTables\NullLifeTableProvider::class);
   $this->app->bind('pack.gb.payments',         \App\Services\Payment\PaymentService::class);
   ```
2. Each binding must satisfy its contract. Where the existing UK service does *not* implement the contract, write a thin adapter (`Fynla\Packs\Gb\TaxEngineAdapter` wrapping `TaxConfigService`). Adapters live in the new pack from day 1 — they are the *only* code in `packs/country-gb/src/` until R-3 starts.
3. Add a Pest test asserting every `pack.gb.*` binding resolves and `instanceof` its contract.

### Verification
- All 13 bindings resolvable.
- Each binding `instanceof` its contract interface.
- Pest green.

### Risks
- Some UK services have constructor dependencies the container can't auto-resolve. Add explicit container singletons in the provider as needed.
- `NullExchangeControl` and other Null implementations must already exist in core (created during the original WS A-D). If not, create them in this workstream.

---

## 4. R-2 — Architecture Test Suite (1 hr)

### Goal
A test suite that fails the build if pack boundaries are violated. Initially scoped to ZA (since GB has no code yet); ratcheted to GB as each workstream lands.

### Tasks

1. Create `tests/Architecture/PackBoundaryTest.php`:
   ```php
   it('SA pack does not reference UK code')
       ->expect('Fynla\Packs\Za')
       ->not->toUse('App\\');

   it('SA pack does not reference UK pack')
       ->expect('Fynla\Packs\Za')
       ->not->toUse('Fynla\Packs\Gb');

   it('Core does not reference any pack')
       ->expect('Fynla\Core')
       ->not->toUse('Fynla\Packs\\');

   it('Core does not reference UK App namespace')
       ->expect('Fynla\Core')
       ->not->toUse('App\\Services')
       ->not->toUse('App\\Agents')
       ->not->toUse('App\\Models');
   ```
2. Add the inverse rule, initially marked `@skipped`, to be activated workstream-by-workstream:
   ```php
   it('UK pack does not reference App namespace')->skip('Activated as relocation completes')
       ->expect('Fynla\Packs\Gb')
       ->not->toUse('App\\');

   it('UK pack does not reference SA pack')
       ->expect('Fynla\Packs\Gb')
       ->not->toUse('Fynla\Packs\Za');
   ```
3. Each subsequent workstream activates the relevant assertion when its directory is empty in `app/`.

### Verification
- Suite runs in `./vendor/bin/pest --testsuite=Architecture`.
- All non-skipped assertions pass at this point.

### Risks
- Pest architecture-testing plugin may not be installed. Verify; install if needed.

---

## 5. R-3 — Relocate Constants + Traits + Exceptions (2 hr)

### Goal
Smallest, lowest-risk batch first. Establishes the relocation pattern. After this, the team has a known-good "move + namespace + update use statements + run Pest" rhythm.

### Targets (~30 files)

`app/Constants/`: TaxDefaults (UK), EstateDefaults (UK), ValidationLimits (UK).
`app/Traits/`: 12 traits — split:
- **Stay in core (generalise):** Auditable, StructuredLogging, FormatsCurrency, HasJointOwnership, CalculatesOwnershipShare → move to `core/app/Core/Traits/` (`Fynla\Core\Traits\`).
- **Move to GB pack:** UK-specific traits (e.g. `PolicyCRUDTrait` if UK-specific, `ResolvesIncome` / `ResolvesExpenditure` if they reference UK tax shapes).
- Audit each trait's call sites before deciding.

`app/Exceptions/FinancialCalculationException.php` → `Fynla\Core\Exceptions\` (generic, stays in core).

### Tasks (per file)

1. Move file to target location.
2. Update `namespace` declaration.
3. `grep -rln "use App\Constants\TaxDefaults;" app/ tests/ core/ packs/ | xargs sed -i '' 's|use App\\Constants\\TaxDefaults;|use Fynla\\Packs\\Gb\\Constants\\TaxDefaults;|g'` (or equivalent rector rule).
4. Update fully-qualified references too: `\App\Constants\TaxDefaults` → `\Fynla\Packs\Gb\Constants\TaxDefaults`.
5. `composer dump-autoload`.
6. `./vendor/bin/pest` — green.
7. Commit: "refactor(uk-pack): relocate Constants/TaxDefaults to GB pack".

### Verification
- All UK constant references resolve from `Fynla\Packs\Gb\Constants\…`.
- Pest green.
- Architecture test ratchet: assert `app/Constants/` is empty (or contains only non-UK files).

### Risks
- A constant referenced by core (mistake — constant should not be UK-specific if core uses it). If found, generalise and put in core; do not move to GB pack.

---

## 6. R-4 — Relocate UK Models (4 hr)

### Goal
All 76+ UK financial-entity models live in `packs/country-gb/src/Models/`. Generic models (`User`, `Household`, `Goal`, `LifeEvent`, `Jurisdiction`, `UserJurisdiction`) stay in core (the latter two are already in `core/app/Core/Models/`).

### Targets

`app/Models/` — audit each file:

| Model | Destination |
|---|---|
| `User`, `Household`, `HouseholdMember`, `Goal`, `LifeEvent`, `EmailVerificationCode`, `PendingRegistration`, `Subscription`, `DiscountCode`, `AuditLog` | Stay in core (`core/app/Core/Models/`) |
| All Tax / Estate / Retirement / Investment / Protection / Savings models (IsaContribution, LtaRecord, UfplsEvent, Trust, Will, ProtectionPolicy, …) | `packs/country-gb/src/Models/` |
| Sub-directories `app/Models/Estate/` (12), `app/Models/Investment/` (6), etc. | `packs/country-gb/src/Models/{Estate,Investment,…}/` |

### Tasks (per model batch)

1. Move file.
2. Update namespace.
3. Update relationship references in OTHER models — every `belongsTo(\App\Models\X::class)` updates to `belongsTo(\Fynla\Packs\Gb\Models\X::class)`.
4. Update factory references in `database/factories/`.
5. Update controller / service references.
6. Update test references.
7. `composer dump-autoload`.
8. `./vendor/bin/pest` — green.
9. Commit per coherent batch (e.g. "refactor(uk-pack): relocate Estate models").

### Critical: handle polymorphic relations carefully

Polymorphic relations store the FQCN in a `*_type` column. Existing rows store `App\Models\Trust`. After move they reference `Fynla\Packs\Gb\Models\Trust` — but the database still says `App\Models\Trust`. Two options:

- **Morph map** (recommended): register a morph map in `GbPackServiceProvider::boot()`:
  ```php
  Relation::morphMap([
      'trust'              => \Fynla\Packs\Gb\Models\Trust::class,
      'protection_policy'  => \Fynla\Packs\Gb\Models\ProtectionPolicy::class,
      // …
  ]);
  ```
  Then a one-shot data migration converts `morphable_type` columns from FQCN to short keys. This is the right long-term answer.
- **Database backfill**: data migration `UPDATE … SET morphable_type = 'Fynla\\Packs\\Gb\\Models\\Trust' WHERE morphable_type = 'App\\Models\\Trust'`. Faster, less elegant.

Plan default: **morph map** — same migration cost, cleaner code, future country-agnostic.

### Verification
- All UK models resolve from new namespace.
- Polymorphic relations work (write test that creates and reads a polymorphic record).
- Pest green.

### Risks
- This is the riskiest mechanical workstream because of polymorphic relations. Allocate the morph map setup as a separate commit *before* moving the first polymorphic-target model.

---

## 7. R-5 — Relocate UK Services: Estate + Tax (3 hr)

### Goal
Largest service batches first. Estate (28 files) + Tax (TaxConfigService, UKTaxCalculator, TaxBandTracker, sub-services).

### Targets

- `app/Services/Estate/` (28 files) → `packs/country-gb/src/Estate/`
- `app/Services/Tax/` → `packs/country-gb/src/Tax/`
- `app/Services/TaxConfigService.php` → `packs/country-gb/src/Tax/TaxConfigService.php`
- `app/Services/UKTaxCalculator.php` → `packs/country-gb/src/Tax/UkTaxCalculator.php`
- `app/Services/TaxBandTracker.php` → `packs/country-gb/src/Tax/TaxBandTracker.php`

### Tasks
Same as R-3/R-4 pattern. Each service moved, namespace updated, every caller updated.

### Update R-1 bindings
Now that the files have moved, update `GbPackServiceProvider`:
```php
$this->app->bind('pack.gb.tax',    \Fynla\Packs\Gb\Tax\TaxConfigService::class);
$this->app->bind('pack.gb.estate', \Fynla\Packs\Gb\Estate\EstateAgent::class);
```

### Verification
- Pest green for tax + estate suites specifically (`pest tests/Unit/Services/Tax/` and `pest tests/Unit/Services/Estate/`).
- Architecture test: `app/Services/Estate/` empty; `app/Services/Tax/` empty.

---

## 8. R-6 — Relocate UK Services: Retirement + Investment + Protection + Savings (3 hr)

Same pattern. ~50 files across four directories.

### Targets

- `app/Services/Retirement/` (13) → `packs/country-gb/src/Retirement/`
- `app/Services/Investment/` (16) → `packs/country-gb/src/Investment/`
- `app/Services/Protection/` (8) → `packs/country-gb/src/Protection/`
- `app/Services/Savings/` (10) → `packs/country-gb/src/Savings/`

### Update bindings
```php
$this->app->bind('pack.gb.retirement', \Fynla\Packs\Gb\Retirement\RetirementService::class);
$this->app->bind('pack.gb.investment', \Fynla\Packs\Gb\Investment\InvestmentService::class);
$this->app->bind('pack.gb.protection', \Fynla\Packs\Gb\Protection\ProtectionService::class);
$this->app->bind('pack.gb.savings',    \Fynla\Packs\Gb\Savings\SavingsService::class);
```

### Verification
- Pest green for each module's test directory.
- Architecture test: each directory under `app/Services/` empty for the relocated modules.

---

## 9. R-7 — Relocate UK Services: Goals + Plans + Coordination (2 hr)

### Targets

- `app/Services/Goals/` (12) → `packs/country-gb/src/Goals/`
- `app/Services/Plans/` (11) → `packs/country-gb/src/Plans/`
- `app/Services/Coordination/` (8) — split:
  - **Stay in core:** `CoordinatingAgent`-orchestration logic (generic). → `core/app/Core/Coordination/`
  - **Move to GB pack:** UK-specific aggregation (e.g. UK-aware net-worth sum that knows about ISAs). → `packs/country-gb/src/Coordination/`

### Verification
- Pest green.

---

## 10. R-8 — Relocate UK Agents (1 hr)

### Targets

`app/Agents/` (9 files):

| Agent | Destination |
|---|---|
| `BaseAgent` | Core (`core/app/Core/Agents/`) |
| `CoordinatingAgent` | Core — orchestrates calls across packs via container |
| `ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`, `TaxOptimisationAgent` | GB pack (`packs/country-gb/src/Agents/`) |

### Refactor CoordinatingAgent
The existing `CoordinatingAgent` currently calls UK agents directly. Refactor to resolve them via container:
```php
$protectionAgent = app("pack.{$user->primaryJurisdictionCode()}.protection");
```

This is the change that makes country addition truly additive — `CoordinatingAgent` will work for IE / AU / NZ packs once they bind their own `pack.{cc}.protection`.

### Verification
- Pest green.
- Coordination tests pass.

---

## 11. R-9 — Relocate UK Controllers + Requests + Resources + Observers (4 hr)

### Targets

- `app/Http/Controllers/Api/` (~80 UK files; some are core — `AuthController`, `UserController`, `HouseholdController`, `GoalController`, `LifeEventController`) — split:
  - Core controllers stay (auth, user, household, goal, life event, billing, settings, onboarding).
  - UK module controllers move (Tax, Estate, Retirement, Investment, Protection, Savings, Plans).
- `app/Http/Requests/` (~80 UK form requests) — move per their controller.
- `app/Http/Resources/` (12) — move with their models.
- `app/Observers/` (12) — move UK-specific (RiskRecalculationObserver, etc.); generic stay in core.

### Update routes
`routes/api.php` — UK routes get split out:
- Core routes stay in `routes/api.php`.
- UK routes move to `packs/country-gb/routes/api.php`, mounted by `GbPackServiceProvider::boot()`:
  ```php
  Route::middleware(['auth:sanctum', 'active.jurisdiction', 'pack.enabled:gb'])
      ->prefix('api/gb')                    // see Spec § 6.1, Option X
      ->group(__DIR__.'/../routes/api.php');
  ```
- Add a redirect layer for legacy unprefixed UK URLs (`/api/protection/*` → `/api/gb/protection/*`) to avoid breaking mobile clients in flight. The redirect layer ships in R-14.

### Verification
- Pest feature tests pass (these hit HTTP routes).
- Architecture test: `app/Http/Controllers/Api/` contains only core controllers.

### Risks
- Sanctum middleware behaviour in pack-mounted routes. Verify the auth middleware still resolves correctly. Likely fine; flag if it breaks.

---

## 12. R-10 — Migrations + Seeders (1 hr)

### Targets

- `database/migrations/` — UK tables' migration files relocate to `packs/country-gb/database/migrations/`.
- `database/seeders/` — UK seeders relocate (`TaxConfigurationSeeder`, `TaxProductReferenceSeeder`, `ActuarialLifeTablesSeeder`, `SavingsMarketRatesSeeder`).
- Core seeders stay (`HouseholdSeeder`, `TestUsersSeeder`, `ChrisUserSeeder`, `JurisdictionSeeder`).

### Tasks
1. Move files.
2. Register pack migration paths in `GbPackServiceProvider::boot()`:
   ```php
   $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
   ```
3. Register pack seeders so `php artisan db:seed` discovers them. Seeders are class-resolved so they keep working as long as `composer dump-autoload` ran.

### Important: do NOT rename UK tables
Spec § 7 deferred the `gb_` prefix migration. Tables keep current names (`isa_contributions`, `lta_records`, etc.). Renaming is a separate, optional, post-Phase-1 workstream.

### Verification
- `php artisan migrate:status` — all migrations recognised.
- `php artisan db:seed` — green.
- Pest green.

---

## 13. R-11 — 4 Missing UK Contract Bindings (2 hr)

### Goal
Replace the Null bindings from R-1 with real UK implementations. Brings UK to contract symmetry with ZA.

### Targets

1. `Fynla\Packs\Gb\Localisation\GbLocalisation` implements `Fynla\Core\Contracts\Localisation`:
   - `formatMoney(int $minor): string` — `£1,234.56`
   - `formatDate(\DateTimeInterface $d): string` — `DD/MM/YYYY`
   - `currencyCode(): 'GBP'`, `locale(): 'en_GB'`
2. `Fynla\Packs\Gb\Validation\NinoValidator` implements `IdentityValidator` (UK National Insurance number — 9-character pattern).
3. `Fynla\Packs\Gb\Validation\GbBankingValidator` implements `BankingValidator` (sort code 6 digits + 8-digit account number).
4. `Fynla\Packs\Gb\LifeTables\GbLifeTableProvider` implements `LifeTableProvider` (ONS data — already seeded by `ActuarialLifeTablesSeeder`).

### Verification
- Each binding resolvable from container; `instanceof` the contract.
- 4 unit tests, one per binding.

---

## 14. R-12 — Per-Pack `navigation()` Providers (2 hr)

### Goal
Sidebar reads from per-pack `navigation()`. Core's `MODULES_BY_JURISDICTION` constant is deleted.

### Tasks

1. Each pack ships a `navigation.js` (or PHP equivalent if read server-side) returning its sidebar manifest:
   ```js
   // packs/country-gb/resources/js/navigation.js
   export default function navigation() {
     return {
       rootItems: [
         { key: 'gb-dashboard', label: 'Dashboard', icon: 'home', to: '/dashboard' },
         { key: 'gb-net-worth', label: 'Net Worth', icon: 'chart-bar', to: '/net-worth/wealth-summary' },
       ],
       sections: {
         cashManagement: [...],
         finances: [...],
         family: [...],
         planning: [...],
       },
     };
   }
   ```
2. Same shape for `packs/country-za/resources/js/navigation.js` (relocate from current `MODULES_BY_JURISDICTION.za`).
3. Frontend `PackRegistry` (Vuex module) collects each active pack's navigation on import:
   ```js
   // core/store/modules/jurisdiction.js
   import { mergeBySectionKey } from '@/core/utils/navigationMerge';

   getters: {
     sidebarSections: (state, getters, rootState) => {
       const navs = state.active.map(code => rootState.packs[code]?.navigation()).filter(Boolean);
       return mergeBySectionKey(navs);
     }
   }
   ```
4. Delete `MODULES_BY_JURISDICTION` constant from `resources/js/store/modules/jurisdiction.js`.
5. `SideMenu.vue` continues to render from `sidebarSections` getter — no template change.
6. SECTION_ORDER and SECTION_LABELS constants stay in core (these are agnostic — section keys, not country-specific items).

### Verification
- UK-only user sees same sidebar as production today (no regression).
- SA-only user sees same sidebar shape, SA items.
- Architecture test: `MODULES_BY_JURISDICTION` no longer exists in core; grep returns zero matches.

---

## 15. R-13 — Frontend Relocation (6 hr)

Largest workstream. Mostly mechanical: move Vue files into the GB pack bundle, update import paths.

### Targets

`resources/js/` audit:

| Path | Action |
|---|---|
| `components/Auth/`, `components/Dashboard/`, `components/Layout/`, `components/Settings/`, `components/Onboarding/`, `components/Shared/` | Stay (or move to `core/`) |
| `components/Protection/`, `components/Savings/`, `components/Investment/`, `components/Retirement/`, `components/Estate/`, `components/Goals/`, `components/Plans/`, `components/NetWorth/`, `components/Property/`, `components/Trust/`, `components/Holistic/`, `components/WhatIf/`, `components/Risk/` | Move to `packs/country-gb/resources/js/components/` |
| `components/ZA/` | Move to `packs/country-za/resources/js/components/` (mirror move for SA) |
| `views/` | Audit each — most route views are UK-specific and move to GB pack; auth/settings/dashboard views stay |
| `store/modules/` | Audit each — UK-specific (savings, investment, retirement, …) move to GB pack; jurisdiction, auth, household, goals stay in core |
| `services/` | Same audit |
| `mixins/currencyMixin.js` | Stay (generalise to consult Localisation binding) |

### Migration approach

Stage by module, not en masse:

1. Move all `components/Estate/` files into `packs/country-gb/resources/js/components/Estate/`.
2. Update every import path: `from '@/components/Estate/X.vue'` → `from '@/packs/gb/components/Estate/X.vue'`. Use Vite alias `@gb` or update `@/components/Estate` to resolve from the pack via vite.config.
3. Run dev server, click through the Estate module in browser, verify it works.
4. Commit: "refactor(uk-pack): relocate Estate frontend".
5. Repeat for Investment, Retirement, Protection, Savings, Goals, Plans, NetWorth.

### Vite config

Pack bundles must dynamic-import after auth:

```js
// resources/js/app.js
async function bootstrap() {
  await store.dispatch('auth/fetchUser');
  const active = store.getters['jurisdiction/active'];

  if (active.includes('gb')) {
    const gb = await import('@/packs/gb');
    gb.default(router, store);            // pack registers routes + Vuex modules + navigation
  }
  if (active.includes('za')) {
    const za = await import('@/packs/za');
    za.default(router, store);
  }

  app.mount('#app');
}
```

### Verification
- Network tab: SA-only user does not download `packs-gb-*.js`. UK-only user does not download `packs-za-*.js`.
- Bundle size: SA-only user's total JS materially smaller than today.
- Pure UK Playwright journey passes.
- Pure SA Playwright journey passes.

### Risks
- Mid-migration breakage. Vite HMR will fail on broken imports. Stage carefully, one module at a time.
- `@/` alias resolution. Verify vite.config.js correctly resolves `@/packs/gb/...`.

---

## 16. R-14 — Routing Realignment (3 hr)

### Goal
Backend routes use the country prefix (Spec § 6.1 Option X). Frontend routes drop the `/za/*` prefix from SA URLs (these live inside the SA pack scope; for SA-only users they're unprefixed). Legacy URLs redirect with deprecation.

### Backend tasks
1. UK routes mount at `/api/gb/*` (per R-9 step). Existing unprefixed routes (`/api/protection/*`) are kept alive with a 301 redirect to `/api/gb/protection/*`. Redirect layer is a single core middleware that runs before pack middleware.
2. SA routes already at `/api/za/*` — no change.
3. Cross-border routes reserved at `/api/global/*` — Phase 3.

### Frontend tasks
1. SA pack registers same URL paths as UK pack (`/savings`, `/protection`, etc.). For a SA-only user the URLs are unprefixed.
2. Legacy `/za/*` URLs (one-time soft-redirect in core's catch-all):
   ```js
   { path: '/za/:rest*', redirect: to => ({ path: '/' + to.params.rest, query: to.query }) }
   ```
3. Architecture test: no `/za/` literal in `packs/country-za/resources/js/routes.js`.

### Verification
- UK clients on the old `/api/protection` URLs get a 301 → new URL, then 200 — transparent.
- Mobile app continues to work on old URLs (relies on follow-redirect default in fetch/axios).
- SA pack's routes are unprefixed for the SA-only user.

### Risks
- 301 redirects strip POST bodies in some clients. Use 308 (Permanent Redirect) for non-GET methods.

---

## 17. R-15 — Full Regression (3 hr)

### Pest
- `./vendor/bin/pest` — all 940+ existing UK tests + 400+ SA tests pass.
- Architecture suite green: every assertion now active (no skipped rules).

### Playwright — UK journey
Login as `john@example.com`. Navigate every UK module. Submit a form per module. Verify no SA references appear. Verify network tab does not include `packs-za-*.js`.

### Playwright — SA journey
Login as `za-protection-test@example.com`. Navigate every SA module via unprefixed URLs (`/savings`, `/protection`, …). Submit a form per module. Verify no UK references. Verify network tab does not include `packs-gb-*.js`.

### Playwright — dual smoke
Seed a dual-jurisdiction test user. Login. Verify sidebar renders without crashing. Verify the **country of residence**'s pack owns the URL routing for shared-name URLs. (Phase 2 will deliver foreign-asset surfacing inside residence-pack views; Phase 1 expects residence-pack routes to load and the sidebar to render.)

### Architecture test ratchet
At this point, every pack-boundary assertion is active and passing. Cross-pack import attempts fail the build.

---

## 18. Decision Points (need user answer before PRD)

These three are tactical and the plan defaults to one answer; flag if the user wants the other:

1. **Backend URL strategy** — plan default: **Option X (country-prefixed `/api/gb/*`, `/api/za/*`) with a redirect layer**. Alternative: Option Y (unprefixed with jurisdiction middleware). Option X is the original architecture; Option Y adds a runtime routing layer. **Decision: stick with X?**
2. **UK table renames** — plan default: **defer**. UK tables keep current names; optional follow-up workstream renames to `gb_*`. Renaming 50+ UK tables in this branch is high-risk for low immediate benefit. **Decision: defer to a follow-up?**
3. **Branch strategy** — plan default: **single long-lived `refactor/uk-pack-relocation` branch**, all 15 workstreams ship as commits, branch merges atomically. Alternative: 15 small PRs landing serially on main. The relocation creates intermediate states that are not deployable to production until the branch is whole — single long-lived branch is the only sensible choice. **Decision: single long-lived branch?**

---

## 19. Out of Scope (for this plan)

- Cross-border pack (Phase 3 — depends on UK + SA both being proper packs, which this plan delivers)
- Dual-user UX (Phase 2 — `<CountryView>`, `<Money>` localisation, residence-based pack default with foreign-asset surfacing inside residence-pack views, vue-i18n pack scoping)
- Geo-registration verification (Phase 2)
- New SA workstreams (1.6b SA Estate, 1.7 personas, 1.8 FAIS/POPIA — paused; resume after relocation)
- Cleanup PRs (DialogContainer, Tabs.vue) — paused; resume after relocation
- UK table prefix migration (`gb_*`) — optional follow-up

---

## 20. Next Step

User-amended workflow for this refactor: spec → plan → **inline audit + amendments** → implement. (Formal PRD via `/prd-writer` skipped — user's explicit choice for this mechanical refactor; audit step replaces it.)

**Spec:** `Plans/architecture-spec-v3.md` ✅
**Plan:** this document ✅ (audit amendments captured in § 0 above)
**Inline audit:** complete (2026-05-06 session 2, `feature-dev:code-explorer` report) ✅
**Next:** Cut `refactor/uk-pack-relocation` from `main`. Cherry-pick TestUsersSeeder jurisdiction-pinning portion of `0adf82b`. Begin R-0.
