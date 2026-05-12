---
type: plan
date: 2026-05-12
status: draft
companion_spec: Plans/test-gauntlet-spec-v1.md
prd: (pending — to be generated via /prd-writer)
supersedes: []
---

# Test Gauntlet Plan v1 — Pre-Production Validation for the UK Pack Architecture

This plan executes the quality bar defined in `Plans/test-gauntlet-spec-v1.md`. It is the only route from `refactor/uk-pack-relocation` to `fynla.org`. Branch state at plan start: tip `2e9fcb6`, 107 commits ahead of `main`, dev env `csjones.co/fynla_inter` green.

The plan is structured as nine workstreams (G-0 → G-8). Each has a scope, exit gate, dependencies, and effort estimate. Workstreams run in parallel where dependencies allow; gates are sequential.

---

## 0. Operating principles

1. **One workstream owner per layer.** CSJ owns user/beta/prod-readiness gates. I own automated layers (unit/systems/E2E/security/hardening). CSJ reviews and signs off.
2. **Bug triage cadence:** weekly. Severity-1 (data wrong, security, auth broken) blocks the workstream; severity-2 (UX, copy) logs to a v1.1 backlog.
3. **Re-test discipline:** any bug fix in a passed-layer triggers re-validation of that layer. No exceptions. Re-tests are cheaper than missed regressions.
4. **No new features during the gauntlet** (per spec § 3). Hardening adjustments and bug fixes only.
5. **Dev env is sacred.** No production deploy from this branch until G-8 sign-off lands.
6. **Daily status surface:** end-of-day handover lists which workstreams advanced and which bugs landed. Same pattern as R-0 → R-14b sessions.
7. **Honesty over optimism.** A workstream is red if it has ANY open severity-1, regardless of how close to done it feels.

---

## 1. Workstream G-0 — Setup & baseline (~4 hr, week 1)

**Scope:** finish the bootstrap items deferred during 2026-05-12 dev provisioning.

| # | Step | Owner | Status as of 2026-05-12 |
|---|------|-------|-------------------------|
| G-0-i | SiteGround cron entry for `fynla_inter` (Site Tools → Devs → Cron Jobs) | CSJ | pending (task #17) |
| G-0-ii | XAI/Grok dev API key in server `.env`; `php artisan config:clear` | CSJ paste, me apply | pending (task #12) |
| G-0-iii | Revolut sandbox webhook registered at `https://csjones.co/fynla_inter/api/payment/webhook`; `wsk_…` into `.env` | CSJ register, me apply | pending |
| G-0-iv | Lifecycle test recipient override verified (`config('lifecycle.test_recipient_override')` returns `chris@fynla.org`) | me | open — returned `UNSET` during smoke; likely config key mismatch, needs investigation |
| G-0-v | Beta-ready user pool identified (5–10 candidates with consent intent) | CSJ | pending |
| G-0-vi | Triage backlog created (GitHub issues, Linear, or a tracked markdown doc in `May/`) | CSJ + me | pending |

**Exit gate:** all six items green. `php artisan tinker --execute="…"` returns the four guardrail values (env=staging, revolut=sandbox, ai_provider=xai, lifecycle_test=chris@fynla.org) and lifecycle scheduler shows the daily job.

**Dependencies:** none.

---

## 2. Workstream G-1 — Unit & Logic (~1 week, week 1–2)

**Scope:** verify the 2,825-test Pest baseline holds on dev DB; audit coverage of relocated namespaces; build the logic-fixture golden files.

### G-1-a: Baseline re-confirmation (~0.5 day)

```bash
# On csjones.co/fynla_inter:
cd ~/www/csjones.co/fynla_inter-app && ./vendor/bin/pest
```

Expected: 2,825 passing, 1 skipped (pre-existing flaky InvestmentControllerTest). Anything else → triage and fix before proceeding.

### G-1-b: Coverage audit (~1 day)

```bash
./vendor/bin/pest --coverage --coverage-clover=coverage.xml
```

Target: `core/app/Core/` ≥ 70%, `packs/country-gb/src/` ≥ 80%, `app/Services/` ≥ 75%. Gaps documented; fill the largest ones first.

### G-1-c: Logic fixture golden files (~3 days)

For each of the 6 preview personas (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple):
- Walk the seeded data through every calculator (IHT, CGT, Income Tax, Pension AA, MPAA, ISA, TFSA-equivalent if SA).
- Document expected output values to the nearest pence.
- Store as Pest data providers: `tests/Unit/LogicFixtures/{persona}_calculator_test.php`.
- CSJ reviews and signs off each persona's fixture set in writing.

**Exit gate:** baseline green, coverage targets met or gap-list documented with acceptance from CSJ, all 6 persona fixtures signed off.

**Dependencies:** G-0 complete.

**Parallel work allowed:** G-4 can start (security audit doesn't need test infrastructure).

---

## 3. Workstream G-2 — Systems integration (~1 week, week 2–3)

**Scope:** every API controller has Feature-test coverage; observer chains verified; pack service providers correctly bind every contract; cross-namespace polymorphic resolution works.

### G-2-a: API controller coverage matrix (~2 days)

For each of the 89 API controllers in `app/Http/Controllers/Api/` + `packs/*/src/Http/Controllers/`:
- ≥ 1 happy-path Feature test using `Sanctum::actingAs($user)`.
- ≥ 1 unauthenticated-rejection test asserts 401.
- ≥ 1 unauthorized-resource test asserts 403 (where applicable).

Matrix tracked in `Plans/test-gauntlet-coverage-matrix.md` (created G-2-a-i). Each row: controller, has-happy, has-auth, has-authz, blocker if red.

### G-2-b: Observer chain verification (~1 day)

The 12 observers in `app/Observers/` (`RecommendationCacheObserver`, `RecalculateRiskProfileObserver`, `MonteCarloTriggerObserver`, `GoalContributionTracker`, etc.) need explicit Feature tests:
- Create the triggering model
- Assert the observer fired (mock the downstream service or check log entry)
- Verify the observer resolves the relocated model namespaces correctly

### G-2-c: Pack service provider audit (~1 day)

Every contract in `core/app/Core/Contracts/` must have a binding in either `GbPackServiceProvider` (real) or `ZaPackServiceProvider` (Null impl or real). Run a container-resolution test that walks every binding:

```php
foreach (PackRegistry::codes() as $code) {
  foreach (CoreContracts::all() as $contract) {
    assert(app()->bound("pack.{$code}.{$contract}"));
    assert(app("pack.{$code}.{$contract}") instanceof $contract);
  }
}
```

### G-2-d: Polymorphic morph resolution (~0.5 day)

Sanctum's `personal_access_tokens.tokenable_type`, `notifications.notifiable_type`, `audit_logs.model_type`, plus any other polymorphic relations — all must resolve to `Fynla\Core\Models\User` (or the appropriate pack model) and accept BOTH legacy and new namespace strings during the migration window (the `class_alias` in `CoreServiceProvider::boot()` handles this). Feature test that creates a token with each string variant and asserts auth still works.

### G-2-e: PackIsolationTest reconfirmation (~0.5 day)

The R-14b-deferred allow-list must remain empty. Add a guard test:

```php
it('R-14b allow-list is empty')->expect(fn () => /* parse PackIsolationTest source */)->toBe([]);
```

**Exit gate:** coverage matrix green for every controller; all 12 observer chains have Feature tests; every contract bound; morph resolution verified; PackIsolationTest allow-list empty.

**Dependencies:** G-1 complete.

**Parallel work allowed:** G-4 continues; G-5 may start CSP planning.

---

## 4. Workstream G-3 — End-to-end browser (~1.5 weeks, week 3–5)

**Scope:** every persona walks every module in Playwright. Zero console errors. Numbered-rules compliance verified.

### G-3-a: Test infrastructure (~1 day)

- Pin Playwright version
- Build a fixture harness that logs in as a given persona, fetches the verification code from the DB, lands on the dashboard
- Add a helper that asserts "0 errors in console" after each navigation

### G-3-b: Per-persona × per-module journey scripts (~5 days)

6 personas × 7 modules = 42 journey scripts. Each:
- Login as persona
- Navigate to module
- Verify dashboard summary cards render with expected currency values
- Verify list views render with seeded records
- CRUD round-trip on one record (create → read → update → delete)
- Verify changes persist across reload
- Verify the relevant Vuex store state is consistent
- Logout

### G-3-c: Cross-module flows (~2 days)

Specifically the Holistic Plan view and Coordination agent flows — these touch multiple pack services in one request. 5 scripted scenarios:
1. Goal creation → contribution tracking → goal status update on dashboard
2. Property purchase → mortgage record → net worth recalculation
3. SIPP contribution → pension AA tracking → MPAA flag
4. Life event (marriage) → tax allowance recalculation
5. IHT scenario with gifts → 7-year taper test

### G-3-d: Numbered-rules compliance (~1 day)

Programmatic assertions for the 13 rules in CLAUDE.md where automatable:
- Rule 2: Preview write blocked (POST as preview user → 403)
- Rule 4: Form-modal `save` event fires once
- Rule 5: Canonical enums in API responses
- Rule 6: All currency rendered via `currencyMixin` (DOM scan)
- Rule 9: No amber/orange colors in computed styles
- Rule 10: No acronyms in user-facing text (excl. ISA/TFSA) — DOM scan
- Rule 13: No score values in user-facing UI (DOM scan)

The remaining 6 rules need manual review.

**Exit gate:** 42 journey scripts + 5 cross-module flows + 7 automated rule checks all green. Zero console errors during a full clean run.

**Dependencies:** G-2 complete.

**Parallel work allowed:** G-5 hardening in flight; G-4 closing.

---

## 5. Workstream G-4 — Security audit (~1 week, weeks 2–4 parallel)

**Scope:** OWASP Top 10 walk-through, auth-flow review, dependency CVE scan, secret management, polymorphic-morph-aliases attack surface, log redaction posture.

### G-4-a: Dependency CVE scan (~0.5 day)
```bash
composer audit
npm audit --production
```
Triage findings; patch what's patchable; document risk-accepted CVEs (with reasoning).

### G-4-b: OWASP walk-through (~2 days)

Invoke the `security-and-hardening` skill against:
- All 89 API controllers
- All 83 form requests
- The auth flow (login, register, password reset, 2FA, biometric)
- The preview-write-interceptor middleware
- The Revolut webhook handler

Findings tracked; HIGH/CRITICAL fixed before exit; MEDIUM/LOW logged with risk acceptance.

### G-4-c: Polymorphic morph attack surface (~0.5 day)

Verify Sanctum, notifications, audit_logs morph types CANNOT be tricked into authing as a non-User class. The `class_alias` accepts `App\Models\User` → `Fynla\Core\Models\User`, but no other string. Test by attempting to forge a PAT with `tokenable_type='\App\Console\Command'` and asserting it fails to authenticate.

### G-4-d: Secret management audit (~0.5 day)

- `.env` files on server have 600 perms (verify on dev)
- No real secrets in repo (re-grep `deploy/*/.env.production` after the recent commit)
- Log redaction for `password`, `api_key`, `sk_`, `wsk_`, `xai-` patterns in `storage/logs/laravel.log`
- Mail password rotation procedure documented

### G-4-e: Auth-flow review (~1 day)

Manual review against the documented auth-flow checklist. Specifically:
- The mobile biometric flow (`auth/mobileLogout` vs `auth/logout` mistake from CLAUDE.md)
- The verification-code rate limit
- The preview-mode write block on auth-related routes (`EXCLUDED_ROUTES`)

### G-4-f: Externalised audit (~1 day, optional)

Run a second-opinion LLM-driven audit (different model than Claude — Grok via xAI maybe, or Gemini if convenient). Capture findings.

**Exit gate:** OWASP Top 10 checklist complete; zero high/critical CVEs unpatched; secret-management findings closed; written sign-off from CSJ.

**Dependencies:** G-1 complete (knowing the unit-test baseline matters before chasing security).

**Parallel work allowed:** G-1, G-2 continue; G-5 may start.

---

## 6. Workstream G-5 — Hardening (~1 week, weeks 3–5 parallel)

**Scope:** convert dev's permissive defaults into prod-grade configuration that still passes G-3 E2E and G-6 User testing.

### G-5-a: CSP allow-list (~1 day)

Current CSP blocks Google Fonts, GA, FB Pixel (visible in browser console). Decide per source:
- Self-host (Google Fonts → static `/fonts/` directory)
- Add to allow-list (GA, FB Pixel — explicit `script-src` entries)
- Drop (if no business case)

Tests: every page in the journey suite passes `0 console errors` with the new CSP.

### G-5-b: Rate-limit middleware (~0.5 day)

`throttle:5,1` on login, register, password reset, verification-code resend. Existing Laravel `throttle:` middleware suffices.

### G-5-c: Log redaction (~0.5 day)

Custom log processor that redacts `password`, `api_key`, `secret`, `token`, `Authorization` headers, anything matching `sk_…` / `pk_…` / `wsk_…` / `xai-…` from log entries. Apply globally via `app/Providers/AppServiceProvider`.

### G-5-d: Error boundary on every Vue route (~1 day)

Each top-level view in `resources/js/views/` and `packs/*/resources/js/views/` wrapped in an `<ErrorBoundary>` component that catches render errors, logs to console + (eventually) Sentry, and shows a graceful fallback.

### G-5-e: External-HTTP timeouts (~0.5 day)

Audit every `Http::get/post/…` and `Client::request(…)`:
- Explicit timeout (10s default, document per call)
- Retry policy where idempotent (exponential backoff)

Endpoints to audit: Revolut, xAI, mail, AWIN tracking.

### G-5-f: Observability (~1 day)

Wire Sentry (or equivalent) for both Laravel and Vue. Free tier acceptable; DSN in `.env`. Crucial: must include the `environment=staging` tag so dev/prod errors are separable when prod eventually lights up.

### G-5-g: Production .env validation script (~0.5 day)

A `php artisan env:validate` command that checks the production `.env` has:
- `APP_ENV=production` and `APP_DEBUG=false`
- `REVOLUT_SANDBOX=false`
- No `LIFECYCLE_TEST_RECIPIENT` override
- A real `APP_KEY`, not the template placeholder
- DB / mail / Revolut / AI credentials present

Runs as part of prod-cutover dry-run in G-8.

**Exit gate:** every checklist item in `app/Http/CLAUDE.md` and the security section of `core/CLAUDE.md` ticked. Dev still functions correctly under tightened config. Sentry receives test errors from both Laravel and Vue.

**Dependencies:** G-3 in progress (so we can verify nothing breaks).

---

## 7. Workstream G-6 — User testing (~2 weeks clock, week 5–7)

**Scope:** CSJ + 1–2 trusted users use the app daily for 2 weeks. Bugs filed; severity-1 blockers fixed mid-flight.

### G-6-a: Test plan per user (~1 day to draft)

Each user gets a structured daily script of ~30 min:
- Day 1–3: onboarding + adding records (income, expenditure, property, pension, ISA, life policy)
- Day 4–7: scenario work (set a goal, run a what-if, view holistic plan)
- Day 8–14: free exploration + targeted regression on known edge cases

### G-6-b: Daily standup (~5 min/day)

Bugs surfaced go to the triage backlog (created in G-0-vi). Daily 5-min triage: severity assignment, owner assignment. Severity-1 fixed within 24h.

### G-6-c: Week-1 retro (~2 hr)

Review the week's bugs. Update the test plan if patterns emerge ("we haven't touched X module — schedule it"). Decide whether week 2 continues as planned or pivots.

### G-6-d: Week-2 stabilisation

Goal: zero severity-1 bugs found in week 2. Severity-2 bugs queued for v1.1 backlog.

**Exit gate:** 2 weeks elapsed; week-2 finished with zero new severity-1 bugs; CSJ signs off that "I would let a friend use this".

**Dependencies:** G-3 complete (no UI bugs reaching humans), G-4 + G-5 high-priority items closed.

---

## 8. Workstream G-7 — Closed beta (~2 weeks clock, week 7–9)

**Scope:** 5–10 invited external users on `csjones.co/fynla_inter` for 2 weeks.

### G-7-a: Beta recruitment kit (~1 day)

- NDA template
- Onboarding email + welcome PDF
- Account creation procedure (CSJ creates, sends credentials)
- Structured weekly survey (Google Form, Typeform, or markdown email)
- Bug-reporting channel (Slack, email, Linear, whatever beta users prefer)

### G-7-b: Onboarding calls (~30 min × beta users)

CSJ runs a short onboarding call per user. Explains the gauntlet context, sets expectations ("data won't migrate"), demos the modules they'll use.

### G-7-c: Weekly survey + bug triage (~2 hr/week)

Same triage cadence as G-6. Severity-1 fixes block the workstream.

### G-7-d: Week-2 retro + go/no-go input

Beta users complete a structured exit survey. Findings inform G-8 decision.

**Exit gate:** 2 weeks elapsed; severity-1 rate < 1 per user-week in week 2; written beta feedback summary.

**Dependencies:** G-6 complete.

---

## 9. Workstream G-8 — Prod readiness review (~3 days, week 9)

**Scope:** the final pause before cutover (which is itself out of scope for the gauntlet — cutover happens after CSJ signs off here).

### G-8-a: Cutover-plan rehearsal (~1 day)

Walk through the deploy steps documented in `May/May12Updates/deploy-2026-05-12.md` against a current snapshot of prod. Note divergences.

### G-8-b: Rollback-plan documentation (~0.5 day)

Single doc covering:
- Reverting to the May 6 prod state
- Restoring the previous `public/build/`
- DB-rollback decision tree (the morph backfill migration is one-way — but the `class_alias` makes a code rollback work without DB rollback)
- Customer-comms script if downtime exceeds 10 min

### G-8-c: Final security re-check (~0.5 day)

Run `composer audit` + `npm audit --production` + a final `security-and-hardening` skill pass against the diff since G-4 closed.

### G-8-d: Go/no-go writeup (~0.5 day)

A single markdown doc:
- All 8 layer exit gates: green / yellow / red with notes
- Outstanding severity-2 bugs (count + list)
- Outstanding severity-1 bugs (must be zero — if any, no-go)
- Beta feedback summary
- Estimated time to recover from common failure modes
- CSJ's written go/no-go decision

**Exit gate:** CSJ's written go (or no-go with re-baseline of timeline).

**Dependencies:** G-7 complete.

---

## 10. Sequencing and the calendar

Calendar week numbering starts 2026-05-12 (Monday).

| Week | Calendar | G-0 | G-1 | G-2 | G-3 | G-4 | G-5 | G-6 | G-7 | G-8 |
|------|----------|-----|-----|-----|-----|-----|-----|-----|-----|-----|
| 1 | May 12–18 | ▶ → ✓ | ▶ | | | ▶ | | | | |
| 2 | May 19–25 | | ✓ | ▶ | | ↻ | | | | |
| 3 | May 26–Jun 1 | | | ✓ | ▶ | ✓ | ▶ | | | |
| 4 | Jun 2–8 | | | | ↻ | | ↻ | | | |
| 5 | Jun 9–15 | | | | ✓ | | ↻ | ▶ | | |
| 6 | Jun 16–22 | | | | | | ✓ | ↻ | | |
| 7 | Jun 23–29 | | | | | | | ✓ | ▶ | |
| 8 | Jun 30–Jul 6 | | | | | | | | ↻ | |
| 9 | Jul 7–13 | | | | | | | | ✓ | ▶ → ✓ |

Buffer: ~3 weeks (Jul 14 → Aug 4) before the hard 12-week stop. Buffer is for severity-1 fixes mid-gauntlet that re-open closed layers. If buffer exhausted, re-baseline rather than slip silently.

**Hard stop:** if at 12 weeks (2026-08-04) the gauntlet has not cleared, write a new plan v2 that either narrows scope, accepts higher risk, or extends the freeze. Don't continue indefinitely with no exit criterion.

---

## 11. What's not in this plan

- **Cutover execution** — the actual `composer install --no-dev` + `migrate --force` + cache-clear on prod. Out of scope for the gauntlet. Happens in a separate session after G-8 sign-off.
- **R-16 cleanup** (remove the `class_alias` in `CoreServiceProvider::boot()`). Deferred to after the prod morph backfill migration has run (i.e. post-cutover).
- **Prod marketing relaunch / customer comms** — the gauntlet is a build-side activity. Customer-facing relaunch planning happens in parallel and is CSJ-owned.
- **New feature work** — Protection / Savings / Investment / Retirement / Estate / Goals / Coordination all stay feature-frozen. Same for mobile.
- **Multi-country expansion** — SA pack stays at current shape; no new countries during the gauntlet.

---

## 12. Risk register

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Beta-user recruitment slow | Medium | High (delays G-7) | Start in G-0; have a fallback of "extend user testing G-6 to fill the gap" |
| Severity-1 calc bug found late | Medium | High (re-tests preceding layers) | Logic fixture sign-off in G-1 should catch most; budget for at most 2 re-tests in the calendar |
| External Sentry-equivalent costs surprise | Low | Low | Use free tier; if exhausted, fall back to file-based structured logs |
| CSJ unavailability mid-gauntlet | Medium | Medium | Plan assumes part-time CSJ engagement; calendar has slack |
| AI assistant context limit hit | Low | Low | Session-end / handover protocol already handles this |
| Single-developer burnout | Medium | High | Daily handovers force pacing; week-1 retros adjust if needed |
| Architecture regression found in G-3 (campaign bug) | Low | Critical | Revert to specific R-N commit and re-run from there; campaign branch is well-tagged |
| Prod freeze pressure (revenue impact) | High | Medium | Spec § 5 acknowledges this; CSJ business decision |

---

## 13. Companion documents

- **Spec:** `Plans/test-gauntlet-spec-v1.md`
- **PRD:** generated via `/prd-writer` after spec + plan review.
- **Coverage matrix (to be created in G-2-a):** `Plans/test-gauntlet-coverage-matrix.md`
- **Bug triage backlog (to be created in G-0-vi):** location TBD per CSJ
- **Memory:** [[feedback-prod-deploy-freeze]] — the policy this plan executes against.
- **Architecture context:** [[Plans/architecture-spec-v3.md]] + [[Plans/architecture-plan-v3.md]] — what the campaign actually did.
