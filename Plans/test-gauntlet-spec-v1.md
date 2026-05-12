---
type: spec
date: 2026-05-12
status: draft
supersedes: []
companion_plan: Plans/test-gauntlet-plan-v1.md
prd: (pending — to be generated via /prd-writer)
---

# Test Gauntlet Spec v1 — Pre-Production Validation for the UK Pack Architecture

This spec defines the quality bar Fynla International must clear before code on `refactor/uk-pack-relocation` (107 commits, 1,931 files, full `App\Models\*` → `Fynla\Core\Models\*` / `Fynla\Packs\Gb\Models\*` relocation) is allowed to land on `fynla.org` (production, 370 live Sanctum sessions, real customers).

It is the **only** route to prod for this branch. The `csjones.co/fynla_inter` dev environment (bootstrapped 2026-05-12) is the canvas. No code reaches `main` or `fynla.org` until every layer below clears its exit gate.

---

## 1. Why this spec exists

The R-0 → R-14b architecture campaign relocated the entire UK domain into a country pack. The change is correct (per `Plans/architecture-spec-v3.md`) and the dev smoke test is green, but the blast radius is unprecedented:

- 1,931 files changed against `main`
- Every model with polymorphic `morph_type` rows on prod (370 PATs, 9 notifications) needs the `class_alias` + backfill migration to switch over cleanly
- Every service provider, observer, agent, and pack contract is in a new namespace
- The auto-discovery surface (`package:discover`, Sanctum's morph map, Eloquent's relation resolution) all changed

A normal deploy ("dev → smoke → prod → 15-min log watch") is not safe here. Past Fynla deploys touched 10–50 files at a time. This is two orders of magnitude bigger. **The test gauntlet exists because the deploy-day blast radius is too large to validate at deploy time.**

The gauntlet is shifted left: every bug we can find in the 8 weeks before prod is one we don't find with a customer on the phone.

---

## 2. The 8 layers — what each tests, why each exists

The gauntlet has eight layers, ordered by scope (smallest first) so that bugs surface at the cheapest level to fix.

### 2.1 Unit (G-1)
**What:** Pest unit tests on services, models, calculators, value objects, traits. Pure function correctness with mocked dependencies.

**Why it exists:** Catch math errors, off-by-one bugs, wrong enum handling at the smallest possible scope. The 2,825-test Pest baseline is already green locally — this layer's job is to confirm coverage is *adequate*, not just present, and to top up gaps the campaign opened.

**Scope this gauntlet:** verify current 2,825 pass on dev; audit coverage of newly-relocated namespaces; add tests for any service that has zero unit coverage.

### 2.2 Logic (G-1.5, inside G-1)
**What:** Tax math, IHT/CGT/Income Tax/Pension calculator outputs against known-correct fixtures. Cross-border interaction logic. Money rounding (`int_minor` ledger arithmetic, no float drift).

**Why it exists:** Financial calculations are the product. A 1-pence error in IHT is a customer-trust event. Logic tests are unit tests with *financial-correctness* exit criteria — they assert specific currency values for specific scenarios, not just "function returns".

**Scope this gauntlet:** golden-file fixtures for the 6 preview personas walked end-to-end through every calculator; documented expected values reviewed by CSJ; calculation regressions surface here, not in the browser.

### 2.3 Systems (G-2)
**What:** Integration / feature tests at the API boundary. Each Pest Feature suite hits a real controller, real DB (RefreshDatabase trait), real observers, real service provider container. No mocks except external HTTP (Revolut/xAI).

**Why it exists:** Unit tests passing is necessary but insufficient — the wiring between layers (pack service providers, observer chains, polymorphic morph resolution, eager-load joins) is where the architecture campaign's risk lives. Systems tests assert "the wiring is correct" not just "each piece works in isolation".

**Scope this gauntlet:** 100% of API controllers have at least one Feature test exercising the happy path; observer chains fire correctly post-relocation; pack service providers bind every contract.

### 2.4 E2E (G-3)
**What:** Playwright browser tests on `csjones.co/fynla_inter` walking each of the 6 seeded preview personas through every module the persona owns. Real UI, real API, real DB, real auth.

**Why it exists:** The frontend has 713 Vue components and 39 Vuex stores all referencing the relocated namespaces (through API endpoints). Unit and Systems tests don't exercise the browser-to-server contract. E2E catches the regressions that only manifest when a real user clicks.

**Scope this gauntlet:** every persona → every module → CRUD round-trip; the 13 numbered rules in CLAUDE.md exercised (preview write-block, currency formatting, joint ownership, etc.); zero console errors during a clean run.

### 2.5 Security (G-4)
**What:** OWASP Top 10 audit; auth-flow review (login, register, password reset, 2FA, biometric, preview-write-interceptor); input validation across all 83 form requests; SQL injection / XSS / CSRF posture; dependency CVE scan; secret management audit (.env files, key rotation procedure, log redaction); polymorphic morph aliases verified for known-good values only (Sanctum can't be tricked into authing against a non-User class).

**Why it exists:** Financial data is regulated (UK GDPR, FCA marketing rules even in beta) and prod runs on shared hosting with 370 active sessions. A security review that uncovers a vuln before prod cutover is a saved breach.

**Scope this gauntlet:** the `security-and-hardening` skill runs against every newly-relocated controller + every user-input boundary; findings tracked to closure; one external review pass (an LLM-driven audit qualifies if rigorous).

### 2.6 Hardening (G-5)
**What:** Production-readiness configuration: tighten CSP (currently blocking Google Fonts + GA + FB Pixel — needs deliberate allow-list or self-host), rate limit auth endpoints, log redaction for PII, error boundary on every Vue route, retry/timeout on every external HTTP call, observability (Sentry or equivalent), structured logging compliance.

**Why it exists:** The dev env runs on `APP_DEBUG=true` with permissive defaults. Prod needs `APP_DEBUG=false` with the same UX *and* the deliberate hardening layer that catches everything debug mode hides.

**Scope this gauntlet:** every item in `app/Http/CLAUDE.md` and `core/CLAUDE.md` security checklists addressed; CSP allow-list explicit; rate-limit middleware on auth routes; .env.production templates contain only safe defaults.

### 2.7 User (G-6)
**What:** CSJ + 1–2 trusted users (friends/family) work through the app daily for 1–2 weeks on `csjones.co/fynla_inter`. Real workflows, real data entry, real edge cases. Bugs filed to a triage backlog.

**Why it exists:** Automated tests find regressions; humans find usability bugs, missing affordances, copy errors, calculator-output surprises, and "this number can't be right" reactions that test fixtures never hit because the tester wrote the fixture.

**Scope this gauntlet:** every module gets one full user-driven walk-through per week for 2 weeks; bugs tracked; severity-1 (data wrong) issues block prod cutover; severity-2 (UX) issues don't block but get logged for v1.1.

### 2.8 Beta (G-7)
**What:** 5–10 invited users (not Fynla insiders) given accounts on `csjones.co/fynla_inter` for 2 weeks. NDA + onboarding call + structured feedback survey. Same severity ladder as user testing.

**Why it exists:** CSJ's mental model is the product designer's mental model. Beta users come without that calibration and surface the gaps. The architecture campaign is invisible to them — they'll find UX, copy, and "this doesn't do what I expected" bugs the build team can't see.

**Scope this gauntlet:** structured invitation + onboarding; weekly survey; weekly bug triage; explicit "what would stop you using this" question; explicit consent to data being thrown away post-beta (dev DB).

### 2.9 Prod readiness (G-8)
**What:** Final review checklist; cutover plan rehearsal; rollback plan documented; prod migration dry-run on a DB snapshot; the 5 outstanding items from `feedback_prod_deploy_freeze.md` re-validated; CSJ signs off in writing (or chat).

**Why it exists:** No gauntlet is perfect. G-8 is the deliberate pause where we ask "what would we wish we'd done?" and either do it or accept the risk consciously.

**Scope this gauntlet:** a single checklist doc, every item ticked or risk-accepted; a written go/no-go from CSJ; the deploy note at `May/May12Updates/deploy-2026-05-12.md` re-validated against current state.

---

## 3. The mutual-exclusion principle

**No new features ship during the gauntlet.** Bug fixes from gauntlet findings are allowed (and expected); net-new functionality is not. Each new feature is a moving target that invalidates the layers tested before it.

Two narrow exceptions:
1. **Hardening adjustments** are not features. CSP allow-list tweaks, rate-limit tuning, log redaction — all in scope.
2. **Severity-1 bug fixes** that surface during testing. A calculator returning wrong tax is in scope to fix even mid-gauntlet; a re-test of the affected layer is then mandatory.

The temptation to "just slip in one more feature before launch" is the single biggest threat to the gauntlet's value. Resist it.

---

## 4. Exit criteria per layer (what "done" means)

| Layer | Exit gate |
|-------|-----------|
| **G-1 Unit/Logic** | All 2,825+ tests green on dev DB; coverage report shows ≥ 70% on `core/`, ≥ 80% on `packs/country-gb/src/`; logic fixtures for 6 personas signed off by CSJ. |
| **G-2 Systems** | Every API controller has ≥ 1 Feature test green; observer-chain tests assert `RecommendationCacheObserver`, `RecalculateRiskProfileObserver`, `MonteCarloTriggerObserver` fire on the new namespaces; PackIsolationTest green. |
| **G-3 E2E** | All 6 personas × all 7 modules = 42 journeys green in Playwright; zero browser console errors during clean run; the 13 numbered rules in CLAUDE.md verified by automated assertions where automatable, manually otherwise. |
| **G-4 Security** | OWASP Top 10 checklist complete; zero high/critical CVEs in dependencies; auth flows reviewed by external (LLM-driven) audit; `security-and-hardening` skill green on every controller. |
| **G-5 Hardening** | CSP allow-list explicit (no `unsafe-inline` where avoidable); auth endpoints rate-limited; log redaction in place for PII; Sentry or equivalent wired; `app/Http/CLAUDE.md` checklist green. |
| **G-6 User** | 2 weeks of daily use by CSJ + 1–2 trusted users with no severity-1 bugs in week 2. |
| **G-7 Beta** | 2 weeks of use by 5–10 external beta users with severity-1 bug rate < 1 per user-week in week 2. |
| **G-8 Prod readiness** | Written go/no-go from CSJ; rollback plan signed off; cutover plan rehearsed against a DB snapshot. |

The gauntlet exit is the *intersection* of all 8. A single layer red blocks prod.

---

## 5. Constraints

- **Timebox:** ~8 weeks from 2026-05-12, target prod cutover ~2026-07-07. Hard end: 12 weeks (2026-08-04). Beyond 12 weeks, re-baseline the plan rather than slip indefinitely.
- **Team:** CSJ + me (Claude). Single-developer pace. No external QA, no QA engineer, no security consultant (LLM-driven audits substitute).
- **Environment:** `csjones.co/fynla_inter` is the only deploy target. No prod access. Real Revolut sandbox. Real xAI/Grok dev key (when CSJ provides it). Real `noreply@fynla.org` SMTP (shared with prod — care with lifecycle test recipient override).
- **Data:** seeded preview personas + any beta-user data. No real customer data. Beta data discarded post-gauntlet.
- **Tooling:** Pest, Playwright (via MCP), Composer audit, `security-and-hardening` skill, `tech-debt-session` skill. No paid scanners; if needed, surface and ask.
- **Cost:** the prod freeze is the cost. Customer-acquisition is paused. Marketing pages can stay live; new-signup pages may be disabled or shipped to a "coming soon" splash if it reduces support load during the gauntlet.

---

## 6. Non-goals

- **Performance optimisation** unless a benchmark surfaces a red flag (>2s page load, >500ms API). The architecture relocation should be performance-neutral.
- **New module work** — Protection / Savings / Investment / Retirement / Estate / Goals / Coordination all stay feature-frozen. Mobile (iOS Capacitor) is on the same freeze.
- **Visual redesign / fynlaDesignGuide.md v1.4+** — design system is locked at v1.3.0 for the duration.
- **Multi-country expansion** — SA pack stays at its current shape. No new countries during the gauntlet.
- **Migrating to Sonnet 4.6 / model upgrades** — AI provider config flipped to xAI/Grok in this campaign; further provider work is out of scope.

---

## 7. Open questions (to be resolved during PRD interview)

1. **Beta user pool** — does CSJ have 5–10 candidates in mind, or does recruiting belong inside G-7?
2. **External security review** — LLM-driven audit only, or does CSJ want one paid pen-test? Cost / scope?
3. **Definition of "logic test fixture sign-off"** — does CSJ review every persona's calculator outputs personally, or sample-and-trust?
4. **Hardening rollback plan** — if CSP tightening breaks a third-party we care about (Awin attribution, Google Analytics), do we relax CSP or drop the third-party?
5. **Beta-user account lifecycle** — accounts deleted post-gauntlet, or migrated to prod accounts at cutover? GDPR posture either way.
6. **The Revolut sandbox webhook** — needs registering via SiteGround / Revolut sandbox dashboard manually; in scope for G-0 (setup) or G-7 (beta) when payment-touch finally matters?
7. **What counts as "severity-1"** — concrete definition CSJ wants enforced (e.g. "calculator output wrong by ≥ £100" vs "any UI crash" vs both).
8. **Lifecycle email engine on dev** — currently enabled with `chris@fynla.org` override. Stays so for the whole gauntlet, or do we let beta users get real-looking emails (still routed via override)?
9. **`AGENT_INTERNAL_TOKEN`** — rotated on bootstrap; does CSJ need it stored anywhere outside the server `.env`?
10. **R-16 cleanup** (remove the `class_alias` in `CoreServiceProvider::boot()`) — does it ship at cutover, or as a follow-up after prod runs the morph backfill migration?

These flow into the PRD interview. CSJ decides; the PRD documents the decisions.

---

## 8. Companion documents

- **Plan:** `Plans/test-gauntlet-plan-v1.md` — workstream-by-workstream execution detail.
- **PRD:** generated via `/prd-writer` once this spec + the plan are reviewed.
- **Memory hint:** [[feedback-prod-deploy-freeze]] — the policy this spec executes against.
- **Architecture context:** [[Plans/architecture-spec-v3.md]] + [[Plans/architecture-plan-v3.md]] — what the campaign actually did. Prerequisite reading.
