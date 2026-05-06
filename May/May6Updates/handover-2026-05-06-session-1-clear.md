---
type: handover
mode: context-clear
date: 2026-05-06
session: 1
branch: main
previous_session: 2026-05-05-session-5 (RA-1 sidebar refactor on archived feature/architecture-realignment branch)
---

# Context Clear Handover — 2026-05-06, Session 1

## Immediate state

Working tree clean on `main`. v3 architecture spec + plan committed and pushed (`98d4881` on `main`). Working architecture contract is once again `Plans/multi_country_architecture.md` v1.1 (every country lives in a pack, including UK). The v2 retreat ("UK stays in `app/`, only SA in packs/") is retired as not-scalable. Next session is the codebase-audit step described under "Pick up from here" — there is no implementation work in flight.

## The thread

- User pushed back on the v2 architecture: at 50+ countries, having UK forever-privileged in `app/` while every other country lives in `packs/` doesn't scale. Cross-border bilateral relationships explode (N(N-1)/2); every cross-border pack would have to special-case `\App\…`. Original promise "adding a country requires zero core changes" is broken (sidebar lives in core constants).
- Diagnosed: April Phase 0 failed because of TECHNIQUE (Eloquent compatibility aliases — `App\Models\X extends Fynla\Packs\GB\Models\X` — break Eloquent's type interchangeability), not because of strategy. The v2 plan abandoned the destination instead of fixing the technique.
- User chose **C then A**: re-plan, then commit to original architecture properly. Lost work acknowledged.
- Wrote `Plans/architecture-spec-v3.md` (re-affirms `multi_country_architecture.md` v1.1; explicitly retires v2 + the May 5 realignment) and `Plans/architecture-plan-v3.md` (15-workstream UK relocation, ~39 hours focused execution).
- User amended **spec § 6.2**: dropped the "currently-focused jurisdiction toggle" model. New model: country of residence dictates the base/default pack; foreign-country assets/liabilities surface inline in the residence pack's views. No toggle.
- User amended plan **timeline**: days → hours throughout. Total ~39 hours of focused mechanical execution. Any blow-out is a signal to stop and check for drift, not extend the estimate.
- User questioned the PRD step. Discussion ended at: PRD's main value is the **codebase audit** that validates plan against live code; the formal 9-section doc adds little for a refactor. User picked **Option A** (audit step inline, no formal PRD).

## Files committed today (`main`, commit `98d4881`)

- `Plans/architecture-spec-v3.md` (NEW, ~180 lines)
- `Plans/architecture-plan-v3.md` (NEW, 15 workstreams, hour-denominated)

Also updated this session (not in commit — gitignored or memory-only):
- `CSJTODO.md` (gitignored) — front-of-list rewritten to v3, all RA-1 → RA-6 work marked superseded
- `~/.claude/projects/.../memory/project_architecture_decision.md` — pointed at v3 plan, retired v2 references
- `~/.claude/projects/.../memory/sidebar_architecture_pattern.md` — added note that `MODULES_BY_JURISDICTION` is transitional (gets removed in WS R-12)
- `~/.claude/projects/.../memory/MEMORY.md` — index entry for architecture decision updated

Vault-sync also updated `CLAUDE.md` Country Packs metric (3 → 2 — `_template` is a scaffold, not a country pack). Currently uncommitted, will be picked up by Phase 10 of session-end.

## Branch state

- `main` — current. Tip `98d4881` (today's v3 spec + plan commit). Pushed.
- `feature/architecture-realignment` — **ARCHIVED as superseded** by the v3 plan. Tip `b08d2b2` (session-5 handover). Contains:
  - `f21e939` — May 5 realignment spec/plan/gap-analysis (now historical, superseded)
  - `0adf82b` — RA-1 SideMenu refactor + TestUsersSeeder jurisdiction-pinning fix. The seeder fix is the only durable artefact; will be cherry-picked into the future `refactor/uk-pack-relocation` branch when that work begins. The SideMenu refactor (which put `MODULES_BY_JURISDICTION` in core) is replaced by per-pack `navigation()` providers in WS R-12.
  - `b08d2b2` — yesterday's session handover.
- `refactor/uk-pack-relocation` — DOES NOT EXIST YET. Created when R-0 begins (after the audit step).

## What the next Claude needs to know

- **The contract is `Plans/multi_country_architecture.md` v1.1.** UK code goes into `packs/country-gb/`. Every country in a pack, no exceptions. Core has zero country knowledge.
- **No compatibility aliases.** Direct relocation only — move file → update namespace → update every `use App\…` reference in the codebase in the same commit → run Pest → fix breakage. The April attempt failed because of aliases (`App\Models\X extends Fynla\Packs\GB\Models\X`) — Eloquent rejects the inheritance pattern.
- **Polymorphic relations:** register a morph map in `GbPackServiceProvider::boot()` BEFORE moving any polymorphic-target model, then run a one-shot data migration to convert `morphable_type` columns from FQCN to short keys.
- **Cadence:** one module at a time, not 293 files at once. Pest stays green at every commit.
- **"Hours not days" mandate.** The plan totals ~39 hours of focused mechanical execution. Any workstream blowing past its hour estimate is a signal to STOP and check for drift, not to extend the budget.
- **Spec § 6.2 dual-user model:** country of residence dictates the default pack; foreign assets surface inline in residence-pack views. No toggle. No manual jurisdiction switch.
- **Three plan defaults** (in `Plans/architecture-plan-v3.md` § 18) — the user has not explicitly approved or overridden:
  1. Backend URL strategy: Option X (country-prefixed `/api/gb/*`, `/api/za/*` with redirect layer) — plan default
  2. UK table renames: defer (keep current names; optional follow-up `gb_*` rename) — plan default
  3. Branch strategy: single long-lived `refactor/uk-pack-relocation` branch — plan default
  These don't block the audit step. Surface them again before R-0.
- **PRD step is SKIPPED** for this refactor (user's explicit choice — see "Pick up from here"). The audit step replaces it.
- The vault-sync subagent already added entries for May06 to the May Index, May 2026 git history, and Home.md. The handover file (this one) will be linked by session-end Phase 9.

## Pick up from here

**Top of agenda — verbatim from user's session-end instruction:**

> Skip the formal PRD doc; run the audit step inline. Dispatch `feature-dev:code-explorer` against the v3 plan, surface every conflict/gap as a punch list, amend the spec/plan, then go straight to R-0. ~30 min of audit + amendments, no formal doc. This honours the "hours not days" mandate and still prevents off-piste.

### Concrete next actions

1. **Read** `Plans/architecture-spec-v3.md` and `Plans/architecture-plan-v3.md` (the contract for the audit).
2. **Dispatch `feature-dev:code-explorer` agent** against the v3 plan with this brief:
   - "Audit the v3 UK pack relocation plan against the live `app/` directory. For each workstream R-0 → R-15, identify:
     - Files the plan quantifies but doesn't catalogue (which traits in `app/Traits/` are UK vs shared, which middleware in `app/Http/Middleware/` is UK vs core, which models are UK financial vs core like User/Household, which polymorphic-target models exist and where they're referenced)
     - Hidden coupling (UK observers wired to core models, UK services imported by core code, polymorphic morph types stored in DB columns)
     - Edge-case directories not mentioned in the plan (`app/Services/{Admin,AI,Cache,Mobile,Marketing,GDPR,Documents,Onboarding,Settings,UserProfile,Risk,LifeStage,Plans,WhatIf,Trust,Chattel,Property,Business,NetWorth,Dashboard,Coordination,Shared,Benefits,Audit}/` — which are UK-specific, which are shared, which need splitting)
     - Cross-pack risks (anything in `packs/country-za/` that imports from `\App\…` and would break the moment UK code moves)
     - Frontend audit: which `resources/js/components/`, `views/`, `store/modules/`, `services/` are UK-specific vs shared
   - Return: a punch list of conflicts/gaps with file paths, organised by which workstream is affected. Specific enough that I can amend the spec/plan in <30 min."
3. **Amend the spec/plan** based on the punch list — add missing edge cases, update workstream task lists, adjust hour estimates ONLY if a genuine gap is found (otherwise stay disciplined on hours-not-days).
4. **Confirm the three plan defaults** (URL strategy, table renames, branch strategy) with the user.
5. **Cut the branch** `refactor/uk-pack-relocation` from `main`.
6. **Cherry-pick the TestUsersSeeder jurisdiction-pinning fix** from `0adf82b` (only the seeder portion — not the SideMenu changes) as the first commit.
7. **Begin R-0** (Pre-flight + GB pack skeleton — 1 hr): scaffold `packs/country-gb/` from `packs/_template/`, register composer path repo, dump autoload, register `GbPackServiceProvider` with PackRegistry. Pest green.

### What NOT to do

- **Do NOT skip the audit step** even though the formal PRD is skipped — the audit IS the load-bearing protection against off-piste.
- **Do NOT start R-0 before the audit completes** and the spec/plan amendments are in.
- **Do NOT add scope** (no DialogContainer, no Tabs.vue extraction, no SA Estate WS 1.6b, no personas, no FAIS/POPIA copy — all paused until UK relocation completes).
- **Do NOT push commits to `feature/architecture-realignment`** — it's archived.
- **Do NOT use compatibility aliases** when relocating. Direct relocation only.
- **Do NOT extend hour estimates** without flagging it as drift.
