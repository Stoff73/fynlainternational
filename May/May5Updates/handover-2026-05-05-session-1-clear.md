---
type: handover
mode: context-clear
date: 2026-05-05
session: 1
branch: main
previous_session: 2026-04-21 session 1 (WS 1.5b SA Protection shipped, Task 21 Playwright deferred)
---

# Context Clear Handover — 2026-05-05, Session 1

[[May Index]] | [[Home]]

## Immediate state

Just finished a design-system audit of all 5 SA pages, written and committed to `May/May5Updates/sa-pages-design-audit-2026-05-05.md` (+ 6 reference screenshots). User is clearing context **so the next instance can pick up the rework PR**. Working tree clean, branch `main` is 3 commits ahead of `origin/main` (no remote configured for this repo — local-only per `Implementation_Plan_v2.md`).

## The thread

The day went: **(1) tech-debt sweep** (cleared 4 `ProtectionWorkflowTest` failures + wired `ZaTaxConfigurationSeeder` + `JurisdictionSeeder` into `DatabaseSeeder`; tests went 2,776 → 2,781 passing, 0 failing). **(2) WS 1.5b Task 21 Playwright smoke** (3/3 functional scenarios passed; 4 functional defects logged in `playwright-task-21-2026-05-05.md`). **(3) User pushed back** — pages didn't follow `fynlaDesignGuide.md`, `/za/protection` had no chrome at all. I had reported "PASSED" without flagging the bare layout. **(4) SA pages design audit** — produced this comprehensive defects list. The user has now asked for context-clear before the rework PR begins.

## Files committed this session

3 commits today:

- `9504fb1` — fix(tech-debt): clear 4 ProtectionWorkflowTest fails, wire ZaTaxConfigurationSeeder, fix WillBuilder middle-name flake
- `c055805` — fix(seeders): rescue orphan JurisdictionSeeder + make Vite port overridable for sibling-repo coexistence
- `c2a27c5` — docs(audit): SA pages design-system compliance audit + 6 reference screenshots

Plus the local-only `dev.sh` rewrite (gitignored — see "Pickup notes" below).

## What the next Claude needs to know

1. **The user is angry that I didn't audit against `fynlaDesignGuide.md` v1.3.0 before running the smoke today.** CLAUDE.md Rule 11 says "Before changing, updating, or implementing anything related to the UI, you MUST read and follow `fynlaDesignGuide.md`." Read the guide BEFORE touching any SA Vue file.
2. **My earlier blanket complaint that "all SA pages have no chrome" was WRONG.** 4 of 5 SA pages do use `<AppLayout>`. Only **`/za/protection`** is bare — that's the regression that triggered the user complaint. Don't generalise.
3. **The audit report `May/May5Updates/sa-pages-design-audit-2026-05-05.md` is the source of truth** for what's broken. Read it first before opening any Vue file. It includes a per-page matrix and per-defect file:line references.
4. **Sibling fynla UK repo runs concurrently on `:8000`/`:5173`.** This repo's `dev.sh` (gitignored, local-only) now picks `:8001`/`:5174` preferred and falls back to `:8000`/`:5173` if the sibling holds them. `vite.config.js` honours the `VITE_PORT` env var.
5. **WS 1.5b smoke also surfaced 4 functional defects** in `playwright-task-21-2026-05-05.md` (raw-cents in "Why this number?", `is_dutiable` mutator misses `nominated_individual` / `testamentary_trust`, Policies-tab count not refreshing, `TestUsersSeeder` not idempotent for FamilyMember/Mortgage). User asked whether to ship now or roll into WS 1.6b Estate. Decision pending.
6. **No vault-sync was run this session** (skipped on context-clear — too heavy for a mid-flow clear). The vault at `/Users/CSJ/Desktop/fynlaBrain/May/May5Updates/` already contains a handover from the sibling fynla repo (UK production) — don't confuse it with this one.
7. **DB state is hand-fixed for the test user.** `za-protection-test@example.com` (id 2010) has been:
   - `email_verified_at = now()` set manually (so login skips the verification-code dance)
   - 2× FamilyMember dependants (intended) but TestUsersSeeder ran twice during DB recovery so dependants are now **4** not 2, and total mortgages are **R 1,600,000** not R 800,000
   - One `za_protection_policies` row (Old Mutual life cover R 2,000,000) created during the smoke
   - One `za_protection_beneficiaries` row currently `type=estate, name=Thandi Test, allocation_percentage=100, is_dutiable=1` (left in this state from scenario 3)
   - GB jurisdiction (id 314, primary) + ZA jurisdiction (id 313, secondary) in `user_jurisdictions`. These survive `db:seed` now that `JurisdictionSeeder` is wired in.

## Pick up from here — Recommended fix order (single PR, ~12 files, no backend)

Verbatim from the audit report's recommendation section:

1. **Wrap `/za/protection` in `AppLayout`** (fixes C1) — 5 lines, immediate visible impact.
   File: `resources/js/views/ZA/ZaProtectionDashboard.vue:1-21`. Replace bare `<div class="za-protection-dashboard">` with `<AppLayout><div class="max-w-7xl mx-auto px-4 py-6">…</div></AppLayout>` and add `import AppLayout from '@/layouts/AppLayout.vue';` to the script.

2. **Standardise container width to `max-w-7xl mx-auto`** across all 5 SA dashboards (fixes M3) — 4 one-line edits.
   Files (currently `max-w-6xl`): `ZaSavingsDashboard.vue:3`, `ZaInvestmentDashboard.vue:3`, `ZaExchangeControlDashboard.vue:3`. `ZaRetirementDashboard.vue` already correct. `ZaProtectionDashboard.vue` gets fixed in step 1.

3. **Add the shared chrome stack** to all 5 SA dashboards (fixes M1 + M2) — biggest visual lift.
   - `module-gradient` background class on the inner wrapper (per `fynlaDesignGuide.md` § 4 "Gradients & Patterns")
   - `<ModuleStatusBar />` from `@/components/Shared/ModuleStatusBar.vue`
   - `<ProfileCompletenessAlert v-if="profileCompleteness && !loadingCompleteness" :completenessData="profileCompleteness" :dismissible="true" />` from `@/components/Shared/ProfileCompletenessAlert.vue`
   - Optional but recommended: `<ModuleLifeEvents class="mb-6" module="..." :events="lifeEvents" :impact-summary="lifeEventImpact" />` — only if the SA module has life-event integration. Skip for now if not.

   Reference for what this looks like assembled: `resources/js/views/Protection/ProtectionDashboard.vue:1-78` (UK canonical).

4. **Wrap each SA list/section in a `bg-white rounded-lg border border-light-gray p-6` card** (fixes M5) — pure JSX edits, no logic.
   Files: `ZaPoliciesTable.vue` (lines 17-39 wraps a `<table>` directly — wrap that whole block), `ZaInvestmentAccountsList.vue`, `ZaHoldingsList.vue`, `ZaRetirementFundsList.vue`, `ZaTransferLedger.vue`. Mirror the UK pattern at `Protection/ProtectionDashboard.vue:60-65`.

5. **Replace `confirm()` with a shared `ConfirmModal`** in `ZaPoliciesTable.vue:76` (fixes M6).
   `if (!confirm(\`Delete ${p.provider} ${this.typeLabel(p.product_type)}?\`)) return;` is a native browser dialog — bad UX. First grep `resources/js/components/Shared/` for an existing `Confirm*` component (e.g. `ConfirmModal.vue`); if none exists, create a small one (header / body / Confirm/Cancel buttons styled per design guide § 6.2). Use it everywhere SA components do destructive ops.

6. **Token sweep** (fixes m3, m4, m5) — three small swaps:
   - `ZaExchangeControlDashboard.vue:5`: `text-horizon-700` → `text-horizon-500`
   - `ZaProtectionDashboard.vue:5`: `<p class="text-horizon-300 mt-2">` → `<p class="text-sm text-horizon-500 mt-1">`
   - `ZaProtectionDashboard.vue:7`: `border-savannah-100` → `border-horizon-200`

**These 6 steps land in one PR. ~12 files touched. Zero backend changes.**

Steps 7 + 8 are follow-up PRs, do NOT bundle into the rework:

7. **Acronym sweep** in `resources/js/components/ZA/Retirement/*` for bare `RA` / `PF` / `PvF` / `Reg 28` standalone usage (CLAUDE.md Rule 10). Audit `Investment/*` for `SDA` / `FIA` / `AIT`. Audit `ExchangeControl/*` for `SARS` / `SARB`. Spell out on first use; abbreviations after that are fine.

8. **(Out of scope, separate PR)** Extract shared `Tabs.vue` and `ConfirmModal.vue` components and refactor all consumers (UK + SA). These are pre-existing design-system gaps, not SA-specific. Don't bundle.

## Plus the 4 functional defects from `playwright-task-21-2026-05-05.md`

Decide whether to bundle these into the design-rework PR or roll into WS 1.6b Estate (which consumes `is_dutiable`). My recommendation: **bundle F1 + F3 into the design PR** (they're cosmetic / data-flow), **roll F2 into WS 1.6b** (it's a tax-rule decision that needs estate context), **and fix F4 separately** (TestUsersSeeder idempotency is a dev-experience fix, separate concern).

## Outstanding from before today

- WS 1.6b SA Estate Planning frontend (1 week, queued)
- WS 1.7 SA personas + onboarding (1.5 weeks, queued)
- WS 1.8 Localisation + FAIS / POPIA compliance copy (queued)
- DialogContainer follow-up PR (5 ZA modals — `ZaContributionModal` ×2, `ZaInvestmentForm`, `ZaRetirementFundForm`, `ZaProtectionPolicyModal`) — queued, run after design rework

## Known issues / blockers

- The 5 `.claude/skills/*` files in `git status` are pre-existing harness updates (not my work) — leave them out of any commit.
- **Sibling fynla UK repo's Vite still on `:5174`** (PID 41302 at session-end). New `./dev.sh` handles this gracefully — but if the next instance hits a Vite startup issue, run `./dev.sh` (it'll fall back to `:5173`).
- `dev.sh` is gitignored (per `.gitignore:76`) — the `pick_port()` rewrite is local to my machine. If you want the fix shared with other devs, either un-gitignore `dev.sh` or convert to `dev.sh.example`.

## Servers at session-end

- Laravel: `:8001` (PID 9025)
- Vite: `:5173` (PID 9125)
- Sibling UK fynla: Vite on `:5174` (PID 41302), Laravel on `:8000`

## Test status at session-end

- 2,781 passing, 0 failing, 2 skipped (`./vendor/bin/pest`)
- Last full-suite run: ~07:30 BST, before any of today's app/ changes
- Today's changes were tests + seeders + audit doc only — no app/ code changed, no test impact expected
