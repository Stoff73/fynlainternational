---
name: prd-writer
description: Generate a production-ready PRD for a Fynla International feature by first validating an existing spec and plan against the live codebase (finding conflicts, gaps, cross-purpose planning, missing integrations, and multi-country/pack architecture violations), then running a rolling interview with the user to resolve every ambiguity, amending the spec and plan as needed, and only then writing the PRD in the canonical 9-section format. Use whenever the user says "write a PRD", "generate the PRD", "create PRD from spec", "turn this plan into a PRD", or hands over spec/plan paths and asks for product requirements documentation. Also trigger when the user mentions "requirements document", "product spec doc", "formalise a feature", or wants engineering-ready requirements before implementation starts. This skill ONLY works when a spec AND plan already exist — if either is missing, point the user at `superpowers:brainstorming` (spec) or `superpowers:writing-plans` (plan) first.
---

# PRD Writer (Fynla International)

Produce a rigorous, codebase-validated PRD from an existing spec and plan. The skill refuses to accept the spec and plan at face value — it drives a subagent-led audit of the real codebase, surfaces every inconsistency, interviews the user to resolve them, and only writes the PRD once the spec, plan, and codebase are in mutual agreement.

**Project root:** `/Users/CSJ/Desktop/fynlaInternational`
**Vault:** `/Users/CSJ/Desktop/FynlaInter/FynlaInter/` (Obsidian — design guide, deploy notes, session history)

This skill runs from the Fynla International project — a multi-country evolution of the UK Fynla product. UK code lives in `app/`, South Africa (and future country) code lives in `packs/country-{iso}/`, country-agnostic infrastructure lives in `core/app/Core/`. The 12 contracts in `core/app/Core/Contracts/` enforce the boundary. Plans that ignore this separation — e.g. adding SA logic to `app/`, hardcoding jurisdiction in core, duplicating tax tables — are structural bugs the audit must catch.

## Why this skill exists

Specs and plans are written in isolation from the running code. They drift. They assume routes, services, tables, or components that have been renamed, removed, or refactored. They duplicate existing functionality, violate the design guide, or conflict with architectural patterns (Agents/Services/Controllers layering, pack isolation, PreviewWriteInterceptor, TaxConfigService / ZaTaxConfigService, joint ownership trait, Money value object, TaxYear abstraction, jurisdiction middleware, etc.). Shipping a PRD built on a stale spec means engineers build the wrong thing, and the user pays the cost in a cycle of rework.

The fix is: **validate before documenting**. This skill does that.

## Prerequisites

The skill refuses to proceed if either is missing:

- **Spec** — typically in one of:
  - `/Users/CSJ/Desktop/fynlaInternational/docs/superpowers/specs/YYYY-MM-DD-{feature}-design.md`
  - `/Users/CSJ/Desktop/fynlaInternational/Plans/` (macro/architecture specs like `Implementation_Plan_v2.md`, `SA_Research_and_Mapping.md`, `multi_country_architecture.md`)
  - `/Users/CSJ/Desktop/fynlaInternational/April/{Month}{D}Updates/` (day-stamped session outputs and handovers)
  - `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/{Month}{D}Updates/` (vault copies)
- **Plan** — typically in one of:
  - `/Users/CSJ/Desktop/fynlaInternational/docs/superpowers/plans/YYYY-MM-DD-{feature}.md`
  - `/Users/CSJ/Desktop/fynlaInternational/Plans/`
  - `/Users/CSJ/Desktop/fynlaInternational/April/{Month}{D}Updates/`

If only a spec exists → tell the user to run `superpowers:writing-plans` first. If only a plan exists → tell the user to run `superpowers:brainstorming` first. Do not synthesise missing inputs.

---

## Workflow

### Phase 1 — Locate and read inputs

1. Ask the user for the feature name (or accept explicit paths). Do not guess.
2. Search all known locations in order:
   ```
   /Users/CSJ/Desktop/fynlaInternational/docs/superpowers/specs/*{feature}*
   /Users/CSJ/Desktop/fynlaInternational/docs/superpowers/plans/*{feature}*
   /Users/CSJ/Desktop/fynlaInternational/Plans/*{feature}*
   /Users/CSJ/Desktop/fynlaInternational/April/*Updates/*{feature}*
   /Users/CSJ/Desktop/FynlaInter/FynlaInter/April/*Updates/*{feature}*
   ```
3. If zero or multiple matches, show the candidates and ask the user to pick.
4. Read both documents in full. Extract into a working note (in your head, not a file):
   - Feature summary and stated scope
   - Which pack(s) the feature targets — UK (`app/`), SA (`packs/country-za/`), core (`core/app/Core/`), cross-border, or frontend-only
   - Entities/models the spec claims to create or modify (and which pack they live in)
   - Routes and API endpoints mentioned (UK routes at `/api/*`, SA at `/api/za/*`, global at `/api/global/*`)
   - Vue components mentioned (UK in `resources/js/components/`, SA in `resources/js/components/ZA/`)
   - Services, agents, observers mentioned
   - Database columns/migrations mentioned (UK tables unprefixed, SA tables `za_*`, core tables unprefixed)
   - Whether Money value object / TaxYear abstraction / jurisdiction scoping apply
   - External integrations (Revolut, Awin, Anthropic, MaxMind/Cloudflare geo, etc.)
   - Stated success criteria or acceptance criteria (if any)
   - Files in the plan's change list

### Phase 2 — Assess scope and dispatch the codebase validation audit

Classify the feature's integration depth to pick the right validation approach:

| Scope | Signals | Agent strategy |
|-------|---------|----------------|
| **Small** | 1-3 files, one Vue component or one service method, UI-only tweak, no DB change | `Explore` (medium thoroughness) — one dispatch |
| **Medium** | 4-15 files, 1-2 modules touched, possibly a migration, CRUD against existing models | `feature-dev:code-explorer` — one dispatch |
| **Large** | 15+ files, cross-module, new agent/service, new table, new external integration, changes to shared patterns (auth, subscription, tax, estate, joint ownership) | `feature-dev:code-explorer` AND `feature-dev:code-architect` in parallel |

The validation agent must produce a **Validation Report** covering these areas. Pass this verbatim in the prompt so the agent knows what to look for:

1. **Entity conflicts** — do the models/tables/columns the spec claims to create or modify already exist? With different names or shapes? For SA work, check `za_` prefix is used and table isn't already in `app/Models/`.
2. **Route conflicts** — do the API endpoints already exist? Do they already return/accept different data? Does the plan use the correct prefix: UK `/api/*`, SA `/api/za/*`, cross-border `/api/global/*`?
3. **Component conflicts** — do the Vue components already exist? Are they in a different module directory? Do they already have a different responsibility? SA components must live under `resources/js/components/ZA/` and be lazy-loaded.
4. **Pack/architecture conflicts** — does the plan violate the multi-country boundary?
   - UK code stays in `app/` — no moving files into `packs/country-gb/`, no compatibility aliases (see `docs/adr/ADR-003-strict-pack-isolation.md` and the failed Workstream E history)
   - SA code belongs in `packs/country-za/` — not in `app/`
   - `core/app/Core/` is country-agnostic — no `if ($country === 'gb')` logic, no hardcoded tax rates, no UK or SA terminology
   - New country-specific behaviour must go through one of the 12 contracts in `core/app/Core/Contracts/` (TaxEngine, RetirementEngine, InvestmentEngine, ProtectionEngine, EstateEngine, ExchangeControl, IdentityValidator, Localisation, BankingValidator, PaymentProcessor, LifeTableProvider, CountryPack)
   - Service resolution uses container keys `pack.{iso}.{contract}` (e.g. `pack.gb.tax`, `pack.za.retirement`) — not direct class instantiation across pack boundaries
   - Jurisdiction-scoped API routes must go through `ActiveJurisdictionMiddleware` / `EnsurePackEnabled`
5. **Money & tax-year abstractions** — does the plan respect the value-object conventions?
   - Monetary amounts should use `Fynla\Core\Money\Money` (integer minor units + currency enum), not raw decimals — see `docs/adr/ADR-005-money-value-object.md`
   - Models storing money use the `HasMoney` trait and the shadow-column migration pattern (`*_minor` + `*_currency`)
   - Tax-year-scoped logic uses `Fynla\Core\TaxYear\TaxYear` + `TaxYearResolver`, not string comparisons — see `ADR-006-semantic-tax-year.md`
6. **Pattern conflicts** — does the plan violate Fynla conventions?
   - All UK tax values via `TaxConfigService` / `taxConfig.js`; all SA tax values via `ZaTaxConfigService` (when the ZA pack exists). No hardcoded years, allowances, thresholds.
   - Joint ownership uses `HasJointOwnership` trait and single-record pattern (`joint_owner_id` + `ownership_percentage`)
   - Form modals emit `save` not `submit`
   - No banned colours (amber, orange, primary-*, secondary-*, gray-* for general UI)
   - Preview user isolation (`is_preview_user = true`)
   - `PreviewWriteInterceptor` — new auth POST routes need `EXCLUDED_ROUTES` entry
   - User-facing text spells out acronyms (except ISA). SA acronyms to watch: TFSA, RA, PF, PvF (spell out first use), SDA, FIA, AIT, DTA, QROPS.
   - No scores in user-facing UI
   - Canonical ownership enum values (`individual`, `joint`, `tenants_in_common`, `trust`) — never `sole`
7. **Jurisdiction / cross-border plumbing** — for any feature touching assets, protection policies, pensions, investments, savings, or estate objects:
   - New asset tables should have a `country_code` CHAR(2) nullable column
   - Auto-detection: the `JurisdictionDetectionObserver` (Workstream 0.6) activates foreign jurisdictions when asset `country_code` differs from user's active jurisdictions — does the plan wire into this?
   - Does the plan correctly treat the UK user as `jurisdiction = GB` (not the absence of jurisdiction)?
   - Does the plan avoid exposing the word "jurisdiction" in user-facing UI? (Users think in "where is my pension", not "jurisdiction.")
8. **Cross-purpose planning** — does the plan solve one problem but create another? e.g. adding a column that duplicates data already computed elsewhere, building a second notification path when one exists, creating a new state store for data already in Vuex, building a "country selector" when geolocation is the entry point.
9. **Design system compliance** — do the UI changes align with `fynlaDesignGuide.md` (current version in the vault) colours, typography, and component patterns? Any new CSS patterns that duplicate global classes?
10. **Test and seed impact** — does the plan require new seeders, factories, or test coverage? Does it risk breaking existing seeds (especially preview personas)? For SA work: does it land a `ZaPreviewUserSeeder` / `ZaActuarialLifeTablesSeeder` / `ZaTaxConfigurationSeeder`?
11. **Missing integrations** — what does the plan fail to mention that the real codebase will require? Observers that will need updating? Audit log entries? Cache invalidation? Vuex store updates? Sidebar/router entries? Jurisdiction state updates in the session endpoint? Lazy-loaded ZA route registration?
12. **Gaps between spec and plan** — where does the plan fail to implement something the spec promises, or where does the plan do something the spec didn't ask for?

Example dispatch (medium scope):

```
Agent(
  description: "Validate spec/plan against codebase",
  subagent_type: "feature-dev:code-explorer",
  prompt: """
  Project root: /Users/CSJ/Desktop/fynlaInternational
  This is Fynla International — a multi-country codebase. UK code in `app/`, SA code (when built) in `packs/country-za/`, country-agnostic infra in `core/app/Core/`. Read `/Users/CSJ/Desktop/fynlaInternational/CLAUDE.md` and `/Users/CSJ/Desktop/fynlaInternational/Plans/Implementation_Plan_v2.md` for context before starting.

  Validate the following spec and plan against the Fynla International codebase. Report conflicts, gaps, and cross-purpose planning. This is NOT a code review of new code — the code doesn't exist yet. Instead, check whether what the spec and plan describe is consistent with what's already in the codebase.

  Spec: <full spec text>
  Plan: <full plan text>

  Produce a Validation Report with these sections (use exact headings):
  1. Entity conflicts
  2. Route conflicts
  3. Component conflicts
  4. Pack/architecture conflicts (UK stays in app/, SA in packs/country-za/, core is country-agnostic, 12 contracts, pack.{iso}.* container keys, jurisdiction middleware)
  5. Money & tax-year abstractions (Money VO, HasMoney trait, TaxYear + TaxYearResolver)
  6. Pattern conflicts (TaxConfigService / ZaTaxConfigService, joint ownership trait, form save emit, banned colours, preview isolation, PreviewWriteInterceptor, acronyms, no-scores rule, canonical ownership enums)
  7. Jurisdiction / cross-border plumbing (country_code column, JurisdictionDetectionObserver, auto-activation, no user-facing "jurisdiction" terminology)
  8. Cross-purpose planning
  9. Design system compliance (fynlaDesignGuide.md — check current version in the FynlaInter vault)
  10. Test and seed impact (including ZA seeders where relevant)
  11. Missing integrations (observers, audit log, cache invalidation, Vuex, sidebar/router, session endpoint jurisdiction state, ZA route lazy-loading)
  12. Gaps between spec and plan

  For each finding: cite the exact file:line in the codebase, quote the relevant spec/plan passage, and state the specific conflict or gap. Do not speculate. If a claim checks out, say "no issue found" — do not pad.

  Also identify ambiguities — places where the spec or plan is unclear, contradictory, or leaves a decision unmade. The user will be interviewed about these.
  """
)
```

For large scope, dispatch `feature-dev:code-architect` in parallel with a prompt focused on architectural fit:

```
Agent(
  description: "Architectural review of proposed plan",
  subagent_type: "feature-dev:code-architect",
  prompt: """
  Project root: /Users/CSJ/Desktop/fynlaInternational
  Context: Fynla International's three-layer architecture —
    - `app/` (UK code, untouched, 655+ PHP files, Agents → Services → Controllers → Models)
    - `core/app/Core/` (country-agnostic: 12 Contracts, Money, TaxYear, PackRegistry, Jurisdiction, Middleware)
    - `packs/country-{iso}/` (country-specific Composer packages implementing the contracts; SA pack TBD in Phase 1)

  Read `/Users/CSJ/Desktop/fynlaInternational/Plans/Implementation_Plan_v2.md` and the relevant ADRs in `docs/adr/` before starting.

  Review the attached plan against this architecture. Does the plan's proposed structure fit? Are there existing services/agents that should be reused rather than duplicated? Is the layering correct? Does it respect module boundaries (Protection, Savings, Investment, Retirement, Estate, Goals, Coordination)? Does it respect country-pack boundaries (UK belongs in app/, SA in packs/country-za/, shared infra in core/)?

  For anything hitting country-specific logic, check the plan resolves services through `pack.{iso}.*` container keys rather than direct class instantiation, and verify it implements (or extends) the right contract from `core/app/Core/Contracts/`.

  Spec: <full spec text>
  Plan: <full plan text>

  Output: a list of architectural concerns with specific remediations. Call out any ADR violations by ADR number. If the architecture is sound, say so.
  """
)
```

### Phase 3 — Present findings and begin the rolling interview

Present the Validation Report to the user in a compact, readable form. Group findings by severity:

- **🔴 Conflict** — spec/plan is wrong about the codebase; must be corrected
- **🟡 Ambiguity** — spec/plan is silent on something the codebase requires a decision for
- **🟢 Gap** — spec and plan don't agree, or plan missed something the spec requires

Then **interview the user in rolling batches of 2-3 questions**. Not 15 at once — the user will skim and miss things. Rolling interview:

1. Ask 2-3 questions targeted at the highest-severity items
2. Wait for answers
3. Use the answers to narrow the next batch (some questions may no longer apply)
4. Repeat until every 🔴 and 🟡 is resolved
5. For 🟢 items, confirm the intended behaviour with the user

Each question must be specific and actionable. Good: *"The spec says 'Fyn will create a savings account' but the plan has no Vuex action for `savings/createAccount` — did you intend the existing `savings/addAccount` action, or is this a new path?"* Bad: *"Any thoughts on the savings module?"*

**If the user's answer contradicts the spec or plan, say so explicitly and flag it for amendment in Phase 4.** Do not silently reconcile.

### Phase 4 — Amend the spec and the plan

Once every open question is answered, update both source documents in place:

1. Show the user the exact diffs you intend to apply (as patches, not descriptions)
2. Ask for explicit approval to apply them
3. On approval, use the `Edit` tool to apply changes. Never rewrite the files wholesale — targeted edits preserve authorial voice.
4. Add a `Status` line or update the existing one: `**Status:** Amended — {today's date} — conflicts resolved against codebase audit`
5. Confirm to the user what was changed and where

If the user wants to push back on any amendment, accept it — they may know something about upcoming work the audit didn't capture. Update your understanding accordingly, but do not write the PRD until the user explicitly confirms the spec and plan are final.

### Phase 5 — Write the PRD

Use the template in the next section. Every section must be populated from the (now-validated) spec, plan, interview answers, and codebase context. Do not invent content to fill a section — if a section legitimately has nothing to say, write `_Not applicable — {one-line reason}_`.

Rules for PRD content:

- **Grounded in the real codebase.** Reference specific models, routes, components, and services by name, with file paths relative to `/Users/CSJ/Desktop/fynlaInternational/`. Generic PRDs are useless.
- **Be explicit about which pack the feature lives in.** UK → `app/`, SA → `packs/country-za/` (or planned location if pack not yet built), cross-country infra → `core/app/Core/`, cross-border → `packs/cross-border/` (Phase 2).
- **British spelling in user-facing text, American in code.** (Optimisation / optimize, Customise / customize.) SA user-facing text follows en-ZA conventions once the SA pack ships — same British-style spelling but SA-specific terminology (e.g. "retirement annuity" not "SIPP", "estate duty" not "IHT", "TFSA" not "ISA").
- **Tax values are symbolic, not numeric.** Write "the current ISA annual allowance (from `TaxConfigService`)" not "£20,000"; write "the current SA retirement deduction cap (from `ZaTaxConfigService`)" not "R350,000". The PRD should outlive a tax year rollover in either country.
- **Currency handling is jurisdiction-aware.** Use `Fynla\Core\Money\Money` for all monetary amounts when the feature touches model storage; state the currency enum value (`Currency::GBP`, `Currency::ZAR`). Never hardcode a currency symbol.
- **Acronyms spelled out** (except ISA). SA acronyms to watch: TFSA, RA, PF, PvF, SDA, FIA, AIT, DTA, QROPS, POPIA, FAIS — spell out first use.
- **No scores** in user-facing metrics — use currency, percentages, or time periods.
- **Design decisions reference `fynlaDesignGuide.md`** (current version in the FynlaInter vault) colours and patterns rather than restating them.
- **Prioritise functional requirements** using `Must-have` / `Should-have` / `Nice-to-have`. Be ruthless — if everything is must-have, the PRD provides no guidance.
- **Cross-border behaviour is implicit.** If a feature could activate a new jurisdiction (e.g. adding an asset with a foreign location), note the expected observer behaviour and the user-facing notification — don't paper over auto-detection.

### Phase 6 — Save the PRD

1. **Primary location** — save inside the project's month-updates folder:
   `/Users/CSJ/Desktop/fynlaInternational/April/{MonthName}{D}Updates/` where `D` is the day without a leading zero (e.g. `April1Updates`, `April17Updates`).
2. If the folder doesn't exist, create it.
3. Name the file `PRD-{feature-kebab-case}.md` (match the spec/plan kebab case).
4. Write the file.
5. **Vault mirror (optional, ask first)** — if the user wants it available in the Obsidian vault, also copy to `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/{MonthName}{D}Updates/`. Don't mirror without being asked — the vault is for cross-session reference, not every artefact.
6. Report to the user:
   - Path to the saved PRD
   - Paths to the amended spec and plan
   - One-line summary of the most material changes

---

## PRD Template

Use this exact structure. Embed the template in the output verbatim, filling every section. Maintain the heading levels.

```markdown
# PRD — {Feature Title}

**Project:** {Feature Title}
**Owner:** {User's name or "CSJ" if unknown}
**Status:** Draft
**Date:** {today, DD Month YYYY}
**Spec:** `{path to amended spec}`
**Plan:** `{path to amended plan}`
**Codebase audit:** Completed {today} — see Risks & Dependencies for residual concerns

---

## 1. Context & Why

### Problem
{What's broken, missing, or painful in Fynla today. Be specific — cite the module and the user experience. Avoid "users want X" framing; explain the friction.}

### Business case
{Why now? What strategic goal does this serve? Tie to revenue, retention, compliance, trust, or unlock-for-future-work. If the connection is weak, say so — don't manufacture importance.}

### Strategic fit
{Which of the 7 Fynla modules (Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination) does this touch, and in which jurisdiction(s) / pack(s)? How does it relate to recently shipped or upcoming work? Where does it fit on the Implementation_Plan_v2.md roadmap — Phase 0 (foundation), Phase 1 (SA pack), Phase 2 (cross-border), Phase 3+ (additional countries)? Reference prior deploys, CSJTODO items, or recent April{D}Updates handovers if relevant.}

---

## 2. Target Persona

{Pick from Fynla's seeded personas where applicable. Available personas depend on which packs the feature touches:

- **UK personas (GB pack, seeded):** young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple
- **SA personas (ZA pack, Phase 1 — planned):** young professional, young family, peak earners, pre-retiree, retiree, expat (see `Plans/SA_Research_and_Mapping.md`)
- **Dual-jurisdiction:** users with assets in both countries — cross-border activated automatically

Explain which persona(s) feel this pain most acutely and in which jurisdiction. If the feature is for advisors or admins, say so explicitly. If it's infrastructure (no user-facing change — e.g. Phase 0 jurisdiction plumbing, Money VO migration), write "Infrastructure — indirectly benefits all personas" and explain how.}

**Primary:** {persona + jurisdiction + why}
**Secondary:** {persona + jurisdiction + why, or "None"}

---

## 3. Success Metrics (KPIs)

{Concrete, measurable, with a target and a measurement window. Prefer metrics Fynla can actually measure — database counts, API response times, user action rates, error rates — over metrics requiring new analytics infrastructure. If new measurement is needed, flag it as a dependency in section 9.}

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| {e.g. % of users completing X flow} | {current or "unknown"} | {target %} | {how measured, when} |

---

## 4. User Stories & Scenarios

### User stories
{Use "As a [persona], I want to [action] so that [benefit]" format. Group by persona if multiple. Cover the primary journey and the main variations.}

- As a **{persona}**, I want to **{action}** so that **{benefit}**.
- ...

### Key scenarios
{Narrative walkthroughs of 2-4 representative journeys. Include the unhappy path — what happens if validation fails, if the user aborts, if they're in preview mode.}

**Scenario 1 — {name}:**
1. {Step}
2. {Step}
3. {Expected outcome}

---

## 5. Functional Requirements

Prioritised using MoSCoW. Each requirement references the module and the specific backend/frontend touchpoints from the plan.

### Must-have
- **FR-M1:** {Requirement}. _Touches: `{component or service}`._
- **FR-M2:** ...

### Should-have
- **FR-S1:** {Requirement}. _Touches: ..._

### Nice-to-have
- **FR-N1:** {Requirement}. _Touches: ..._

---

## 6. User Flow & UX/Design

### Flow
{Either a numbered flow or an ASCII/mermaid diagram. Reference actual route paths and component names from the plan. Show the happy path and call out where the unhappy path branches.}

### UX/Design notes
- **Design system:** Uses `fynlaDesignGuide.md` (current version in `/Users/CSJ/Desktop/FynlaInter/FynlaInter/`) — {call out specific colour tokens, typography choices, or component patterns being applied}
- **Jurisdiction visibility:** {does this feature appear for all users, or only for users with a specific active jurisdiction? If SA-only, note lazy-loading via `resources/js/components/ZA/`. If cross-border, note activation trigger.}
- **Reusable components:** {list existing components being reused, e.g. `FormModal.vue`, `AccountForm.vue`}
- **New components (if any):** {list with purpose and file path — e.g. `resources/js/components/ZA/TfsaTracker.vue`}
- **Responsive behaviour:** {mobile/tablet/desktop expectations, or "standard responsive — no special treatment"}
- **Accessibility:** {keyboard nav, ARIA, focus management considerations — especially for modals and forms}
- **Reference artefacts:** {paths to screenshots, whiteboard images, sample data, or prior art in the FynlaInter vault at `/Users/CSJ/Desktop/FynlaInter/FynlaInter/`}

---

## 7. Out of Scope

{Explicit list of things this feature is NOT doing. Each item should be something a reasonable reader might otherwise assume is in scope. Don't list obviously unrelated things — that's noise.}

- {Thing 1}
- {Thing 2}
- ...

---

## 8. Risks & Dependencies

### Risks
| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| {e.g. Migration on large table locks production} | {Low / Med / High} | {Low / Med / High} | {Specific plan} |

### Technical dependencies
- {Existing service, trait, contract, or pattern this relies on — e.g. `TaxConfigService`, `ZaTaxConfigService`, `HasJointOwnership`, `HasMoney`, `Fynla\Core\Money\Money`, `Fynla\Core\TaxYear\TaxYear`, `ActiveJurisdictionMiddleware`, one of the 12 contracts in `core/app/Core/Contracts/`}
- {Core infrastructure readiness — e.g. "requires Workstream 0.6 (`country_code` columns + JurisdictionDetectionObserver) to be complete"}
- {External service, e.g. Revolut API, Anthropic API, MaxMind GeoLite2, Cloudflare `CF-IPCountry` header}

### Sequencing dependencies
- {Other work that must ship first, or that this blocks}

### Residual concerns from codebase audit
{Anything from Phase 2's Validation Report that wasn't fully resolved in Phase 4. If fully resolved, write "None — all audit findings addressed in amended spec/plan."}

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| {today} | Initial draft | prd-writer skill |
```

---

## Edge cases and judgement calls

- **The user already has a PRD draft.** Read it first. Treat it as another input to validate, not as output. Amend it the same way you amend the spec and plan.
- **The validation agent returns "no issues."** Verify by spot-checking 2-3 of the plan's file paths yourself before trusting it. Agents sometimes skim.
- **The user resists amending the spec.** That's their call — they may know context the audit missed. Respect it, but log the unresolved item in the PRD's "Residual concerns" section.
- **The spec and plan are massive (50+ pages combined).** Don't try to fit everything into the context window at once. Read summaries + first/last sections, dispatch the validation agent with full paths and let it read the files directly, then pull only the findings into your working memory.
- **The user asks to skip the codebase audit.** Push back once, explain why the audit exists (it's the reason this skill exists in the first place). If the user still says skip, proceed without it but explicitly mark the PRD status as `Draft — codebase audit skipped at user request` so a future reader knows.

---

## What NOT to do

- Don't write a PRD that could apply to any Laravel/Vue app. Fynla International-specific or it's useless.
- Don't paste the Validation Report into the PRD — summarise residual concerns only.
- Don't invent metrics. If no baseline exists, say "unknown — requires measurement."
- Don't gold-plate. The PRD is a working document, not a pitch deck.
- Don't skip the rolling interview to save time. The interview is the skill's whole point.
- Don't propose moving UK code into `packs/country-gb/`. That path was tried (Workstream E), failed catastrophically (600+ test failures from Eloquent type mismatches in compatibility aliases), and was reverted. UK code stays in `app/`. See `Plans/Implementation_Plan_v2.md` § 1 and `docs/adr/ADR-003`.
- Don't hardcode country in core. If the PRD's feature needs country-specific behaviour in `core/`, that's a design smell — the behaviour belongs behind a contract and in a pack.
- Don't treat fynla.org and the International codebase as interchangeable. This is the International project at `/Users/CSJ/Desktop/fynlaInternational`. The original UK-only project lives at `/Users/CSJ/Desktop/fynla` (separate deployment, separate branch workflow) and is not in scope for this skill.
