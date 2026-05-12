---
type: triage-backlog
created: 2026-05-12
gauntlet_gate: G-0-v
status: active
owners: CSJ + Claude (joint)
severity_ladder: Plans/test-gauntlet-plan-v1.md § 0 (Operating principles)
---

# Triage Backlog — Fynla International

This is the canonical triage backlog for the Test Gauntlet v1 (`Plans/test-gauntlet-plan-v1.md`). All bugs, enhancements, and open questions surfaced during G-1 through G-7 land here first. Severity-1 items block their workstream; severity-2 items log to v1.1.

Severity policy (per spec § 4 / Q3):
- **Severity-1** — blocks workstream + blocks cutover: numeric error ≥ £10, OR auth failure (login, register, password reset, 2FA, biometric, session resumption), OR data loss (record created → record disappears).
- **Severity-2** — logs to v1.1 backlog, doesn't block: UI / rendering / copy bugs, layout breaks, micro-copy errors, console warnings, sub-£10 calculator drift.

Triage cadence: weekly. Severity-1 fixed within 24h of triage.

---

## 1. Open bugs

### Sev-1 (workstream blockers)

*(none open)*

### Sev-2 (v1.1 backlog)

| # | Title | Workstream | Discovered | Owner | Status | Notes |
|---|-------|------------|------------|-------|--------|-------|
| B-2 | Cruft in dev app root — `.obsidian/`, `Articles/`, `Home.md`, `Marketplace.md`, `README.md`, `addepar.md`, `Fynla_International_Handover.docx`, `appMapping/` etc. mixed into `~/www/csjones.co/fynla_inter-app/` | G-0 | 2026-05-12 (session 4) | TBD | open | Earlier bootstrap rsync swept non-Laravel files into the app root. Doesn't break anything (Laravel ignores them), but pollutes `composer install` and future rsyncs. Cleanup task — not blocking. |
| B-4 | `@capgo/capacitor-native-biometric` 6.0.4 — Authentication Bypass advisory GHSA-vx5f-vmr6-32wf (CWE-287). Fix 8.4.5 = semver-major. | G-4-a | 2026-05-12 (session 5) | TBD | open | Auth-critical but local-attacker scope only — bypass needs device code-execution or a malicious app exploiting the older binding. Bumping requires API drift review, iOS device regression test (Face ID flow in `app.js` / `BiometricPrompt.vue` / `SettingsList.vue`), and Keychain token format verification. ~0.5 day. Prod frozen anyway per `feedback_prod_deploy_freeze.md`. Picks up after G-1-c. |

---

## 2. Open enhancements (deferred from gauntlet scope)

| # | Title | Source | Discovered | Owner | Notes |
|---|-------|--------|------------|-------|-------|
| E-1 | Full lifecycle email engine (10 event types, scheduling matrix, audit logging) | TECH DEBT | 2026-05-12 (G-(-1)) | TBD | Spec at `docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md`. Only MVP plumbing built in G-(-1). Post-cutover delivery; needs own spec → plan → PRD cycle. |
| E-2 | R-16 cleanup — remove `class_alias` in `CoreServiceProvider::boot()` | TECH DEBT | 2026-05-11 (R-14b-viii) | TBD | Deferred until post-cutover, once prod morph backfill is verified clean. Tracked in `Plans/test-gauntlet-plan-v1.md` § 11. |
| E-3 | R-14a residuals — `pack.gb.exchange_control` + `pack.gb.tax_optimisation` resolve to `App\…` classes | TECH DEBT | R-14a (pre-relocation) | TBD | Float-money signatures block relocation. Post-cutover R-17 batch. Tracked in `Plans/test-gauntlet-r14a-residuals.md` (G-2-g). |
| E-4 | CSP dual-definition reconciliation — `SecurityHeaders.php` allows GA/FB/Fonts; `.htaccess` blocks them | HARDENING | Audit | Claude | Resolution in gauntlet H-5 (UI hardening, weeks 5–6). |
| E-5 | Vite 5→8 multi-major upgrade — clears GHSA-4w7w-66w2-5vf9 path-traversal advisory | G-4-a | 2026-05-12 (session 5) | TBD | Build-time only, dev server runs on localhost — practical exposure is nil. Requires bumping `vite`, `@vitejs/plugin-vue`, `laravel-vite-plugin`, `vitest`; updating `vite.config.js` per CLAUDE.md mobile rules; full local + dev + mobile rebuild. ~0.5–1 day. Routine maintenance, not security blocker. |
| E-6 | `SecurityHeaders.php` — add `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, CSP `report-uri` | G-4-b LOW L-6 | 2026-05-12 (session 5) | TBD | Defensive hardening of production CSP. No user-controlled HTML rendering today so risk is low, but easy win for posture. |
| E-7 | `SanitizeInput.php:102` — `strip_tags` is destructive on legitimate `<` / `>` in text fields | G-4-b LOW L-7 | 2026-05-12 (session 5) | TBD | UX issue more than security. Replace with `htmlspecialchars` (preserves content, encodes entities) or drop entirely and rely on Vue auto-escape. |
| E-8 | `PreviewWriteInterceptor.php:126` — `str_starts_with($currentPath, $excludedRoute.'/')` allows future sub-path inheritance | G-4-b LOW L-2 | 2026-05-12 (session 5) | TBD | Add a comment in the docblock or convert excluded routes to a regex pattern set for tighter matching. |
| E-9 | `PreviewWriteInterceptor::resolveUserFromToken` — `PersonalAccessToken::findToken` doesn't check expiration | G-4-b LOW L-3 | 2026-05-12 (session 5) | TBD | Functionally safe (Sanctum guard checks expiry downstream) but conceptually redundant. Add `if ($accessToken->expires_at && $accessToken->expires_at->isPast()) return null;` for defensive symmetry. |
| E-10 | `AuthController::login:175` — `Auth::attempt` duplicates the user lookup from line 142 | G-4-b LOW L-1 | 2026-05-12 (session 5) | TBD | Performance nit, not security. Use `Hash::check` directly to avoid the second query. |
| E-11 | `SubscriptionRenewalService::handleRenewalPayment` is dead code | G-4-b slice 2 LOW L-1 | 2026-05-12 (session 6) | TBD | Never called from `WebhookController` (only Overdue/Cancelled/Finished are wired) and not invoked from any scheduled job. Also contains a dangerous fallback (`latest('current_period_end')` picks a random active subscription). Delete the method, or wire it to `ORDER_COMPLETED` for true renewal detection and fix the fallback. Prefer deletion until renewal handling is properly designed. |
| E-12 | Revolut webhook timestamp tolerance 5 min → 2 min | G-4-b slice 2 LOW L-2 | 2026-05-12 (session 6) | TBD | `RevolutService.php:225` uses `5 * 60 * 1000` ms. Tighter window (2 min) reduces replay attack surface. Trade-off: clock skew tolerance. Measure real-world Revolut delivery latency first, then decide. |
| E-13 | Webhook secret-missing log is silent / not actionable | G-4-b slice 2 LOW L-3 | 2026-05-12 (session 6) | TBD | `RevolutService.php:217` logs at `warning` when `$this->webhookSecret` is empty. Should be `critical` or thrown — silent misconfiguration would reject every webhook for hours unnoticed. |
| E-14 | Webhook response body always generic `'Webhook processed'` | G-4-b slice 2 LOW L-4 | 2026-05-12 (session 6) | TBD | `WebhookController.php:71` returns the same body for handled / unhandled / ignored events. Including the event type + handler outcome would aid log replay and debugging. |
| E-15 | Cap on webhook signature parse iterations | G-4-b slice 2 LOW L-5 | 2026-05-12 (session 6) | TBD | `RevolutService.php:244` splits the signature header on `,` with no max-segment cap. Not exploitable (each `hash_equals` is constant-time) but adds CPU pressure if an attacker can inject extra `v1=...` segments via a misconfigured proxy. Cap at 5 segments. |

---

## 3. Open questions (need a decision)

| # | Question | Context | Owner | Notes |
|---|----------|---------|-------|-------|
| Q-2 | When to register the SiteGround cron entry for `fynla_inter`? | G-0-i | CSJ | Site Tools → Devs → Cron Jobs. Independent of B-1. |
| Q-3 | Revolut sandbox webhook — when to register at `sandbox-merchant.revolut.com`? | G-0-iii | CSJ | URL: `https://csjones.co/fynla_inter/api/payment/webhook`. Paste `wsk_…` back to me for `.env` insertion. |
| Q-4 | Triage tool — markdown doc (this file), GitHub Issues, or Linear? | G-0-v | CSJ | Defaulting to this markdown doc for now per "tracked markdown doc in May/" option in plan § 1. Switchable later without losing items. |

---

## 4. Closed / resolved

| # | Title | Resolved | Notes |
|---|-------|----------|-------|
| G-4-b/H-1 | `PasswordResetService::initiateReset` leaked account existence via response shape | 2026-05-12 (session 5, commit `3a2070c`) | No-user branch now also returns a dummy `data.reset_token` (`Str::random(64)`). Subsequent endpoints uniformly fail with "Invalid or expired reset session" so the response shape carries no signal. |
| G-4-b/H-2 | `MFAController::disable` accepted password alone, no MFA challenge required | 2026-05-12 (session 5, commit `3a2070c`) | Now requires both `password` AND `code` (TOTP or recovery code). `MFAService::verifyCode` hardened to return false rather than throw on malformed secrets. Frontend disable-MFA modal updated to collect the code. |
| G-4-b/H-3 | `MFAController::regenerateRecoveryCodes` accepted password alone | 2026-05-12 (session 5, commit `3a2070c`) | Same shape as H-2, same fix. No current frontend caller — API-only change. |
| G-4-b/M-1 | Admin auto-promote ran BEFORE `Auth::attempt` on every login | 2026-05-12 (session 5, commit `e37acb7`) | Moved promotion AFTER successful auth. Added `AuditLog::ACTION_ADMIN_PROMOTED` constant and audit entry on every promotion. Failed login attempts no longer touch users.role_id / is_admin. |
| G-4-b/M-2 | Same admin auto-promote on registration verifyCode path, no audit | 2026-05-12 (session 5, commit `e37acb7`) | Audit log added (`ACTION_ADMIN_PROMOTED`, `source: registration_auto_promote`). |
| G-4-b/M-3 | `AuthController::verifyCode` registration path used `!==` for 6-digit code check | 2026-05-12 (session 5, commit `e37acb7`) | Swapped to `hash_equals`. |
| G-4-b/M-4 | `PasswordResetService::verifyEmailCode` used `!==` for email code | 2026-05-12 (session 5, commit `e37acb7`) | Same swap. |
| G-4-b/M-5 | `MFAController::useRecoveryCode` had no lockout counter on failure | 2026-05-12 (session 5, MEDIUM batch B commit) | Added `LoginAttempt::REASON_RECOVERY_CODE_FAILED` constant + `recordFailedAttempt` call on failure path. Recovery code brute-force is now bounded by the same lockout threshold as TOTP. |
| G-4-b/M-6 | `AuthController::changePassword` left stale tokens on other devices alive | 2026-05-12 (session 5, commit `e37acb7`) | Now revokes all OTHER tokens after successful password change, keeping the current session alive. Brings change-password in line with PasswordResetService::resetPassword's behaviour. |
| G-4-b/M-7 | AI-chat preview write-blocking was a single-point-of-failure (tool executor) with no test pinning the invariant | 2026-05-12 (session 5, MEDIUM batch B commit) | Added 4 unit tests at `tests/Unit/Services/AI/AiToolDefinitionsPreviewBlockingTest.php` asserting (1) preview-mode tool list excludes write tools across all 5 write groups, (2) non-preview tool list includes them, (3) xAI variant same, (4) preview tool list is strictly smaller than full list. |
| G-4-b/M-8 | `MFAController::verify` nuked all tokens including mobile/preview | 2026-05-12 (session 5, MEDIUM batch B commit) | Scoped to `where('name', 'auth_token')` so mobile (`mobile_auth_token`) and preview (`preview-access`) tokens survive web MFA verification. |
| G-4-b/M-9 | `PreviewController::createSpouseAccount` synthesised fake `@temp.fps.com` emails | 2026-05-12 (session 5, MEDIUM batch B commit) | Now throws `InvalidArgumentException` if persona spouse data lacks a valid email. No more orphaned User rows with unverifiable domains. |
| G-4-b/M-10 | Preview-user login path skipped verification AND MFA — risky if flag ever leaks onto real prod account | 2026-05-12 (session 5, MEDIUM batch B commit) | Gated behind `! app()->environment('production')`. Preview personas only exist in local/staging, so production still applies email-verification + MFA gates if `is_preview_user=true` ever appears on a real account. |
| B-1 | Dev `.env` `APP_KEY` placeholder — HTTP 500 on `csjones.co/fynla_inter` | 2026-05-12 (session 4) | Fixed by `php artisan key:generate --force` + `config:clear` + `optimize` on the dev server. New key in `.env`; backup at `.env.before-key-regen-YYYYMMDD-HHMMSS`. Site now HTTP 200. CSJ confirmed app had never carried real user data, so destructive impact was nil. Q-1 resolved by the same action. |
| G-0-iv | Lifecycle test recipient override verified | 2026-05-12 (session 4) | Three exit-gate checks all green: config returns `chris@fynla.org` (dev server tinker); `schedule:list` shows `0 7 * * *  php artisan lifecycle:run-daily`; `tests/Unit/Services/Lifecycle/LifecycleEngineTest.php` 7/7 passing. Plan tracker `Plans/test-gauntlet-plan-v1.md` § 1 updated. |
| G-0-v | Triage backlog created | 2026-05-12 (session 4) | This file. Marked PASS in plan tracker § 1. |
| G-1-a | Pest baseline re-confirmation on dev | 2026-05-12 (session 4) | Final result: 2,836 passed / 1 skipped / 59 todos / 0 failed — exact match with local baseline. Required prerequisite work: rsync of `tests/`, `packs/country-{gb,za,xx-smoke}/tests/`, `phpunit.xml`, `composer.json`, `composer.lock`; `composer install` + chmod; `chmod +x vendor/bin/pest`. Root cause of an initial 749 spurious failures: dev's `APP_URL=https://csjones.co/fynla_inter` (subpath) was being prepended to Pest's test URLs (e.g. `postJson('/api/auth/register')` → `/fynla_inter/api/auth/register` → 404 fallback). Fix: added `<env name="APP_URL" value="http://localhost"/>` to `phpunit.xml` — single test-env override that works for any subpath staging. Committed in `b2ac915`. |
| G-1-b | Observer-firing tests | 2026-05-12 (session 4) | 59/59 tests passing across 13 observer files in `tests/Feature/Observers/`. Every observer's create/update/delete behaviour verified via Bus::fake (RiskObservers), Mockery::spy (NetWorth/MonteCarlo/RecommendationCache), or DB record assertions (Goal observers). Plan tracker `Plans/test-gauntlet-plan-v1.md` § 2 marked PASS. Two test fixes during implementation (LifeEvent enum, Property address columns) and one scope correction (Goal not registered for RecommendationCacheObserver — exercise via LifeEvent instead). |
| B-3 | Pest 749 spurious failures on dev — `APP_URL` subpath prefix breaking test URL resolution | 2026-05-12 (session 4) | Resolved by `phpunit.xml` override of `APP_URL` to `http://localhost` for the test env. Affects any deployment where `APP_URL` includes a path component. Single line, single source of truth. |
| Q-1 | Which APP_KEY recovery path? | 2026-05-12 (session 4) | Resolved via path (b) — `key:generate --force`. CSJ noted there was no prior deploy and no user data to protect, making destructive concern moot. |
| — | Widow persona ghost — dead branches in `PreviewUserSeeder` + `AdvisorClientSeeder` | 2026-05-12 (G-1-d) | Commit `cedd279`. 5 dead branches removed; CLAUDE.md persona table updated to document `student`. |
| — | Lifecycle MVP plumbing | 2026-05-12 (G-(-1) FR-M1) | Commit `c389e53`. Config + service + command + Kernel scheduling + 7 Pest cases. Server CLI verified. |
| — | Pack-binding singleton fix (4 GB resolvers) | 2026-05-12 (G-(-1) FR-M2) | Commit `2061448`. `bind` → `singleton` for `pack.gb.{user_relations,asset_repo,estate_repo,asset_resolver}`. 4 identity tests. |
| — | Architecture plan v3 paperwork closed | 2026-05-12 | Commit `c2bb103`. Frontmatter `status: closed (dev-green; prod deferred per feedback_prod_deploy_freeze.md)`. |

---

## 5. Conventions for this file

- **Adding items** — append to the relevant section with a sequential ID (`B-N`, `E-N`, `Q-N`). Never re-use IDs.
- **Closing items** — move the row to § 4 with a one-line resolution note. Don't delete.
- **Severity changes** — edit in place with a brief `severity: 2 → 1 because …` note in the Notes column.
- **Workstream column** — use the gauntlet ID (G-0, G-1, …) or "TECH DEBT" / "HARDENING" / "AUDIT" for items not tied to a single gate.
- **Cross-references** — link to handover docs (`May/MayNUpdates/handover-…md`), plan sections, or commits where the item was discovered or resolved.

---

## 6. Companion documents

- **Plan:** `Plans/test-gauntlet-plan-v1.md`
- **Spec:** `Plans/test-gauntlet-spec-v1.md`
- **PRD:** `May/May12Updates/PRD-test-gauntlet-v1.md`
- **R-14a residuals:** `Plans/test-gauntlet-r14a-residuals.md` (to be created in G-2-g)
- **Coverage matrix:** `Plans/test-gauntlet-coverage-matrix.md` (to be created in G-2-a)
- **Standing freeze policy:** memory file `feedback_prod_deploy_freeze.md`
