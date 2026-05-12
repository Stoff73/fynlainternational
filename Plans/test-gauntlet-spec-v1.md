---
type: spec
date: 2026-05-12
status: amended — 2026-05-12 — conflicts resolved against codebase audit
supersedes: []
companion_plan: Plans/test-gauntlet-plan-v1.md
prd: May/May12Updates/PRD-test-gauntlet-v1.md
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

**Why it exists:** Catch math errors, off-by-one bugs, wrong enum handling at the smallest possible scope. The ~2,669-test Pest baseline is already green locally — this layer's job is to confirm coverage is *adequate*, not just present, and to top up gaps the campaign opened.

**Scope this gauntlet:** verify the current baseline (~2,669 `it()` blocks across `tests/**/*.php` + `packs/**/*.php`) passes on dev; audit coverage of newly-relocated namespaces; add tests for any service that has zero unit coverage. Coverage-percentage is a leading indicator only — the *exit gate* (§ 4) requires observer-firing tests, not a coverage threshold.

### 2.2 Logic (G-1.5, inside G-1)
**What:** Tax math, IHT/CGT/Income Tax/Pension calculator outputs against known-correct fixtures. Cross-border interaction logic. Money rounding (`int_minor` ledger arithmetic, no float drift).

**Why it exists:** Financial calculations are the product. A 1-pence error in IHT is a customer-trust event. Logic tests are unit tests with *financial-correctness* exit criteria — they assert specific currency values for specific scenarios, not just "function returns".

**Scope this gauntlet:** golden-file fixtures for the 6 preview personas walked end-to-end through every calculator; documented expected values reviewed by CSJ; calculation regressions surface here, not in the browser.

### 2.3 Systems (G-2)
**What:** Integration / feature tests at the API boundary. Each Pest Feature suite hits a real controller, real DB (RefreshDatabase trait), real observers, real service provider container. No mocks except external HTTP (Revolut/xAI).

**Why it exists:** Unit tests passing is necessary but insufficient — the wiring between layers (pack service providers, observer chains, polymorphic morph resolution, eager-load joins) is where the architecture campaign's risk lives. Systems tests assert "the wiring is correct" not just "each piece works in isolation".

**Scope this gauntlet:** every API controller (~98 in `app/Http/Controllers/Api/` + `packs/*/src/Http/Controllers/`, including the 5 ZA controllers that import GB models cross-pack) has at least one Feature test exercising the happy path; the 13 observer chains (7 in `app/Observers/` + 6 in `packs/country-gb/src/Observers/`) fire correctly post-relocation; pack service providers bind every contract; **the morph backfill migration is replay-tested against a dirty-DB scenario** (not just an empty RefreshDatabase run).

### 2.4 E2E (G-3)
**What:** Playwright browser tests on `csjones.co/fynla_inter` walking each of the 6 seeded preview personas (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`) through every module the persona owns. Real UI, real API, real DB, real auth.

**Why it exists:** The frontend has 713 Vue components and 39 Vuex stores all referencing the relocated namespaces (through API endpoints). Unit and Systems tests don't exercise the browser-to-server contract. E2E catches the regressions that only manifest when a real user clicks.

**Scope this gauntlet:** every persona → every module → CRUD round-trip; the 13 numbered rules in CLAUDE.md exercised (preview write-block, currency formatting, joint ownership, etc.); zero console errors during a clean run.

### 2.5 Security (G-4)
**What:** OWASP Top 10 audit; auth-flow review (login, register, password reset, 2FA, biometric, preview-write-interceptor); input validation across all ~104 form requests (24 in `app/Http/Requests/` + 80 in `packs/country-gb/src/Http/Requests/`); SQL injection / XSS / CSRF posture; dependency CVE scan; secret management audit (.env files, key rotation procedure, log redaction); **horizontal-privilege-escalation test for polymorphic morph aliases** (can a PAT for user A with `tokenable_type='Fynla\Core\Models\User'` pointing at user B's ID authenticate as B and bypass resource-ownership checks?).

**Why it exists:** Financial data is regulated (UK GDPR, FCA marketing rules even in beta) and prod runs on shared hosting with 370 active sessions. A security review that uncovers a vuln before prod cutover is a saved breach.

**Scope this gauntlet:** the `security-and-hardening` skill runs against every newly-relocated controller + every user-input boundary; findings tracked to closure; **second-opinion LLM-driven audit only** (Grok / Gemini); no paid pen-test.

### 2.6 Hardening (G-5)
**What:** Production-readiness configuration: reconcile the dual CSP definitions (the `app/Http/Middleware/SecurityHeaders.php` middleware allows Google Fonts/GA/FB Pixel; the `deploy/*/.htaccess` fallback blocks them — eliminate the conflict), self-host Google Fonts, allow GA + Awin via explicit CSP `script-src` entries, drop FB Pixel; rate limit auth endpoints; log redaction for PII; error boundary on every Vue route; retry/timeout on every external HTTP call (the Revolut services alone have 15+ untimed call sites); observability (Sentry or equivalent); structured logging compliance.

**Why it exists:** The dev env runs on `APP_DEBUG=true` with permissive defaults. Prod needs `APP_DEBUG=false` with the same UX *and* the deliberate hardening layer that catches everything debug mode hides.

**Scope this gauntlet:** every item in `app/Http/CLAUDE.md` and `core/CLAUDE.md` security checklists addressed; CSP allow-list explicit (self-hosted fonts + GA + Awin only); rate-limit middleware on auth routes; `.env.production` templates contain only safe defaults. G-5 runs as a single "hardening checklist" gate rather than 7 sub-gates (audit recommendation).

### 2.7 User (G-6)
**What:** CSJ + 1–2 trusted users (friends/family) work through the app daily for 1–2 weeks on `csjones.co/fynla_inter`. Real workflows, real data entry, real edge cases. Bugs filed to a triage backlog.

**Why it exists:** Automated tests find regressions; humans find usability bugs, missing affordances, copy errors, calculator-output surprises, and "this number can't be right" reactions that test fixtures never hit because the tester wrote the fixture.

**Scope this gauntlet:** every module gets one full user-driven walk-through per week for 2 weeks; bugs tracked; severity-1 (numeric error ≥ £10, auth failure, data loss) issues block prod cutover; severity-2 (UX, copy, UI crashes that don't lose data) issues don't block but get logged for v1.1.

### 2.8 Prod readiness (G-7 — formerly G-8)
**What:** Final review checklist; cutover plan rehearsal; rollback plan documented including the explicit `class_alias` re-add step on the `main` branch (otherwise a post-migration code rollback returns 401 for all 370 PATs); prod migration dry-run on a DB snapshot; CSJ signs off in writing (or chat).

**Why it exists:** No gauntlet is perfect. G-7 is the deliberate pause where we ask "what would we wish we'd done?" and either do it or accept the risk consciously.

**Scope this gauntlet:** a single checklist doc, every item ticked or risk-accepted; a written go/no-go from CSJ; the deploy note at `May/May12Updates/deploy-2026-05-12.md` re-validated against current state.

---

## 2A. Pre-gauntlet workstream — G-(-1) Lifecycle engine (minimum viable)

Surfaced during codebase audit 2026-05-12: `config/lifecycle.php` does not exist and the lifecycle email engine described in `docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md` was never implemented. The `LIFECYCLE_TEST_RECIPIENT` env var that the dev `.env` template sets has nothing to read it. This is a pre-gauntlet workstream because G-0-iv depends on it.

**What:** minimum-viable lifecycle engine — stub `config/lifecycle.php`, build a single `LifecycleEngine` service with `dispatch($user, $event)`, wire `LIFECYCLE_TEST_RECIPIENT` override, register a `schedule:run` entry. Zero email templates / no event content / no scheduling matrix.

**Why it exists:** the dev env's lifecycle config returns `UNSET` during smoke testing; the test gauntlet can't validate "lifecycle emails route to override" if the plumbing doesn't exist.

**Scope:** ~4 hr of work. The full lifecycle email engine (10+ event types, scheduling matrix, audit logging) is **logged as tech debt** and slated for post-cutover delivery as its own spec → plan → PRD cycle.

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
| **G-(-1) Lifecycle engine** | `config/lifecycle.php` exists; `LifecycleEngine::dispatch()` route to override returns chris@fynla.org for all events; scheduler registers and `schedule:list` shows the daily entry; full implementation logged as tech debt. |
| **G-1 Unit/Logic** | ~2,669-test Pest baseline green on dev DB; logic fixtures for 6 personas (sampled 2 reviewed in full + delta-scan on other 4); observer-firing tests in place for all 13 observers (replaces coverage % gate). |
| **G-2 Systems** | Every of ~98 API controllers (incl. 5 ZA cross-pack) has ≥ 1 Feature test green; all 14 `pack.gb.*` bindings resolve via singleton-identity test; observer-chain tests assert `RiskRecalculationObserver`, `RecommendationCacheObserver`, `LifeEventMonteCarloObserver` + 6 GB pack observers fire on the new namespaces; **migration dirty-DB replay test green** (PAT with `App\Models\User` survives `up()` → canonicalised → re-run is no-op); **cache poisoning test green** (pre-relocation cache entries don't break post-deploy); `PackIsolationTest` `App\Models` sub-section empty + allow-list count ≤ baseline + 1 (counter ratchet). |
| **G-3 E2E** | All 6 personas × all 7 modules = 42 journeys green in Playwright; zero browser console errors during clean run; the 13 numbered rules in CLAUDE.md verified by automated assertions where automatable, manually otherwise. |
| **G-4 Security** | OWASP Top 10 checklist complete; zero high/critical CVEs in dependencies; auth flows reviewed by LLM-driven audit (Grok / Gemini); `security-and-hardening` skill green on every controller; **horizontal-privilege morph escalation test green** (forged cross-user PAT can't bypass ownership). |
| **G-5 Hardening** | Single checklist doc green — CSP middleware reconciled with `.htaccess` (self-host fonts + GA + Awin allow-list, FB Pixel dropped); auth endpoints rate-limited; log redaction for PII; ErrorBoundary on every Vue route; external-HTTP timeouts on all 15+ Revolut call sites + Awin + Postcode + Push; Sentry or equivalent wired; `php artisan env:validate` command exists; `app/Http/CLAUDE.md` checklist green. |
| **G-6 User** | 2 weeks of daily use by CSJ + 1–2 trusted users with no severity-1 bugs in week 2. Severity-1 := user-visible numeric error ≥ £10, OR auth failure, OR data loss. Severity-2 := UI/copy/rendering bugs that don't lose data. |
| **G-7 Prod readiness** | Written go/no-go from CSJ; rollback plan signed off (explicitly including the `class_alias` re-add step on `main` branch); cutover plan rehearsed against a DB snapshot; 2 R-14a residuals (`pack.gb.exchange_control`, `pack.gb.tax_optimisation`) tracked to resolution or risk-accepted. |

The gauntlet exit is the *intersection* of all 7 (G-7 was beta — deleted; numbering compressed). A single layer red blocks prod.

---

## 5. Constraints

- **Timebox:** ~6 weeks from 2026-05-12 (G-7 beta layer deleted; G-(-1) lifecycle engine ~4 hr only), target prod cutover ~2026-06-23. Hard end: 12 weeks (2026-08-04). Beyond 12 weeks, re-baseline rather than slip indefinitely. Buffer ~5 weeks before hard stop — adequate per audit if no more than 2 severity-1 bugs surface in G-6.
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

## 7. Open questions — resolved 2026-05-12 (PRD interview)

All 10 spec-time open questions resolved during the codebase-audit-driven interview:

| # | Question | Resolution |
|---|----------|-----------|
| 1 | Beta user pool | **G-7 (beta) deleted entirely.** G-6 internal user-testing (CSJ + 1–2 trusted friends) is the only human-validation layer. No external recruitment, no GDPR exposure, no `beta:purge` artisan command needed. |
| 2 | External security review | **LLM-driven only** (Grok / Gemini via `security-and-hardening` skill). No paid pen-test. |
| 3 | Logic-fixture sign-off depth | **Sample-and-trust.** CSJ reviews 2 personas in full (~4-6 hr); delta-scans the other 4. |
| 4 | CSP rollback posture | **Self-host fonts + relax CSP for GA + Awin; drop FB Pixel.** Reconcile the dual definition (middleware vs `.htaccess`). |
| 5 | Beta-user account lifecycle | Moot — G-7 deleted. |
| 6 | Revolut sandbox webhook timing | **Register at G-0.** Internal users in G-6 may hit subscription flows; needs to work. |
| 7 | Severity-1 threshold | **User-visible numeric error ≥ £10**, OR auth failure, OR data loss. UI/copy = severity-2. |
| 8 | Lifecycle emails on dev | **Override to `chris@fynla.org` for the whole gauntlet** (no real users get them since G-7 is gone). |
| 9 | `AGENT_INTERNAL_TOKEN` storage | **Server `.env` only.** Lost-token recovery is "regenerate + redistribute". |
| 10 | R-16 cleanup timing | **Deferred to *post*-cutover** — once prod's backfill migration is verified clean, R-16 is a trivial follow-up commit. Not in gauntlet scope. |

### Additional resolutions from codebase audit (architectural)

- `widow` persona doesn't exist — replaced by `student` in seeder. CLAUDE.md amended, spec/plan amended, dead `widow` branches in `PreviewUserSeeder` to be removed.
- **Singleton fix** for `pack.gb.user_relations`, `pack.gb.asset_repo`, `pack.gb.estate_repo`, `pack.gb.asset_resolver` — applied pre-G-2.
- **G-3/G-5 resequence** — non-UI G-5 items (rate limit, log redaction, env-validate, external-HTTP timeouts) moved to weeks 2-3 in parallel with G-2; UI G-5 items (CSP, ErrorBoundary, Sentry browser-side) deferred until after G-3 is green.
- **G-5 collapse** from 7 sub-gates to one "hardening checklist" gate.
- **Pre-gauntlet G-(-1)** — minimum-viable lifecycle engine to unblock G-0-iv; full implementation = tech debt.
- **Week-6 checkpoint gate** — defined trigger for soft re-baseline if G-1 through G-4 not all green by end of week 6.
- **Migration dirty-DB replay** and **cache-poisoning** tests added to G-2.
- **Horizontal-privilege morph escalation** test replaces the class-injection variant in G-4.
- **PackIsolationTest counter enforcement** added to prevent silent allow-list growth.
- **`CoreContracts::all()` helper** created via filesystem scan to make G-2 binding walk testable.

---

## 8. Companion documents

- **Plan:** `Plans/test-gauntlet-plan-v1.md` — workstream-by-workstream execution detail.
- **PRD:** generated via `/prd-writer` once this spec + the plan are reviewed.
- **Memory hint:** [[feedback-prod-deploy-freeze]] — the policy this spec executes against.
- **Architecture context:** [[Plans/architecture-spec-v3.md]] + [[Plans/architecture-plan-v3.md]] — what the campaign actually did. Prerequisite reading.
