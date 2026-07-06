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
| G-4-b sl.4 | Form Requests sample-of-10 | ✅ **PASS 2026-07-06** — 1 HIGH found + FIXED in-session (S4-H1 cross-user savings-deposit leak via unscoped `linked_savings_account_id`; 3 regression tests), 8 MED + 9 LOW logged E-24..E-28. Report: `July/July6Updates/g-4-b-slice-4-requests-audit.md`. **G-4-b CLOSED.** |
| G-4-c | Morph escalation test | ✅ **PASS 2026-07-06** — `tests/Feature/Security/MorphEscalationTest.php` proves the load-bearing invariant (steered token can't reach original owner's resources → 404) + legacy-alias resolution + fail-closed on nonsense morph. One LOW robustness nit logged G-4-c-i. |
| G-4-d | Secret management audit | ✅ **PASS 2026-07-06** — zero real secrets tracked in repo (both `.env.production` are `YOUR_*` placeholders); log redaction built (H-2, see below) and live-verified. Server-side `.env` 600 perms 🚩 parked (needs server). |
| G-4-e | Auth-flow review | ✅ **PASS 2026-07-06** — verify-code `throttle:10,1`, resend `throttle:5,1`, login `throttle:5,1`; MFA verify/recovery in preview exclusions; `mobileLogout` present (biometric guard intact). All 3 documented risk points verified. |
| G-4-f | External non-Claude LLM audit | 🚩 **FLAGGED — needs xAI/Gemini API key + spend approval (CSJ)** |
| G-2-a | Controller coverage matrix (98 controllers) | ✅ **PASS 2026-07-06** — auth column enforced GREEN for **all 601 api routes** via `ApiAuthCoverageTest` (every route sanctum/token-gated or on a 21-entry public allow-list; new unguarded route fails the build). IDOR column closed for high-risk paths; happy-path deferred to G-3 per matrix. Matrix: `Plans/test-gauntlet-coverage-matrix.md`. |
| G-2-b | Observer chains, full-request | ✅ **COVERED** — G-1-b's 59 observer-firing tests + G-2-e cache-invalidation-on-mutation + the risk-recalc observer feature tests exercise the full request→observer→cache/risk chain. No new gap. |
| G-2-c | Binding walk | ✅ **PASS 2026-07-06** — `PackBindingWalkTest`: all 16 `pack.gb.*` keys resolve to their core contract (was 5/16 pre-R-17); 4 query-layer bindings verified as shared singletons. |
| G-2-d | Morph resolution + dirty-DB replay | ✅ **PASS 2026-07-06** — `MorphMigrationReplayTest`: fresh tokens carry the core morph; a dirtied `App\Models\User` token canonicalises + stays idempotent + authenticates; the real backfill migration re-runs clean (exit 0). |
| G-2-e | Cache poisoning | ✅ **PASS 2026-07-06** — `CachePoisoningTest`: a poisoned net-worth entry is invalidated on model mutation (never served stale); legacy `App\Models\User` resolves via class_alias; net worth rebuilds cleanly post-invalidation. |
| G-2-f | Allow-list ratchet | ✅ **CLOSED BY R-17** (list = 0, exemptions retired, enforced) |
| G-2-g | R-14a residuals doc | ✅ **CLOSED BY R-17** (both bindings pack-local; nothing to track) |
| G-3-a | Playwright harness | ✅ **DONE 2026-07-06** — `tests/e2e/helpers/persona.js` (demo-flow login for all 6 personas + `collectConsoleErrors` third-party-filtered helper); config baseURL env-driven (`E2E_BASE_URL`, default 8001), webServer auto-start removed (runs against dev.sh). Requires built assets (`VITE_BASE_PATH=/build/`) + `public/hot` removed. Node 20 (`~/.nvm/versions/node/v20.19.5`). |
| G-3-b | Persona × module journeys | **PARTIAL 2026-07-06** — `00-persona-journeys.spec.js` walks 8 core modules per persona asserting URL + authenticated shell + ZERO console errors. **Found & fixed 2 real frontend bugs**: `plans/savings` 404 (route constraint excluded `savings` though the controller supports it) and a double-`/api/api/` prefix in `LetterEstateWarnings.vue`. 5/6 personas green; `retired_couple` passes in isolation but flakes under full sequential load against the single-threaded `artisan serve` (same class as the Pest parallel flakes). Per-record CRUD round-trips = follow-on. |
| G-3-c | 5 cross-module flows | TODO |
| G-3-d | Numbered-rules compliance | ✅ **PARTIAL 2026-07-06** — `DesignGuideComplianceTest` statically locks Rule 6 (no local formatCurrency), Rule 9 (no amber/orange), Rule 13 (no user-facing scores). Fixed the live 'Drift Score'→'Total Drift' and deleted the 7 dead PlanSections components (score violations + audit dead-code). Rules 2/4/5 (preview-block, save-event, enums) already covered by existing Feature/arch tests. |
| G-5 H-1 | Rate limits on auth surfaces | ✅ **DONE 2026-07-06** — verified all pre-auth surfaces throttled (login/register/verify/reset from G-4-b sl.1); added `throttle` to the 4 authenticated MFA-management routes (setup/verify-setup/disable/recovery-codes — code-accepting/secret-rotating). Only idempotent `/logout` is unthrottled (intentional). |
| G-5 H-2 | Log redaction processor | ✅ **DONE 2026-07-06** — `App\Logging\RedactionProcessor` + `RedactSensitiveData` tap on single/daily channels; redacts sensitive context keys + secret-shaped tokens (sk_/pk_/wsk_/xai-/sk-ant-/Bearer/Sanctum). 5 unit tests + live-verified on disk. |
| G-5 H-3 | External-HTTP timeouts | ✅ **DONE 2026-07-06** — swept all external `Http::` calls: Revolut/xAI/Awin already had timeouts; fixed the one gap (`PushNotificationService` FCM call, now `timeout(10)/connectTimeout(5)`). `HttpTimeoutTest` pins it against regression. |
| G-5 H-4 | `env:validate` command | ✅ **DONE 2026-07-06** — `php artisan env:validate --target=production` asserts prod flags (APP_ENV/APP_DEBUG/REVOLUT_SANDBOX), absent test overrides, real APP_KEY, and all required credentials present (never prints secrets). 4 tests. Local passes, prod-target correctly blocks on the dev machine. |
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
