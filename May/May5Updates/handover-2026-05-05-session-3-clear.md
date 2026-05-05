---
type: handover
mode: context-clear
date: 2026-05-05
session: 3
branch: main
previous_session: 2026-05-05 session 2 (SA pages design rework PR shipped)
---

# Context Clear Handover — 2026-05-05, Session 3

[[May Index]] | [[Home]]

## Immediate state

Acronym sweep PR shipped. One commit pushed to `origin/main`: `67500d0` (SARS / CGT / DWT spell-outs + bonus H1 token swap on `ZaInvestmentSummary`). Vault synced (`b2eaed6` from vault-sync subagent — also fixed CLAUDE.md metrics drift Vue 712→713, Models 101→94, Country Packs 2→3). Working tree clean except the 5 pre-existing `.claude/skills/*` carryover files. Browser-verified end-to-end as `za-protection-test@example.com`.

## The thread

CSJ said "continue from handover" → picked up the next item from session 2's pickup queue (acronym sweep). Surveyed scope before touching code: the handover-listed RA/PF/PvF/SDA/FIA/AIT/SARB acronyms were already Rule-10 compliant (parenthetical-introduction pattern in place), so the actual violations were bare SARS in 3 retirement components and bare CGT/DWT in `ZaInvestmentEngine` user-facing strings. Bundled in a bonus design fix (the H1 token slip on `ZaInvestmentSummary` that was missed in session 2's audit fix). One mid-session correction from CSJ: when Vite died and I navigated anyway → CSJ flagged "why would you restart a running service? why don't you just do it properly?" → I investigated the actual state (sibling UK fynla repo's Vite was on :5174, ours was genuinely down), started ours on :5173, and re-ran the test journey end-to-end.

## Files touched

**Committed in `67500d0`:**
- `packs/country-za/src/Investment/ZaInvestmentEngine.php` — TFSA tax_treatment introduces "Capital Gains Tax (CGT)" first use; Discretionary tax_treatment introduces "Dividend Withholding Tax (DWT)" first use; description now reads "Exchange-Traded Funds (ETFs)" (preventative — `description` field is NOT currently rendered in the frontend, only `tax_treatment` is bound on `ZaInvestmentSummary.vue:25`); `note` in `zeroTaxBreakdown` also spelled out
- `resources/js/components/ZA/Investment/ZaInvestmentSummary.vue` — H1 `text-horizon-700` → `text-horizon-500` (Rule 9 fix; same defect that was caught on Exchange Control in session 2 but missed here)
- `resources/js/components/ZA/Retirement/ZaRetirementSummary.vue` — "South African Revenue Service (SARS)" first use
- `resources/js/components/ZA/Retirement/ZaSavingsPotWithdrawalCard.vue` — same
- `resources/js/components/ZA/Retirement/ZaSection11fReliefCalculator.vue` — same

**Subagent committed in `b2eaed6`** (vault sync side-effects to repo):
- `CLAUDE.md` — metrics refresh: Vue 712→713, Models 101→94, Country Packs 2→3

## What the next Claude needs to know

1. **Survey BEFORE touching code paid off again.** The handover-listed acronyms (RA/PF/PvF/SDA/FIA/AIT/SARB) were not the actual violations — those were already compliant. Real violations were SARS/CGT/DWT/ETFs. Don't trust an upstream task description's specifics; grep first.
2. **Rule 10 per-component first-use pattern.** When spelling out an SA acronym, do it on the FIRST occurrence within each component (since components can render in any order/page). Subsequent occurrences in the same component can use the abbreviation. Pattern used: `Capital Gains Tax (CGT)` on first, then `CGT` for follow-ups. This is more robust than relying on render-order assumptions across components.
3. **`description` in `getTaxWrappers()` is NOT rendered in the frontend.** Only `tax_treatment` is bound (`ZaInvestmentSummary.vue:25`). My ETFs spell-out is preventative (mobile/AI-chat may consume it) but has no current UI impact — flagged in the commit body for honesty.
4. **CSJ feedback on broken environment:** "Why would you restart a running service? Why don't you just do it properly?" Investigation revealed our Vite was genuinely down (sibling UK repo's Vite on :5174 doesn't help us). The fix was to START our Vite (not restart), then re-test. **Lesson:** when something looks broken, investigate the real state with `lsof`/`ps`, don't react with a restart. Verify whose process owns each port via `ps -p PID -o pid,command`.
5. **Pre-existing `.claude/skills/*` dirty files** are now spanning 3 sessions of carryover. Per session 1 handover note: "leave them out of any commit" — they're harness updates, not application code.
6. **DB hand-fixes for `za-protection-test@example.com` were repeated again** (user id is now `4`, jurisdiction ids GB=47 ZA=48 this time — they shift on each re-seed). Fix is queued as F4 (`TestUsersSeeder` idempotency for ZA jurisdiction assignment) but still not done.
7. **Deploy note written this session:** `May/May5Updates/deploy-2026-05-05.md` covers all 5 functional commits across sessions 1-3. Nothing has been deployed yet to either dev or prod. CSJ will deploy manually via SiteGround File Manager when ready.

## Pick up from here

Next deliverable from CSJTODO queue:

1. **Extract shared `Tabs.vue`** (used by UK Investment + SA Protection + SA Retirement tab strips) — refactor PR, codebase-wide
2. **DialogContainer follow-up PR** — refactor 5 ZA modals to share a base dialog container (now that `ConfirmModal` exists as a precedent)
3. **WS 1.6b — SA Estate Planning frontend** — F2 (`is_dutiable` mutator whitelist) consumed in this workstream. **Run `/prd-writer` first.**
4. **F4 — `TestUsersSeeder` idempotency** for FamilyMember/Mortgage children + ZA jurisdiction assignment for `za-protection-test`. Separate PR.
5. **WS 1.7 — SA personas + onboarding** (1.5 weeks).
6. **WS 1.8 — Localisation + FAIS / POPIA compliance copy.**

The deploy note (`May/May5Updates/deploy-2026-05-05.md`) is ready if CSJ wants to ship today's three sessions of work.

## Context hints

- **Active branch:** `main` — pushed to origin
- **Behind origin/main by:** 0 commits (fully synced)
- **Uncommitted:** 5 pre-existing `.claude/skills/*` dirty files only — ignore
- **Last commit:** `b2eaed6` docs(vault): sync May5Updates to fynlaBrain + update May Index, Home, Git History; fix CLAUDE.md metrics
