---
type: plan
date: 2026-05-12
status: amended — 2026-05-12 — conflicts resolved against codebase audit
companion_spec: Plans/test-gauntlet-spec-v1.md
prd: May/May12Updates/PRD-test-gauntlet-v1.md
supersedes: []
---

# Test Gauntlet Plan v1 — Pre-Production Validation for the UK Pack Architecture

This plan executes the quality bar defined in `Plans/test-gauntlet-spec-v1.md`. It is the only route from `refactor/uk-pack-relocation` to `fynla.org`. Branch state at plan start: tip `40d9ac4`, 108 commits ahead of `main`, dev env `csjones.co/fynla_inter` green.

The plan is structured as **eight** workstreams (G-(-1), then G-0 → G-7). G-7 (closed beta) was deleted during the codebase-audit-driven interview (CSJ Q7→(c)); the original G-8 (prod readiness review) renumbered to G-7. G-(-1) is a pre-gauntlet workstream that landed during the audit (lifecycle engine doesn't exist).

Each workstream has a scope, exit gate, dependencies, and effort estimate. Workstreams run in parallel where dependencies allow; gates are sequential.

---

## 0. Operating principles

1. **One workstream owner per layer.** CSJ owns user/prod-readiness gates. I own automated layers (unit/systems/E2E/security/hardening). CSJ reviews and signs off.
2. **Severity ladder:**
   - **Severity-1** (blocks workstream, blocks cutover): any user-visible numeric error ≥ £10, OR any auth failure (login, register, password reset, 2FA, biometric, session resumption), OR any data loss (record created → record disappears).
   - **Severity-2** (logs to v1.1 backlog, doesn't block): UI/rendering/copy bugs, layout breaks, micro-copy errors, console warnings, sub-£10 calculator drift.
3. **Bug triage cadence:** weekly. Severity-1 blocks; severity-2 logs.
4. **Re-test discipline:** any bug fix in a passed-layer triggers re-validation of that layer. No exceptions. Re-tests are cheaper than missed regressions.
5. **No new features during the gauntlet** (per spec § 3). Hardening adjustments and bug fixes only.
6. **Dev env is sacred.** No production deploy from this branch until G-7 sign-off lands.
7. **Daily status surface:** end-of-day handover lists which workstreams advanced and which bugs landed. Same pattern as R-0 → R-14b sessions.
8. **Honesty over optimism.** A workstream is red if it has ANY open severity-1, regardless of how close to done it feels.

---

## 0A. Pre-gauntlet workstream G-(-1) — Lifecycle engine MVP (~4 hr, week 1)

**Scope:** the codebase audit revealed `config/lifecycle.php` doesn't exist and `LifecycleEngine` was never implemented. Build the minimum viable plumbing so G-0-iv can pass.

### Deliverables
- `config/lifecycle.php` — returns `['test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT')]` and an empty `'events' => []` array.
- `app/Services/Lifecycle/LifecycleEngine.php` — single public method `dispatch(User $user, string $event, array $context = [])` that routes through the override (logs the event + dispatched recipient; no actual mail send needed for the MVP).
- Console kernel registration of `lifecycle:run-daily` command (no-op stub for now — placeholder for the scheduled job).
- Unit test asserting `config('lifecycle.test_recipient_override')` returns `chris@fynla.org` when the env var is set.

### Out of scope (TECH DEBT — full lifecycle engine)

The full lifecycle engine spec at `docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md` (10 email types, scheduling matrix, audit logging) is **logged as tech debt** for post-cutover delivery. It needs its own spec → plan → PRD cycle and is out of scope for this gauntlet.

### Exit gate
- `php artisan tinker --execute="echo config('lifecycle.test_recipient_override') ?: 'UNSET'.PHP_EOL;"` returns `chris@fynla.org`.
- `php artisan schedule:list` shows the daily entry.
- The MVP unit test passes.

### Dependencies
- None.

### Singleton-binding fix (parallel housekeeping during G-(-1))

Also during G-(-1), apply the audit's recommended pack-binding singleton fix: change `pack.gb.user_relations`, `pack.gb.asset_repo`, `pack.gb.estate_repo`, `pack.gb.asset_resolver` in `GbPackServiceProvider::register()` from `$this->app->bind(...)` to `$this->app->singleton(...)`. Add a binding-identity test (`app()->make('pack.gb.user_relations') === app()->make('pack.gb.user_relations')`) for each of the four.

---

## 1. Workstream G-0 — Setup & baseline (~4 hr, week 1)

**Scope:** finish the bootstrap items deferred during 2026-05-12 dev provisioning.

| # | Step | Owner | Status as of 2026-05-12 |
|---|------|-------|-------------------------|
| G-0-i | SiteGround cron entry for `fynla_inter` (Site Tools → Devs → Cron Jobs) | CSJ | pending (task #17) |
| G-0-ii | XAI/Grok dev API key in server `.env`; `php artisan config:clear` | CSJ paste, me apply | pending (task #12) |
| G-0-iii | Revolut sandbox webhook registered at `https://csjones.co/fynla_inter/api/payment/webhook`; `wsk_…` into `.env` (per Q11: at G-0, not deferred to G-6) | CSJ register, me apply | pending |
| G-0-iv | Lifecycle test recipient override verified — depends on G-(-1) lifecycle engine MVP completing first | me | ✅ PASS (2026-05-12) — config returns `chris@fynla.org` on dev, `schedule:list` shows `lifecycle:run-daily` at `0 7 * * *`, 7/7 unit tests green |
| G-0-v | Triage backlog created (GitHub issues, Linear, or a tracked markdown doc in `May/`) | CSJ + me | ✅ PASS (2026-05-12) — `May/May12Updates/triage-backlog.md` created. Scaffolded with bugs / enhancements / open questions sections; B-1 and G-0-iv already closed within it as the session's first uses. |

**Exit gate:** all five items green. `php artisan tinker --execute="…"` returns the four guardrail values (env=staging, revolut=sandbox, ai_provider=xai, lifecycle_test=chris@fynla.org) and lifecycle scheduler shows the daily job.

**Dependencies:** G-(-1) lifecycle engine MVP complete.

---

## 2. Workstream G-1 — Unit & Logic (~1 week, week 1–2)

**Scope:** verify the ~2,669-test Pest baseline holds on dev DB; build observer-firing tests (replacing the coverage % gate per audit); build the logic-fixture golden files.

### G-1-a: Baseline re-confirmation (~0.5 day) — ✅ PASS (2026-05-12)

```bash
# On csjones.co/fynla_inter:
cd ~/www/csjones.co/fynla_inter-app && ./vendor/bin/pest
```

Expected baseline: ~2,669 `it()` blocks (2,428 in `tests/**/*.php` + 241 in `packs/**/*.php`). Conditional skips: `AdminBackupTest` (mysqldump availability), `PackIsolationTest` (pack classes not loaded). Anything else → triage and fix before proceeding.

**Actual result (2026-05-12):** `2,836 passed / 1 skipped / 59 todos / 0 failed (11,018 assertions)` — exactly matches local baseline. Baseline confirmed on dev. The 59 todos are the G-1-b observer-firing test scaffolds (placeholders).

**One fix landed during G-1-a:** dev's `APP_URL=https://csjones.co/fynla_inter` (with subpath) was breaking Pest's TestCase URL resolution — `postJson('/api/auth/register')` was being prefixed to `/fynla_inter/api/auth/register` and falling through to the 404 handler / catch-all routes, causing 749 spurious failures. Fix: added `<env name="APP_URL" value="http://localhost"/>` to `phpunit.xml`, which overrides the test-env URL regardless of dev's runtime APP_URL. Single source of truth for any future subpath-deployed environment. **Uncommitted** as of 2026-05-12 13:30 UTC.

### G-1-b: Observer-firing tests (~2 days) — replaces previous coverage gate — ✅ PASS (2026-05-12)

**Actual result (2026-05-12):** 59/59 tests passing across 13 files in `tests/Feature/Observers/` — every observer's create/update/delete behaviour is now asserted via Bus::fake / Mockery::spy / DB-record assertions as appropriate. All 13 observers from the relocated namespaces fire correctly. Two test fixes during implementation: `LifeEvent.event_type` must use a valid enum value (e.g. `'gift_received'`, not `'birthday'`), and `Property` uses split address columns (`address_line_1`, not `address`). One scope correction: `Goal` isn't registered with `RecommendationCacheObserver` in `EventServiceProvider`, so the observer's `'Goal'` routing arm is exercised via `LifeEvent` (same routing target).



Coverage percentage is a vanity metric for the campaign's risk profile (per audit concern 6). Instead, write explicit Feature tests for each of the 13 observers asserting they fire on the relocated namespaces:

**7 observers in `app/Observers/`:**
- `RiskRecalculationObserver` (note: NOT `RecalculateRiskProfileObserver` — that class doesn't exist)
- `NetWorthCacheObserver`
- `FamilyMemberRiskObserver`
- `RecommendationCacheObserver`
- `LifeEventRiskObserver`
- `LifeEventMonteCarloObserver` (note: NOT `MonteCarloTriggerObserver`)
- `UserRiskObserver`

**6 observers in `packs/country-gb/src/Observers/`:**
- `DCPensionRiskObserver`
- `InvestmentAccountRiskObserver`
- `PropertyRiskObserver`
- `SavingsAccountRiskObserver`
- `InvestmentAccountGoalObserver`
- `SavingsAccountGoalObserver`

For each: create the triggering model with the new namespace, assert the observer's `created/updated/deleted` method fired (via `Event::fake()` spy or log-assertion).

Also assert: the 6 asset models registered in `AppServiceProvider::boot()` for `JurisdictionDetectionObserver` are autoloaded correctly post-relocation (a silent autoload failure would skip observation with no exception).

### G-1-c: Logic fixture golden files (~3 days, sampled sign-off)

For each of the 6 preview personas — **`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`** (note: `widow` was removed from the seeder; `student` replaced her):
- Walk the seeded data through every calculator (IHT, CGT, Income Tax, Pension AA, MPAA, ISA).
- Document expected output values to the nearest pence.
- Store as Pest data providers: `tests/Unit/LogicFixtures/{persona}_calculator_test.php`.

**Sign-off (Q10 → sample-and-trust):** CSJ reviews **2 personas in full** (~4-6 hr); for the other 4, scan only the deltas between the calculator output and the persona's seeded state. Spot-anomalies trigger a full review for that persona.

### G-1-d: Persona surgery (~0.5 day)

- Update CLAUDE.md preview-persona table: replace `widow | Margaret Thompson | Estate planning` row with `student | <name> | Emergency fund, early-career`.
- Remove dead `'widow'` branches from `database/seeders/Fynla/Packs/Gb/PreviewUserSeeder.php` (lines 1433, 1759, 2525).

**Exit gate:** baseline green; all 13 observer-firing tests green; logic fixtures for 6 personas signed off (2 full + 4 sampled).

**Dependencies:** G-0 complete.

**Parallel work allowed:** G-4 can start (security audit doesn't need test infrastructure).

---

## 3. Workstream G-2 — Systems integration (~1.5 weeks, week 2–3)

**Scope:** every API controller has Feature-test coverage (including the 5 ZA controllers that import GB models cross-pack); observer chains verified (13 observers, not 12); pack service providers correctly bind every contract (19, not 12); migration replay is safe on dirty data; cache poisoning from pre-relocation entries can't break the post-deploy app.

### G-2-a: API controller coverage matrix (~2 days)

For each of the ~98 API controllers:
- `app/Http/Controllers/Api/` — 41 files
- `packs/country-gb/src/Http/Controllers/` — 57 files
- `packs/country-za/src/Http/Controllers/` — 5 files (ZA cross-pack — import GB models like `Fynla\Packs\Gb\Models\DCPension`; post-relocation resolution is the actual test)
- (Exclude `packs/country-xx-smoke/` template controller — not production)

For each: ≥ 1 happy-path Feature test using `Sanctum::actingAs($user)`; ≥ 1 unauthenticated-rejection test asserts 401; ≥ 1 unauthorized-resource test asserts 403 (where applicable).

Matrix tracked in `Plans/test-gauntlet-coverage-matrix.md` (created G-2-a-i). Each row: controller, has-happy, has-auth, has-authz, blocker if red.

### G-2-b: Observer chain verification

Already done in G-1-b — but during G-2, extend with full-request integration: not just "observer fires" but "the API request that triggers the observer returns the expected response AND the downstream cache invalidation / risk recalculation happens correctly". Spot-checks across the 13 observers; ~0.5 day.

### G-2-c: Pack service provider binding walk (~1.5 days)

**Pre-step:** create the `CoreContracts` helper at `core/app/Core/Contracts/CoreContracts.php` with a static `all(): array` method that filesystem-scans the contracts directory and returns the list of interface FQCNs.

**Test:**

```php
foreach (PackRegistry::codes() as $code) {
  foreach (CoreContracts::all() as $contract) {
    $shortKey = "pack.{$code}." . self::contractShortName($contract);
    if (app()->bound($shortKey)) {
      assert(app($shortKey) instanceof $contract);
    } else {
      // PaymentProcessor is intentionally unbound for GB pack today
      assert(in_array($shortKey, self::INTENTIONALLY_UNBOUND, true));
    }
  }
}
```

Binding keys are short strings (`pack.gb.tax`, `pack.gb.retirement` etc.), not FQCNs.

**Extend existing `GbPackServiceProviderTest`** to assert all 14 currently-bound `pack.gb.*` keys resolve to the correct concrete type. Today's test only covers 5.

**Singleton-identity assertion** (for the 4 bindings fixed in G-(-1)): `app()->make('pack.gb.user_relations') === app()->make('pack.gb.user_relations')`.

### G-2-d: Polymorphic morph resolution + dirty-DB migration replay (~1.5 days)

Two tests cover this — the second is the audit's highest-priority addition:

**Test 1 (clean):** Sanctum's `personal_access_tokens.tokenable_type`, `notifications.notifiable_type`, `audit_logs.model_type` all resolve to `Fynla\Core\Models\User` on a fresh DB. Create tokens with the new namespace string and assert auth works.

**Test 2 (dirty — replicates prod state):**
1. Manually insert a row directly: `DB::table('personal_access_tokens')->insert(['tokenable_type' => 'App\\Models\\User', 'tokenable_id' => $user->id, ...])`.
2. Run the backfill migration `2026_05_11_120000_backfill_user_morph_aliases_to_core.php` via `php artisan migrate --force`.
3. Assert the row's `tokenable_type` is now `Fynla\Core\Models\User`.
4. Run the migration AGAIN (idempotency check); assert zero rows updated, zero errors.
5. Authenticate using a token from the migrated row; assert `auth()->user()->id === $user->id`.

Repeat for `notifications.notifiable_type` and `audit_logs.model_type`.

### G-2-e: Cache poisoning test (~0.5 day)

The `RecommendationCacheObserver` and `NetWorthCacheObserver` cache data that may serialise model FQCN strings. Pre-relocation cache entries with `App\Models\X` strings could break post-deploy.

**Test:**
1. Manually write a fake cache entry with `App\Models\SavingsAccount` as a key or value.
2. Trigger the observer (e.g. update a savings account).
3. Assert either the `class_alias` resolves the stale string OR the observer gracefully rebuilds the cache entry.

A graceful failure mode is acceptable; a 500 is not.

### G-2-f: PackIsolationTest hardening (~0.5 day)

The `App\Models` sub-section of the allow-list is empty (R-14b closed). The full allow-list is 60+ entries (R-14a deferrals — services that need float-money signatures fixed). The exit gate is:

- "`App\Models` sub-section of `$allowed` is empty" — true today.
- "Total `$allowed` count ≤ baseline + 1" — counter ratchet. Snapshot the count as of gauntlet-start (60-ish) and assert it never grows by more than 1.

### G-2-g: Track R-14a residuals (~0.5 day)

Two GB pack bindings still resolve to `App\…` classes:
- `pack.gb.exchange_control` → `App\Services\ExchangeControl\UkExchangeControl`
- `pack.gb.tax_optimisation` → `App\Agents\TaxOptimisationAgent`

Document explicitly in a `Plans/test-gauntlet-r14a-residuals.md` doc: what they are, why they couldn't move in R-14a (float-money signatures), and the resolution plan (post-cutover R-17 batch). For the gauntlet, just track that they don't grow.

**Exit gate:** coverage matrix green for every controller (incl. ZA); all 13 observer chains tested; all 14 `pack.gb.*` bindings + singleton identity verified; dirty-DB migration replay test green; cache poisoning test green; `App\Models` allow-list section empty + count-ratchet test green; R-14a residuals documented.

**Dependencies:** G-1 complete; G-(-1) singleton fix applied.

**Parallel work allowed:** G-4 continues; non-UI G-5 items (rate-limit, log-redaction, env-validate, HTTP timeouts) may start.

---

## 4. Workstream G-3 — End-to-end browser (~1.5 weeks, week 3–5)

**Scope:** every persona walks every module in Playwright. Zero console errors. Numbered-rules compliance verified.

### G-3-a: Test infrastructure (~1 day)

- Pin Playwright version
- Build a fixture harness that logs in as a given persona, fetches the verification code from the DB, lands on the dashboard
- Add a helper that asserts "0 errors in console" after each navigation

### G-3-b: Per-persona × per-module journey scripts (~5 days)

6 personas (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`) × 7 modules = 42 journey scripts. Each:
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

### G-4-a: Dependency CVE scan (~0.5 day) — ✅ PASS (2026-05-12, session 5)

```bash
composer audit
npm audit --production
```

**Result:** composer clean; npm prod 2 moderate remaining, both semver-major fixes, both formally risk-accepted with planned upgrade workstreams. Findings + reasoning at `May/May12Updates/g-4-a-dependency-audit.md`.

- Patched: `phpoffice/phpspreadsheet` 5.6.0 → 5.7.0 (4 advisories cleared, in-constraint), `postcss` 8.5.6 → 8.5.14 (1 advisory cleared, in-constraint).
- Risk-accepted: `vite` 5.4.21 (build-time only, localhost dev server, semver-major fix tracked separately); `@capgo/capacitor-native-biometric` 6.0.4 (auth-critical — formal upgrade workstream needed; iOS regression risk).

### G-4-b: OWASP walk-through (~2 days)

Invoke the `security-and-hardening` skill against:
- All 89 API controllers
- All 83 form requests
- The auth flow (login, register, password reset, 2FA, biometric)
- The preview-write-interceptor middleware
- The Revolut webhook handler

Findings tracked; HIGH/CRITICAL fixed before exit; MEDIUM/LOW logged with risk acceptance.

### G-4-c: Horizontal-privilege morph escalation test (~0.5 day) — replaces previous class-injection variant

The real morph threat is horizontal privilege escalation, not class injection (per audit concern 2). Test:

1. Create users A and B.
2. Create a PAT for user A with `tokenable_type='Fynla\Core\Models\User'` and `tokenable_id = userB.id` (i.e. token belongs to A but points at B).
3. Authenticate via this token; assert `auth()->user()->id === userB.id` (the token IS functional for B).
4. Hit a resource-ownership-checked endpoint (e.g. `GET /api/savings/{accountId}` where `accountId` belongs to user A); assert 403, not 200.

This proves that even if a token's `tokenable` is steered at another user, the ownership middleware rejects access to the original owner's resources. The class_alias is one-directional and does not constitute a deny-list; ownership middleware does.

A secondary class-injection test (`tokenable_type='App\\Console\\Command'`) is fine to include but is not the load-bearing one.

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

### G-4-f: Externalised LLM-driven audit (~1 day) — required, not optional

Run a second-opinion LLM-driven audit (different model than Claude — Grok via xAI, Gemini, or both). Capture findings. **No paid pen-test** (per Q8 → (a)).

**Exit gate:** OWASP Top 10 checklist complete; zero high/critical CVEs unpatched; secret-management findings closed; written sign-off from CSJ.

**Dependencies:** G-1 complete (knowing the unit-test baseline matters before chasing security).

**Parallel work allowed:** G-1, G-2 continue; G-5 may start.

---

## 6. Workstream G-5 — Hardening checklist (~1 week, split across weeks 2–5)

**Scope:** convert dev's permissive defaults into prod-grade configuration without breaking G-3 E2E. Collapsed from 7 sub-gates to a single checklist (per audit concern 14 + Q12 → all-in).

**Sequencing** (Q6 → resequence):
- **Non-UI items run weeks 2–3 alongside G-2** — they don't risk breaking E2E console-error assertions: rate-limit, log redaction, external-HTTP timeouts, env:validate command.
- **UI-affecting items deferred until after G-3 is green** (weeks 5–6): CSP tightening, ErrorBoundary, Sentry browser-side wiring.

### Hardening checklist

| # | Item | Detail | Phase |
|---|------|--------|-------|
| H-1 | **Rate-limit middleware** | `throttle:5,1` on login, register, password reset, verification-code resend, biometric setup. Laravel `throttle:` middleware. | Non-UI (weeks 2–3) |
| H-2 | **Log redaction** | Custom Monolog processor in `app/Logging/RedactionProcessor.php` redacts `password`, `api_key`, `secret`, `token`, `Authorization` headers, `sk_…`/`pk_…`/`wsk_…`/`xai-…` patterns. Apply globally via `AppServiceProvider`. | Non-UI (weeks 2–3) |
| H-3 | **External-HTTP timeouts** | All 15+ Revolut call sites in `app/Services/Payment/RevolutService.php` + `RevolutSubscriptionService.php` get explicit `->timeout(10)`; same for `AwinTrackingService`, `PostcodeLookupController`, `PushNotificationService`. xAI client already has 120s; document. | Non-UI (weeks 2–3) |
| H-4 | **`php artisan env:validate`** | Net-new console command in `app/Console/Commands/ValidateEnv.php` asserting prod has `APP_ENV=production`, `APP_DEBUG=false`, `REVOLUT_SANDBOX=false`, no `LIFECYCLE_TEST_RECIPIENT`, real `APP_KEY`, all DB/mail/Revolut/AI creds present. Runs in G-7 prod-readiness rehearsal. | Non-UI (weeks 2–3) |
| H-5 | **CSP self-host fonts + reconcile** (per Q9) | Per Q9 → (a): self-host Google Fonts to `/fonts/`, allow GA via `script-src https://www.googletagmanager.com https://*.google-analytics.com`, allow Awin via `script-src https://*.dwin1.com https://www.awin1.com`, **drop Meta Pixel**. Remove the conflicting CSP `Header set` from `deploy/*/.htaccess` so the `SecurityHeaders` middleware is the single source. | UI (weeks 5–6) |
| H-6 | **ErrorBoundary on every Vue route** | Create `resources/js/components/Shared/ErrorBoundary.vue`. Wrap each top-level view in `resources/js/views/` and `packs/*/resources/js/views/`. Catch render errors, log to console + Sentry, show graceful fallback with reload button. ~73 view files to touch. | UI (weeks 5–6) |
| H-7 | **Sentry (or equivalent) observability** | Wire Laravel + Vue. Free tier. DSN in `.env`. Must tag `environment=staging` for dev vs `production` for prod so the streams are separable. | UI (weeks 5–6) |

**Exit gate (single):** every checklist item green. Dev still passes G-3's "zero console errors" run under tightened CSP. Sentry receives a test error from both Laravel and Vue with the correct `environment` tag.

**Dependencies:**
- Non-UI items (H-1 through H-4): G-(-1) complete; can run alongside G-2.
- UI items (H-5 through H-7): G-3 green.

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

Goal: zero severity-1 bugs found in week 2. Severity-2 bugs queued for v1.1 backlog. Severity-1 = numeric error ≥ £10, OR auth failure, OR data loss (per spec § 4 / Q3).

**Exit gate:** 2 weeks elapsed; week-2 finished with zero new severity-1 bugs; CSJ signs off that "I would let a friend use this".

**Dependencies:** G-3 complete (no UI bugs reaching humans), G-4 + G-5 high-priority items closed.

---

## 8. Workstream G-7 — Prod readiness review (~3 days, week 8) — formerly G-8

G-7 (closed beta) deleted entirely per CSJ Q7 → (c). Internal-only validation via G-6 is the human-feedback layer; no external recruitment, no GDPR exposure. This workstream is the original G-8, renumbered.

**Scope:** the final pause before cutover (which is itself out of scope for the gauntlet — cutover happens after CSJ signs off here, in a separate session post-prod-freeze-lift).

### G-7-a: Cutover-plan rehearsal (~1 day)

Walk through the deploy steps documented in `May/May12Updates/deploy-2026-05-12.md` against a current snapshot of prod. Note divergences. **Run the dirty-DB migration replay (per G-2-d) against a prod DB snapshot** as the rehearsal step — not just docs review.

### G-7-b: Rollback-plan documentation (~0.5 day)

Single doc covering:
- Reverting to the May 6 prod state
- Restoring the previous `public/build/`
- **The `class_alias` re-add step** (audit concern 3, highest blast radius gap in the original plan): if a code rollback to `main` happens AFTER the migration runs, the legacy `App\Models\User` PAT rows now hold `Fynla\Core\Models\User` — `main` branch doesn't have that class. The rollback procedure MUST include re-adding `class_alias(\Fynla\Core\Models\User::class, 'App\\Models\\User')` to `main`'s `AppServiceProvider::boot()` and pushing that **before** any production traffic resumes.
- DB-rollback decision tree (the morph backfill is one-way; the `class_alias` makes a code rollback work without DB rollback **only if the reverse alias is added to `main`**).
- Customer-comms script if downtime exceeds 10 min.

### G-7-c: Final security re-check (~0.5 day)

Run `composer audit` + `npm audit --production` + a final `security-and-hardening` skill pass against the diff since G-4 closed.

### G-7-d: Go/no-go writeup (~0.5 day)

A single markdown doc:
- All 7 layer exit gates: green / yellow / red with notes
- Outstanding severity-2 bugs (count + list)
- Outstanding severity-1 bugs (must be zero — if any, no-go)
- Internal user-testing summary
- Estimated time to recover from common failure modes
- 2 R-14a residuals tracked (per G-2-g) — risk-accepted or resolution-planned
- CSJ's written go/no-go decision

**Exit gate:** CSJ's written go (or no-go with re-baseline of timeline).

**Dependencies:** G-6 complete.

---

## 10. Sequencing and the calendar

Calendar week numbering starts 2026-05-12 (Monday). G-7 (beta) deleted; G-5 split into non-UI (parallel with G-2) and UI (after G-3) phases.

| Week | Calendar | G-(-1) | G-0 | G-1 | G-2 | G-3 | G-4 | G-5 nonUI | G-5 UI | G-6 | G-7 |
|------|----------|--------|-----|-----|-----|-----|-----|-----------|--------|-----|-----|
| 1 | May 12–18 | ▶ → ✓ | ▶ → ✓ | ▶ | | | ▶ | | | | |
| 2 | May 19–25 | | | ✓ | ▶ | | ↻ | ▶ | | | |
| 3 | May 26–Jun 1 | | | | ↻ | ▶ | ✓ | ↻ | | | |
| 4 | Jun 2–8 | | | | ✓ | ↻ | | ✓ | | | |
| 5 | Jun 9–15 | | | | | ↻ | | | ▶ | | |
| 6 | **Jun 16–22 — CHECKPOINT** | | | | | ✓ | | | ↻ | ▶ | |
| 7 | Jun 23–29 | | | | | | | | ✓ | ↻ | |
| 8 | Jun 30–Jul 6 | | | | | | | | | ✓ | ▶ → ✓ |

**Week-6 checkpoint gate (NEW, per audit concern 13):** at end of week 6 (Jun 22), if ANY of G-1, G-2, G-3, G-4 is not green, **trigger soft re-baseline**: compress G-6 from 2 weeks to 1 week, accept "soft July" cutover target rather than hard July 7. This is a binary decision with a defined trigger, not an open-ended slip.

Buffer: ~5 weeks (Jul 7 → Aug 4) before the hard 12-week stop — increased from 3 weeks due to G-7 deletion. Buffer is for severity-1 fixes mid-gauntlet that re-open closed layers. If buffer exhausted, re-baseline rather than slip silently.

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
| Severity-1 calc bug found late | Medium | High (re-tests preceding layers) | Logic fixture sign-off in G-1 (sampled) should catch most; budget for at most 2 re-tests in the calendar; week-6 checkpoint compresses G-6 if needed |
| Migration backfill misbehaves on dirty prod data | Medium | Critical | G-2-d dirty-DB replay test specifically targets this; G-7-a rehearsal runs the migration against a prod snapshot before cutover |
| Code rollback after migration breaks auth (no class_alias on `main`) | Low | Critical | G-7-b rollback runbook documents the explicit `class_alias` re-add step as a hard requirement |
| Singleton-binding fix forgotten / regressed | Low | High | G-(-1) test asserts singleton identity for all 4 bindings; failure surfaces in G-2-c binding walk |
| External Sentry-equivalent costs surprise | Low | Low | Use free tier; if exhausted, fall back to file-based structured logs |
| CSJ unavailability mid-gauntlet | Medium | Medium | Plan assumes part-time CSJ engagement; calendar has slack; week-6 checkpoint forces re-baseline decision |
| AI assistant context limit hit | Low | Low | Session-end / handover protocol already handles this |
| Single-developer burnout | Medium | High | Daily handovers force pacing; week-1 retros adjust if needed; G-7 (beta) deletion removes recruitment work |
| Architecture regression found in G-3 (campaign bug) | Low | Critical | Revert to specific R-N commit and re-run from there; campaign branch is well-tagged |
| CSP tightening breaks G-3 mid-stream | Medium | Medium | G-3/G-5 resequencing — UI G-5 items deferred until G-3 green |
| R-14a residual bindings drift further before cleanup | Low | Medium | G-2-g tracks them in a dedicated doc; PackIsolationTest counter ratchet prevents silent growth |
| Lifecycle engine MVP turns out to be larger than 4 hr | Low | Low | Stub is intentionally minimal; full implementation is tech-debt for post-cutover |
| Prod freeze pressure (revenue impact) | High | Medium | Spec § 5 acknowledges this; CSJ business decision |

---

## 13. Companion documents

- **Spec:** `Plans/test-gauntlet-spec-v1.md`
- **PRD:** generated via `/prd-writer` after spec + plan review.
- **Coverage matrix (to be created in G-2-a):** `Plans/test-gauntlet-coverage-matrix.md`
- **Bug triage backlog (to be created in G-0-vi):** location TBD per CSJ
- **Memory:** [[feedback-prod-deploy-freeze]] — the policy this plan executes against.
- **Architecture context:** [[Plans/architecture-spec-v3.md]] + [[Plans/architecture-plan-v3.md]] — what the campaign actually did.
