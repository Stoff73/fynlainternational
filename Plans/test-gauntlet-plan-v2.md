---
type: plan
workstream: Test Gauntlet v2 — local-only re-baseline
version: v2
date: 2026-07-06
status: ACTIVE — this file is the layer tracker; update Status as items land
supersedes: Plans/test-gauntlet-plan-v1.md (calendar, environment assumptions, G-2-f/G-2-g)
retains: v1 layer definitions (G-2-a..e, G-3-a..d, G-4-c..f, G-5 H-1..H-7), severity rubrics, exit gates
spec: Plans/test-gauntlet-spec-v1.md (unchanged)
---

# Test Gauntlet Plan v2 — Local-Only Re-Baseline

## Why a v2 (mandated by v1 §10)

1. **The v1 calendar is dead.** Week-6 checkpoint (2026-06-22) passed with the
   repo idle; the 8-week clock ran out. v2 drops calendar phasing entirely —
   the autonomous loop executes layers in dependency order, event-driven.
2. **Environment reality changed (CSJ, 2026-07-06): local build only, no dev
   server.** Every "deploy to dev, then test" step becomes "test locally".
   G-0's server-side items are parked, not blocking.
3. **R-17 closed (2026-07-06)** — supersedes G-2-f (allow-list ratchet: now
   ZERO with exemptions retired, enforced) and G-2-g (both R-14a residual
   bindings rebound to pack classes; `Plans/test-gauntlet-r14a-residuals.md`
   never needed). The G-4-b slices 1-3 fixes are on this branch and therefore
   ARE the canvas under local test — the old "not deployed to dev" gap is moot.
4. **Counts drifted**: suite baseline is now ~2,978 passing / 133 architecture;
   form requests span app/ + GB pack + ZA pack after relocation.

## Ground rules (unchanged from v1)

Severity rubric and PASS/FAIL gates per v1 §0. HIGH/CRITICAL fixed before a
layer exits; MEDIUM fixed or risk-accepted into the triage backlog; LOW logged.
Every layer commit+pushed on green.

## Layer tracker

| Layer | Scope (v1 §) | Status |
|-------|--------------|--------|
| G-(-1) | Lifecycle MVP + singleton fix | ✅ DONE (May) |
| G-0-i/ii/iii | SiteGround cron, xAI server key, Revolut sandbox webhook | 🚩 **FLAGGED — CSJ + requires a dev server; parked until one exists** |
| G-0-iv/v | Recipient override, triage backlog | ✅ DONE (May) |
| G-1-a | Pest baseline | ✅ DONE (May: 2,836) — re-baselined 2026-07-06: ~2,978 |
| G-1-b | Observer-firing tests (59) | ✅ DONE (May) |
| G-1-c | Logic golden fixtures (6 personas) | 🚩 **FLAGGED — blocked on CSJ 2-persona sample sign-off (~4-6 hr CSJ)** |
| G-1-d | Persona surgery | ✅ DONE (May) |
| G-4-a | Dependency CVE scan | ✅ DONE (May) + refreshed 2026-07-06 (composer 15→3 advisories, npm 23→4; remainder = Laravel 12 / Vite 8 major-upgrade decisions — CSJ) |
| G-4-b sl.1-3 | Auth / payments / controllers audits | ✅ DONE (May) — fixes live on this branch, exercised by local suite |
| **G-4-b sl.4** | Form Requests sample-of-10 | **IN PROGRESS 2026-07-06** (agent running) |
| G-4-c | Morph escalation test | TODO — next after slice 4 |
| G-4-d | Secret management audit | TODO — local subset (repo grep, log redaction — note H-2 processor not yet built); server perms 🚩 parked |
| G-4-e | Auth-flow review | TODO — doc-driven review |
| G-4-f | External non-Claude LLM audit | 🚩 **FLAGGED — needs xAI/Gemini API key + spend approval (CSJ)** |
| G-2-a | Controller coverage matrix (~103 controllers post-R-17) | TODO — matrix at `Plans/test-gauntlet-coverage-matrix.md` |
| G-2-b | Observer chains, full-request | TODO |
| G-2-c | Binding walk (CoreContracts::all() + pack.gb.* identity) | TODO |
| G-2-d | Morph resolution + dirty-DB migration replay | TODO |
| G-2-e | Cache poisoning | TODO |
| G-2-f | Allow-list ratchet | ✅ **CLOSED BY R-17** (list = 0, exemptions retired, enforced) |
| G-2-g | R-14a residuals doc | ✅ **CLOSED BY R-17** (both bindings pack-local; nothing to track) |
| G-3-a | Playwright harness (persona login + zero-console-error helper) | TODO |
| G-3-b | 42 persona×module journeys | TODO — biggest remaining block |
| G-3-c | 5 cross-module flows | TODO |
| G-3-d | Numbered-rules compliance (7 automatable) | TODO |
| G-5 H-1 | Rate limits on auth surfaces | TODO — verify (largely done in G-4-b sl.1; confirm + close) |
| G-5 H-2 | Log redaction processor | TODO — build `RedactionProcessor` |
| G-5 H-3 | External-HTTP timeouts | TODO — verify (M-3 fixed Revolut in May; sweep Awin/postcode/push) |
| G-5 H-4 | `env:validate` command | TODO |
| G-5 H-5 | CSP self-host fonts + reconcile | TODO — after G-3 green (UI-affecting) |
| G-5 H-6 | ErrorBoundary per route | TODO — after G-3 green |
| G-5 H-7 | Sentry wiring | 🚩 partially — code wiring TODO after G-3; DSN/account = CSJ |
| G-6 | 2-week human user test | 🚩 **FLAGGED — human/calendar item, CSJ schedules** |
| G-7 | Prod-readiness go/no-go | 🚩 **FLAGGED — CSJ decision; machine prep = env:validate + evidence bundle** |

## Execution order for the loop

1. G-4-b slice 4 (in flight) → close G-4-b
2. G-4-c, G-4-d(local), G-4-e — finish the machine-side of G-4
3. G-2-c, G-2-d, G-2-e (small, high-value integration tests)
4. G-2-a coverage matrix + fill the red rows (big) ; G-2-b alongside
5. G-3-a harness, then G-3-b/c/d (biggest block)
6. G-5 non-UI (H-2, H-4, verify H-1/H-3), then UI items after G-3
7. Assemble the G-7 evidence bundle; hand the flagged items list to CSJ

## Exit condition (machine-side)

All non-flagged rows ✅; flagged rows enumerated in a final gauntlet report
with exactly what CSJ must do to close each. That report + green evidence is
the loop's workstream-2 deliverable.
