---
type: handover
mode: context-clear
date: 2026-05-05
session: 4
branch: main
previous_session: 2026-05-05 session 3 (clear)
priority_alert: ARCHITECTURAL REALIGNMENT — front of list for next session
---

[[May Index]] | [[Home]]

# Context Clear Handover — 2026-05-05, Session 4

## ⚠️ FRONT OF LIST FOR NEXT SESSION

**The architectural realignment work is THE priority.** Do not start cleanup PRs (DialogContainer), new feature workstreams (WS 1.6b SA Estate, WS 1.7, WS 1.8), or any other implementation work until the realignment is decided and underway.

The implementation has drifted from `Plans/multi_country_architecture.md` and `Plans/Implementation_Plan_v2.md`. SA-only users currently see the full UK sidebar plus a bolted-on "South Africa" subsection. The plan promises a "Pure SA app" — single sidebar shape, country-agnostic labels, pack-provided routes. The drift is structural; it cannot be fixed by adding more SA pages on top of it.

Three documents capture the full state of this discovery — read them in this order:

1. `May/May5Updates/architecture-gap-analysis-2026-05-05.md` — 13-item inventory of every gap (3 CRITICAL, 3 HIGH, 5 MEDIUM, 2 LOW) between the implementation and the plan, with severity classification and root-cause analysis.
2. `May/May5Updates/architecture-spec-realigned-2026-05-05.md` — re-affirmed authoritative spec. **Crucial clarification baked in:** the sidebar shape is country-agnostic (one shape, same labels for all users). The pack provides the routes behind the labels. There is no "South Africa" or "United Kingdom" sidebar header. Supersedes the wording in `Plan v2 § 2.4` ("distinct headers in the sidebar") which was an earlier draft.
3. `May/May5Updates/architecture-plan-realigned-2026-05-05.md` — Phase 1 realignment plan, 6 sequential workstreams (RA-1 through RA-6), 13–19 days estimated, Option C (UK refactor first, ZA migration second).

The user has approved Option C. Decisions still open at the bottom of the plan doc:

- Brainstorming: skip or run? My recommendation in the plan is skip (spec + plan already explicit) and go straight to `superpowers:writing-plans` to expand each RA-N into TDD tasks. **The user said: "once the spec and plan have been re-aligned, we will make a decision on the brainstorming."** They want to see the spec/plan first before deciding. They have now seen them. Ask them at the start of the next session.
- Branch strategy: one long-lived feature branch (recommended) vs 6 small serial PRs.
- Dual test user: seed in RA-1 (recommended) vs defer.

## Immediate state at clear

The session ended after writing the realigned spec, plan, and gap analysis to `May/May5Updates/`. All three are committed (`f21e939`) and pushed to origin/main. The deploy note has a session-4 addendum. The vault has been synced — three new docs are in the vault, May Index entries added, git history updated. CSJTODO has been updated (next phase) to reflect realignment as the top item.

The session was originally going to brainstorm the DialogContainer cleanup PR (commit `dedc553` is the design spec). That work is paused. Do NOT pick it up next session — pick up the realignment instead.

## The thread

- Session opened with cleanup work: F4 TestUsersSeeder idempotency (`8363f15`) and SARS acronym intro in ZaTransferModal (`54862a9`) shipped successfully.
- Then started the next cleanup item — DialogContainer spec for refactoring 5 ZA modals onto a shared accessible shell. Brainstormed the API, wrote the design spec (`dedc553`).
- User then asked: *"the original spec, plan, design and agreed way forward was to DUPLICATE the UK fynla site, but show the cross border stuff in the unified dashboard. Why has this changed?"* — a question about architectural intent, not the DialogContainer.
- Investigation confirmed the user's recollection was correct. `Plans/multi_country_architecture.md § 8.2, § 8.3` and `Plans/Implementation_Plan_v2.md § 2.4` both promise a "Pure UK app / Pure SA app / dual user with both + cross-border" UX. The implementation delivered "UK app + bolted-on ZA section". 13 gaps documented, root-caused to WS 1.2b only making the ZA half of the sidebar/routing data-driven.
- Spec and plan re-affirmed and written to disk. User clarified one design point: the sidebar shape is country-agnostic — same shape for all users; pack provides the routes; no jurisdiction headers. This supersedes the "distinct headers" wording in Plan v2.

## Files touched this session

**Committed and pushed:**
- `3129b16` `.claude/skills/session-start/SKILL.md`, `.claude/skills/vault-sync/SKILL.md`, three deleted vault-sync helpers — adopted lean marketplace skills with UK-path overrides for International repo
- `8363f15` `database/seeders/TestUsersSeeder.php` (modified) + `tests/Feature/Seeders/TestUsersSeederTest.php` (new) — F4 idempotency
- `54862a9` `resources/js/components/ZA/ExchangeControl/ZaTransferModal.vue` — SARS parenthetical
- `dedc553` `docs/superpowers/specs/2026-05-05-dialog-container-design.md` — DialogContainer spec (paused; still on disk for when cleanup resumes after realignment ships)
- `f21e939` three architecture realignment docs in `May/May5Updates/`

**Not committed (deploy note in-place edit):**
- `May/May5Updates/deploy-2026-05-05.md` — session 4 addendum + sessions_covered → 1,2,3,4 (will be committed in the final session-end commit)

## What the next Claude needs to know

1. **The plan exists and is unambiguous. Do not propose new specs or replanning.** Read the three docs in `May/May5Updates/` and follow them.
2. **The user is rightly frustrated.** The drift was introduced incrementally across WS 1.2b–1.5b. They asked "why has this not been followed?" Don't make excuses; show the evidence (the docs do this) and execute the realignment.
3. **The country-agnostic sidebar is the load-bearing design clarification.** UK and SA users see structurally identical sidebars; only the pack-provided routes behind the labels differ. There are no "South Africa" / "United Kingdom" headers anywhere. This is captured as a memory: `sidebar_architecture_pattern.md` (created by vault-sync this session).
4. **All cleanup PRs are paused.** DialogContainer (`dedc553` spec on disk), Tabs.vue extraction, and any other "small PR" cleanup work resumes ONLY after the realignment Phase 1 (RA-1 → RA-6) ships.
5. **All new feature workstreams are paused.** WS 1.6b (SA Estate Planning frontend), WS 1.7 (personas/onboarding), WS 1.8 (FAIS/POPIA copy) cannot start until realignment is shipped. Building them on the current shell would compound the drift.
6. **Test status: 2,781 passing, 0 failing, 1 known flake** (`SavingsAgentGoalsTest > recommends increasing contributions` — passes in isolation, occasional fail in full suite; this is unchanged from May 5 session 1 baseline).
7. **Working tree clean. Branch `main`. 5 commits ahead of where session 1 started.**

## Pick up from here

Open with the user:

> "Last session ended with the architectural realignment spec + plan written and on disk. Three open decisions in the plan doc. Want me to go through them now, or do you want to read the docs again first?"

Then resolve in this order:

1. **Confirm the user has reviewed and approves the plan.**
2. **Brainstorming decision** — they said this would come after they've seen the spec/plan. Now they have. Ask: "Skip brainstorming and go straight to `superpowers:writing-plans` to expand RA-1 → RA-6 into TDD tasks, or run brainstorming first?"
3. **Branch strategy** — one feature branch vs 6 serial PRs (recommend the former).
4. **Dual test user** — seed in RA-1 (recommended) or defer to RA-6.
5. **Then start RA-1** — Sidebar: make UK fully data-driven. This closes G1, G4, G5. 2–3 days estimated. First step is to extend `MODULES_BY_JURISDICTION.gb` from a list of strings to a list of `{key, label, route, icon, section}` objects sourced from the existing inline `<SideMenuItem>` declarations in `SideMenu.vue` lines 53–98.

If the user wants to revisit the spec or plan before starting RA-1, do that first.

## Deploy status

- Sessions 1–3 functional changes still NOT deployed to any server (per existing `deploy-2026-05-05.md` status field).
- Session 4 added two more deployable items: `database/seeders/TestUsersSeeder.php` (F4) and `resources/js/components/ZA/ExchangeControl/ZaTransferModal.vue` (SARS).
- See `May/May5Updates/deploy-2026-05-05.md` for the full deploy plan. **Architectural realignment NOTICE in that doc warns against deploying any new feature work before realignment Phase 1 ships.** Session 1–4 functional changes are independent of realignment and safe to ship together.

## Tech debt found this session

- Architectural drift identified — 13 items, fully documented in `architecture-gap-analysis-2026-05-05.md`. Realignment plan addresses them in Phase 1.
- DialogContainer cleanup PR was started and paused mid-design. The spec doc (`docs/superpowers/specs/2026-05-05-dialog-container-design.md`) is on disk and still relevant — it can be picked up post-realignment without rework.
- Memory file path drift in lean marketplace skills (`-Users-CSJ-Desktop-fynla` instead of `-Users-CSJ-Desktop-fynlaInternational`). Fixed in `3129b16` for the project copies; the marketplace upstream still has the UK paths. Marked as separate cleanup item (do not touch marketplace per user direction).

## Known issues / blockers

- 17 transient pest failures observed once during session 4 (full re-run on a clean DB returned to baseline 2,781 pass / 1 known flake). Likely caused by mid-session re-seeding mutating dev DB state. Not a real failure — full suite is stable.
- Triple skill registration: `session-start` and `vault-sync` exist at `~/.claude/skills/`, `~/.claude/plugins/.../marketplaces/...`, AND `<repo>/.claude/skills/`. Project version wins precedence (correct). Not blocking.

## Rules reinforced this session

- New feedback memory: **`sidebar_architecture_pattern.md`** (created by vault-sync) — country-agnostic sidebar shape; pack provides routes behind labels; no jurisdiction headers anywhere. Supersedes "distinct headers" wording in Plan v2 § 2.4.
- Reinforced: every workstream's PRD must validate against `Plans/multi_country_architecture.md` and `Plans/Implementation_Plan_v2.md`, not against existing (potentially-drifted) code shape. The drift compounded because PRDs validated against code, not plan.

## Context hints

- Active branch type: mainline (main)
- Behind origin/main by: 0 (just pushed)
- Uncommitted: deploy-note in-place addendum (will be in final session-end commit)
- Last commit: `f21e939` docs(architecture): realignment spec + plan + gap analysis
- Pest: 2,781 passing, 1 flake, 2 skipped (matches May 5 session 1 baseline + 1 new test from F4)
