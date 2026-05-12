# PRD — Test Gauntlet v1 (Pre-Production Validation for the UK Pack Architecture)

**Project:** Test Gauntlet v1
**Owner:** CSJ
**Status:** Draft
**Date:** 12 May 2026
**Spec:** `Plans/test-gauntlet-spec-v1.md` (amended 2026-05-12)
**Plan:** `Plans/test-gauntlet-plan-v1.md` (amended 2026-05-12)
**Codebase audit:** Completed 2026-05-12 — see Risks & Dependencies for residual concerns

---

## 1. Context & Why

### Problem

`refactor/uk-pack-relocation` is a 108-commit branch that relocates the entire UK domain (`app/Models/*`, `app/Services/*` country-specific code, `app/Http/Controllers/Api/*` for UK-only routes) into the country-pack architecture (`packs/country-gb/`, `core/app/Core/`). 1,931 files changed against `main`. The dev environment at `csjones.co/fynla_inter` is bootstrapped and green (2026-05-12 smoke), but the path from there to `fynla.org` is not a routine deploy — it's a campaign-scale change that touches 370 live Sanctum sessions, every observer chain, every pack service provider binding, and the polymorphic morph map for tokens / notifications / audit logs.

A standard "build → upload → smoke → 15-min log watch" deploy has nowhere near the resolution to catch the regressions this branch could introduce. Prior Fynla deploys touched 10–50 files; this is 50× larger. Without an explicit quality gauntlet, the deploy's failure mode looks like: cutover succeeds, then 370 active sessions return 401 because of a single misformatted morph string or a stale autoload entry, with no rollback path that doesn't also break auth.

CSJ has explicitly frozen `fynla.org` (per memory `feedback_prod_deploy_freeze.md`, 2026-05-12) for ~2 months pending this gauntlet.

### Business case

Fynla International is pre-revenue but `fynla.org` is the live UK-only Fynla with paying customers and 370 active sessions. A botched cutover would:
- Force a same-day rollback with active users mid-session.
- Expose a regression at the financial-correctness layer (a calculator returning the wrong tax for a real customer) directly to customer trust.
- Set back the multi-country roadmap by months, because every future country pack relies on the relocation being correct.

The cost of the gauntlet (~6 weeks dev time, internal-only validation) is materially lower than the cost of a botched deploy. The cost of slipping the launch by 6 weeks is acceptable given the freeze is already in place and customer-acquisition is paused.

### Strategic fit

Module coverage: all seven Fynla modules (Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination) are exercised by the gauntlet because the architecture relocation touched every module.

Roadmap position: closes out the R-0 → R-14b architecture campaign described in `Plans/architecture-spec-v3.md` and `Plans/architecture-plan-v3.md`. The campaign was always two halves — relocate (done) and validate (this gauntlet). With this PRD locked, the original v3 plan can be marked closed (task #6) on dev-green grounds; prod-green remains a separate downstream deliverable post-gauntlet.

Cross-references:
- Architecture context: `Plans/architecture-spec-v3.md` + `Plans/architecture-plan-v3.md`.
- Policy: memory file `feedback_prod_deploy_freeze.md`.
- Dev environment bootstrap record: `May/May12Updates/handover-2026-05-12-session-1.md` + this same folder.
- Original deploy note (now historical, kept for cutover reference): `May/May12Updates/deploy-2026-05-12.md`.

---

## 2. Target Persona

This is **infrastructure work** — no new feature surfaces to end users. The gauntlet's "customers" are:

**Primary:** CSJ (founder, single developer) — needs confidence that the cutover won't take down `fynla.org`. The PRD's success means CSJ can sign off in week 8 with a documented basis for the decision.

**Secondary:** the 370 active `fynla.org` users — indirectly benefit. They never see the gauntlet; they experience either a smooth cutover (gauntlet worked) or a botched one (gauntlet failed). The seeded preview personas (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`) are the gauntlet's stand-ins for these users during automated and human-driven testing. *Note:* the `widow` persona referenced in CLAUDE.md and original spec drafts does not exist in `PreviewUserSeeder::PERSONAS` — it was replaced by `student`. CLAUDE.md update is in scope as G-1-d.

---

## 3. Success Metrics (KPIs)

The gauntlet is a binary gate, not a graded one. The success metric is "cutover succeeds without a severity-1 incident in week 1 post-prod-deploy."

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Pest baseline on dev (`csjones.co/fynla_inter`) | ~2,669 passing | All passing + observer-firing tests added (target +20 new tests in G-1-b) | `./vendor/bin/pest` exit 0 |
| API controller Feature-test coverage | Unknown — matrix to be built in G-2-a | 100% of ~98 controllers have ≥ 1 happy-path test | `Plans/test-gauntlet-coverage-matrix.md` (created G-2-a-i) |
| Playwright journey scripts | 0 today | 42 journeys (6 personas × 7 modules) + 5 cross-module flows | `tests/playwright/` directory; CI-style summary at G-3 close |
| Severity-1 bugs found in G-6 week 2 | Unknown | 0 | Triage backlog (G-0-v) |
| Severity-1 bugs in week 1 post-prod-cutover | Unknown | 0 | `storage/logs/laravel.log` on prod + customer support channel |
| Backfill migration dirty-DB replay test | Doesn't exist | Green | Pest run in G-2-d |
| `class_alias` rollback runbook | Doesn't exist | Documented + reviewed | Plans/test-gauntlet-runbook-rollback.md (created G-7-b) |

**Severity-1 definition (per spec § 4 and Q3 → (b)):** any user-visible numeric error ≥ £10, OR any auth failure (login, register, password reset, 2FA, biometric, session resumption), OR any data loss (record created → record disappears). **Severity-2:** UI/copy/rendering bugs, layout issues, console warnings, sub-£10 calculator drift.

---

## 4. User Stories & Scenarios

### User stories

- **As CSJ**, I want to **walk every persona through every module in Playwright** so that **I know the relocation didn't introduce regressions before a real customer hits one**.
- **As CSJ**, I want **the backfill migration tested against a dirty DB (PAT rows with `App\Models\User`)** so that **the prod migration won't fail or partially succeed on the 370 live sessions**.
- **As CSJ**, I want **the rollback procedure documented with the `class_alias` re-add step** so that **if cutover fails, I have a concrete sequence to follow rather than improvising under pressure**.
- **As a `fynla.org` user (proxy via preview personas)**, I want **my tax calculations to be unchanged post-relocation** so that **the move from `App\Models\*` to `Fynla\Core\Models\*` is invisible to me**.
- **As a `fynla.org` user (proxy)**, I want **my Sanctum session to keep working** so that **I'm not forced to log in again when the cutover happens**.

### Key scenarios

**Scenario 1 — Severity-1 bug discovered mid-G-6 (the canonical re-test trigger):**
1. Internal user reports "IHT calculation shows £45,123 but I expected ~£44,800 based on my gifts."
2. CSJ files as severity-1 (> £10 numeric error).
3. G-6 paused; bug triaged to specific service (e.g. `GbInheritanceTaxCalculator`).
4. Fix applied; logic-fixture for the affected persona regenerated; CSJ signs off the new fixture.
5. G-1 affected tests re-run; G-2 if observer chain involved; G-3 if user-visible path changed.
6. G-6 resumes from week-N with one extra day on the clock.

**Scenario 2 — Cutover-day morph regression (the catastrophic failure mode the gauntlet exists to prevent):**
1. Cutover happens post-G-7 sign-off. `php artisan migrate --force` runs the backfill on prod.
2. One PAT row has a value that the migration's `WHERE` clause didn't match (e.g. `\App\Models\User` with leading backslash, or a null, or a typo'd variant).
3. User with that token returns 401 on next request.
4. **If gauntlet succeeded:** G-2-d's dirty-DB test surfaced this exact variant; the migration handles all known string variations; the user is migrated correctly.
5. **If gauntlet failed:** the affected user is silently locked out; CSJ doesn't notice until customer support raises it; rollback would canonicalise back but `main` needs `class_alias` to be re-added first.

**Scenario 3 — Hardening change breaks G-3 mid-stream (resequencing rationale):**
1. CSJ tightens the CSP in G-5-H5 (UI item). Removes `unsafe-inline` from `script-src`.
2. A G-3 Playwright journey on the Investments dashboard now logs a CSP-blocked inline script warning.
3. **If sequencing is correct (Q6 → (a)):** G-5-H5 only runs after G-3 is green; G-3 journeys are re-run as part of H5's verification, not mid-G-3. Cost: 1 extra G-3 pass.
4. **If sequencing is wrong (original draft):** G-3 was already 80% green; now it's 60% red; CSJ has to choose between rolling back the CSP change or pushing through fixes.

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** Build a minimum-viable lifecycle engine (`config/lifecycle.php` + `LifecycleEngine::dispatch()` + `LIFECYCLE_TEST_RECIPIENT` override + scheduler registration). _Touches: `config/lifecycle.php` (new), `app/Services/Lifecycle/LifecycleEngine.php` (new), `app/Console/Kernel.php`._
- **FR-M2:** Convert `pack.gb.user_relations`, `pack.gb.asset_repo`, `pack.gb.estate_repo`, `pack.gb.asset_resolver` from `bind()` to `singleton()` in `GbPackServiceProvider::register()`. Add binding-identity tests for each. _Touches: `packs/country-gb/src/Providers/GbPackServiceProvider.php`, `tests/Feature/PackBindingSingletonTest.php` (new)._
- **FR-M3:** Add explicit observer-firing tests for all 13 observers (7 in `app/Observers/` + 6 in `packs/country-gb/src/Observers/`), asserting they fire on the relocated namespaces post-campaign. _Touches: `tests/Feature/Observers/`._
- **FR-M4:** Build the API controller coverage matrix and ensure every one of ~98 controllers (including 5 ZA cross-pack) has ≥ 1 happy-path Feature test, an unauthenticated-rejection test (401), and an unauthorized-resource test (403). _Touches: `Plans/test-gauntlet-coverage-matrix.md` (new), `tests/Feature/Api/`, `packs/*/tests/Feature/Http/`._
- **FR-M5:** Create `CoreContracts::all()` helper (filesystem-scan of `core/app/Core/Contracts/`) and use it in a container-resolution walk test that verifies every contract is bound for every pack (with an explicit allow-list for intentionally unbound bindings like `pack.gb.payment`). _Touches: `core/app/Core/Contracts/CoreContracts.php` (new), `tests/Architecture/PackBindingWalkTest.php` (new)._
- **FR-M6:** Implement the dirty-DB migration replay test for `2026_05_11_120000_backfill_user_morph_aliases_to_core.php` covering `personal_access_tokens`, `notifications`, `audit_logs`. Idempotency assertion required (re-run produces zero updates). _Touches: `tests/Feature/Database/MorphBackfillTest.php` (new)._
- **FR-M7:** Implement the cache-poisoning test (pre-relocation cache entries with `App\Models\X` strings must not break post-deploy). _Touches: `tests/Feature/Cache/PreRelocationCachePoisoningTest.php` (new)._
- **FR-M8:** Implement the horizontal-privilege morph escalation test (a PAT with `tokenable_type='Fynla\Core\Models\User'` and `tokenable_id` pointing at another user must not bypass resource-ownership checks). _Touches: `tests/Feature/Security/HorizontalPrivilegeMorphTest.php` (new)._
- **FR-M9:** Write the 42 Playwright journey scripts (6 personas × 7 modules) + 5 cross-module flows + 7 automated CLAUDE.md numbered-rule checks. _Touches: `tests/playwright/personas/`, `tests/playwright/cross-module/`, `tests/playwright/rules/`._
- **FR-M10:** Run the OWASP Top 10 audit via the `security-and-hardening` skill against every newly-relocated controller + every user-input boundary + auth flow. Second-opinion pass via Grok or Gemini. _Output: `Plans/test-gauntlet-security-audit-v1.md` (new)._
- **FR-M11:** Reconcile the dual CSP definitions (`app/Http/Middleware/SecurityHeaders.php` vs `deploy/*/.htaccess`). Self-host Google Fonts (`/fonts/` directory in `public/`); allow GA + Awin via explicit `script-src`; drop Meta Pixel. Single source of truth = the middleware. _Touches: `app/Http/Middleware/SecurityHeaders.php`, `deploy/csjones-fynla/.htaccess`, `deploy/fynla-org/.htaccess`, `public/fonts/` (new)._
- **FR-M12:** Add `php artisan env:validate` console command asserting prod `.env` has required values (`APP_ENV=production`, `APP_DEBUG=false`, `REVOLUT_SANDBOX=false`, no `LIFECYCLE_TEST_RECIPIENT`, real `APP_KEY`, all DB/mail/Revolut/AI creds present). _Touches: `app/Console/Commands/ValidateEnv.php` (new)._
- **FR-M13:** Add explicit timeouts (`->timeout(10)`) to all 15+ Revolut HTTP call sites, all Awin tracking calls, `PostcodeLookupController`, `PushNotificationService`. _Touches: `app/Services/Payment/RevolutService.php`, `app/Services/Payment/RevolutSubscriptionService.php`, `app/Services/Marketing/AwinTrackingService.php`, `app/Services/Mobile/PushNotificationService.php`, `app/Http/Controllers/Api/PostcodeLookupController.php`._
- **FR-M14:** Add log-redaction Monolog processor in `app/Logging/RedactionProcessor.php` covering `password`, `api_key`, `secret`, `token`, `Authorization`, `sk_…`, `pk_…`, `wsk_…`, `xai-…` patterns. _Touches: `app/Logging/RedactionProcessor.php` (new), `app/Providers/AppServiceProvider.php`._
- **FR-M15:** Add rate-limit middleware (`throttle:5,1`) to login, register, password reset, verification-code resend, biometric setup. _Touches: `routes/web.php`, `routes/api.php`, `packs/country-gb/routes/api.php`._
- **FR-M16:** Document the rollback procedure with the explicit `class_alias` re-add step (`class_alias(\Fynla\Core\Models\User::class, 'App\\Models\\User')` in `main`'s `AppServiceProvider::boot()`). _Output: `Plans/test-gauntlet-runbook-rollback.md` (new)._
- **FR-M17:** Internal user-testing pass — CSJ + 1–2 trusted users (friends/family) use the dev env daily for 2 weeks, file bugs to a triage backlog, severity-1 fixes block the workstream, severity-2 logs to v1.1. _Output: `Plans/test-gauntlet-user-testing-log.md` (new)._
- **FR-M18:** Prod-readiness writeup with a written go/no-go from CSJ, the cutover-plan rehearsal results, the rollback runbook reviewed, the dirty-DB migration replay run against a prod DB snapshot. _Output: `Plans/test-gauntlet-go-no-go-v1.md` (new)._

### Should-have

- **FR-S1:** Add `PackIsolationTest` counter ratchet — assert `count($allowed) <= baseline + 1` so the allow-list can shrink but not silently grow. _Touches: `tests/Architecture/PackIsolationTest.php`._
- **FR-S2:** Document the 2 R-14a residual bindings (`pack.gb.exchange_control`, `pack.gb.tax_optimisation`) explicitly with a resolution plan for post-cutover R-17. _Output: `Plans/test-gauntlet-r14a-residuals.md` (new)._
- **FR-S3:** Wire Sentry (or equivalent) for both Laravel and Vue. Free tier. Tag `environment=staging`. _Touches: `config/sentry.php` (new), `resources/js/sentry.js` (new), `.env` template._
- **FR-S4:** Update CLAUDE.md preview-persona table — replace `widow` with `student`. Remove dead `'widow'` code branches in `PreviewUserSeeder.php`. _Touches: `CLAUDE.md`, `packs/country-gb/database/seeders/PreviewUserSeeder.php`._
- **FR-S5:** Week-6 checkpoint gate — at end of week 6, if G-1/G-2/G-3/G-4 not all green, compress G-6 from 2 weeks to 1 and declare "soft July" cutover. _Process — no code change; document in `Plans/test-gauntlet-plan-v1.md` § 10._

### Nice-to-have

- **FR-N1:** ErrorBoundary component on every Vue route (~73 view files). Catch render errors, log to console + Sentry, show graceful fallback with reload. _Touches: `resources/js/components/Shared/ErrorBoundary.vue` (new), all top-level views._
- **FR-N2:** Coverage-percentage reporting kept as a leading indicator (not an exit gate). _Touches: `phpunit.xml`, `Plans/test-gauntlet-coverage-report.md` (new, optional)._

---

## 6. User Flow & UX/Design

There is no user-facing flow — this is infrastructure / quality work. The "flow" is the **gauntlet sequence**:

```
G-(-1) Lifecycle engine MVP + Singleton fix       (week 1, ~1 day)
       │
G-0    Setup & baseline                           (week 1, ~2 days)
       │
G-1    Unit + Logic                               (week 1-2, ~1 week)  ──┐
                                                                          │ parallel
G-4    Security audit (LLM-driven)                (week 1-3, ~1 week)  ──┘
       │
G-2    Systems integration                        (week 2-3, ~1.5 weeks) ──┐
                                                                            │ parallel
G-5 non-UI  Rate-limit / log-redact / timeouts /   (week 2-3, ~3 days)   ──┘
            env:validate
       │
G-3    E2E Playwright                              (week 3-5, ~1.5 weeks) ──┐
                                                                             │ at week 5 then
G-5 UI  CSP / ErrorBoundary / Sentry browser       (week 5-6, ~1 week)    ──┘
       │
       ▶ WEEK-6 CHECKPOINT (re-baseline trigger if anything red)
       │
G-6    User testing (CSJ + trusted)               (week 6-7, 2 weeks)
       │
G-7    Prod readiness review                       (week 8, ~3 days)
       │
       ▶ CUTOVER (separate session, post-G-7 sign-off, post-prod-freeze-lift)
```

### UX/Design notes

- **Design system:** no UI changes from the gauntlet except FR-N1 (ErrorBoundary fallback UI), which uses `fynlaDesignGuide.md` v1.3.0 colour tokens — `eggshell-100` background, `horizon-500` text, `raspberry-500` primary CTA ("Reload"). No new design patterns.
- **Jurisdiction visibility:** the gauntlet does not introduce or remove any jurisdiction-aware behaviour. Existing UK-only routes (most of the app) and ZA-aware routes (5 ZA controllers) are both tested.
- **Reusable components:** none introduced. ErrorBoundary (FR-N1) is the only new Vue component and it's a wrapper, not a designed surface.
- **New components:** see FR-N1 (`ErrorBoundary.vue`). No others.
- **Responsive behaviour:** the existing app is responsive; the gauntlet just validates it. ErrorBoundary fallback must work on mobile (Capacitor iOS in particular — uses Capacitor's `App.exitApp` for the "Restart app" affordance there).
- **Accessibility:** ErrorBoundary fallback must have a focusable "Reload" button with appropriate ARIA labelling — `role="alert"`, `aria-live="assertive"`.
- **Reference artefacts:** none in the FynlaInter vault yet; PRD + spec + plan are the artefacts.

---

## 7. Out of Scope

- **Production cutover.** This PRD defines the gauntlet, not the cutover. Cutover happens post-G-7 sign-off in a separate session after CSJ lifts the prod freeze. The cutover steps live in `May/May12Updates/deploy-2026-05-12.md` (historical) and will be re-validated as part of G-7-a.
- **R-16 cleanup.** Removing the `class_alias` in `core/app/Core/Providers/CoreServiceProvider.php` is deferred to post-cutover (per Q10). Once prod's backfill migration is verified clean for some agreed observation window, R-16 is a trivial follow-up commit.
- **Full lifecycle email engine.** Per Q4 → (a), only the MVP plumbing is in scope. The full ~10-email spec at `docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md` is logged as tech debt; its own spec → plan → PRD cycle is required post-cutover.
- **Closed beta with external users.** G-7 (beta) deleted per Q7 → (c). No external recruitment, no NDA, no GDPR exposure, no `beta:purge` command needed.
- **Paid external pen-test.** Per Q8 → (a), LLM-driven audit only.
- **Performance optimisation** unless a regression surfaces during the gauntlet. The relocation should be performance-neutral; the singleton-binding fix in G-(-1) is a defensive correctness fix, not a perf optimisation per se.
- **New feature work.** Mutual-exclusion principle (spec § 3): no new features during the gauntlet. Hardening adjustments and severity-1 bug fixes only.
- **Visual redesign.** Design system stays at `fynlaDesignGuide.md` v1.3.0 for the duration.
- **Multi-country expansion.** SA pack stays at current shape (5 controllers, partial implementation). No new countries during the gauntlet. ZA testing is limited to "do the 5 ZA controllers still work post-relocation given they import GB models cross-pack" (FR-M4).
- **Mobile (Capacitor iOS) gauntlet coverage.** The mobile build chain is referenced in CLAUDE.md but the gauntlet exercises the web app at `csjones.co/fynla_inter`. Mobile re-test happens after the web app cuts over.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Severity-1 calc bug found in G-6 | Medium | High (re-tests preceding layers, consumes buffer) | Logic fixture sign-off in G-1 (sampled per Q10) catches most; week-6 checkpoint compresses G-6 if needed; ≥ £10 threshold (Q3) avoids re-test storms from sub-pence rounding |
| Migration backfill misbehaves on dirty prod data | Medium | **Critical** | FR-M6 dirty-DB replay test; G-7-a rehearsal runs migration against prod DB snapshot |
| Code rollback after migration breaks 370 PATs (no `class_alias` on `main`) | Low | **Critical** | FR-M16 rollback runbook documents the `class_alias` re-add step as a hard requirement, not a footnote |
| Singleton-binding fix forgotten / regressed | Low | High | FR-M2 test asserts singleton identity for all 4 bindings; failure surfaces in G-2-c binding walk |
| CSP tightening breaks G-3 mid-stream | Medium | Medium | Q6 → (a) resequencing: UI G-5 items run AFTER G-3 green, not in parallel |
| External Sentry-equivalent costs surprise | Low | Low | FR-S3 uses free tier; fallback is file-based structured logs |
| CSJ unavailability mid-gauntlet | Medium | Medium | Plan assumes part-time CSJ engagement; calendar has slack; week-6 checkpoint forces re-baseline decision |
| AI assistant context limit hit | Low | Low | Session-end / handover protocol already handles this |
| Single-developer burnout | Medium | High | Daily handovers force pacing; week-1 retros adjust if needed; G-7 (beta) deletion removed 2 weeks of recruitment work |
| Architecture regression found in G-3 (campaign bug) | Low | **Critical** | Revert to specific R-N commit and re-run from there; campaign branch is well-tagged |
| R-14a residual bindings drift further before cleanup | Low | Medium | FR-S1 + FR-S2 — counter ratchet + documented resolution plan |
| Lifecycle engine MVP turns out to be larger than 4 hr | Low | Low | Stub is intentionally minimal; full implementation = tech debt |
| Prod freeze pressure (revenue impact) | High | Medium | Spec § 5 + memory `feedback_prod_deploy_freeze.md` — CSJ business decision; mitigation is concrete week-6 checkpoint |

### Technical dependencies

- **Container resolution** — `Fynla\Core\Registry\PackRegistry::codes()`, `app()->bound("pack.{$code}.{$contract}")` pattern. Verified in audit.
- **Sanctum + polymorphic morph map** — `personal_access_tokens.tokenable_type`, `notifications.notifiable_type`, `audit_logs.model_type`. Verified in audit (migration `2026_05_11_120000_backfill_user_morph_aliases_to_core.php`).
- **The 19 contracts in `core/app/Core/Contracts/`** — not the 12 the spec originally claimed. `FR-M5` creates `CoreContracts::all()` to enumerate them.
- **`class_alias` in `core/app/Core/Providers/CoreServiceProvider::boot()`** — load-bearing for legacy `App\Models\User` references until prod migration runs.
- **Pest 2.x** for test infrastructure; **Playwright (via MCP)** for E2E.
- **Composer path repositories** for the pack architecture — `fynla/pack-country-{gb,za,xx-smoke}` now in `require` (fixed 2026-05-12 in commit `ef94699`).
- **External services:** Revolut sandbox (keys from `revolut/implementation-plan.md`), xAI/Grok dev key (pending — task #12), SiteGround SMTP (`noreply@fynla.org`), MaxMind/Cloudflare GeoIP (only if jurisdiction detection is exercised — likely not in gauntlet scope).

### Sequencing dependencies

- **Must complete first:** dev bootstrap at `csjones.co/fynla_inter` — done 2026-05-12. SiteGround cron entry (task #17) — pending. XAI/Grok dev key (task #12) — pending.
- **This gauntlet blocks:** prod cutover, R-16 (`class_alias` removal), full lifecycle engine implementation, any feature work on `refactor/uk-pack-relocation`.
- **Buffer:** ~5 weeks (Jul 7 → Aug 4) before the 12-week hard stop. Week-6 checkpoint gate is the explicit re-baseline trigger.

### Residual concerns from codebase audit

The validation audit (2026-05-12) surfaced 14 distinct concerns. All addressed in the amended spec + plan or explicit FRs above. Specifically:
- **Audit concerns 1, 4 (binding tests, singleton fix)** — FR-M2, FR-M5.
- **Audit concern 2 (horizontal-privilege test)** — FR-M8.
- **Audit concerns 3, 5 (migration replay + rollback runbook)** — FR-M6, FR-M16.
- **Audit concern 6 (observer-firing replaces coverage gate)** — FR-M3.
- **Audit concern 7 (cache poisoning test)** — FR-M7.
- **Audit concern 8 (G-3/G-5 sequencing)** — plan § 10 amended; Q6 → (a).
- **Audit concern 9 (severity-1 default)** — Q3 → (b), §3 + plan § 0 amended.
- **Audit concern 10 (G-7 GDPR)** — Q7 → (c), G-7 deleted entirely.
- **Audit concern 11 (PackIsolationTest ratchet)** — FR-S1.
- **Audit concern 12 (ZA controllers)** — FR-M4 includes them.
- **Audit concern 13 (week-6 checkpoint)** — FR-S5, plan § 10.
- **Audit concern 14 (G-5 collapse to checklist)** — plan § 6 amended.

**One residual concern carried forward:** the morph-attack-surface protection in Sanctum is runtime type-checking, not a registered deny-list — the `class_alias` is one-directional. FR-M8 tests the realistic horizontal-privilege threat; a paranoid `Relation::morphMap()` would add belt-and-braces but is out of scope (CSJ judgement — additive hardening for post-cutover if R-16 follow-up surfaces it).

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 12 May 2026 | Initial draft, generated via `/prd-writer` after codebase-audit-driven 12-question interview with CSJ. Spec + plan amended same day with the resolved decisions. | prd-writer skill |
