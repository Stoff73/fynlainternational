---
type: audit
gauntlet_gate: G-4-b
slice: 1
date: 2026-05-12
session: 5
status: reporting (no code changes yet)
branch: refactor/uk-pack-relocation
scope: AuthController, MFAController, PasswordResetController, PreviewController, PreviewWriteInterceptor, EnsureMFAVerified, SanitizeInput, SecurityHeaders, supporting models (EmailVerificationCode, PendingRegistration), PasswordResetService
---

# G-4-b — Slice 1: Auth Flow + PreviewWriteInterceptor

## Executive summary

The Fynla International auth surface is mostly well-designed: rate limits are present on every sensitive endpoint, password complexity is enforced everywhere passwords are set, verification codes have proper expiry / attempt-count gates, Sanctum tokens are hashed at rest, mass assignment of `is_admin` is explicitly guarded, and the password reset flow correctly revokes all tokens on success. However, three **HIGH**-severity issues warrant fixes before G-4-b exits: (1) password reset's `initiateReset` defeats its own anti-enumeration design via a response-shape leak, (2) MFA disable and (3) recovery-code regeneration require only password verification rather than active MFA proof, breaking the threat model for stolen-password attacks. Ten **MEDIUM**-severity items concern defense-in-depth weaknesses (admin auto-promote, non-constant-time code comparisons, missing token rotation on password change, single-point-of-failure preview write blocking via AI-chat tool executor, fake-email spouse account creation). The CSP `unsafe-inline` posture in production is acknowledged tech debt awaiting Revolut SDK nonce support.

## HIGH severity (fix before gate exits)

| ID | File:Line | Issue | OWASP | Recommended fix |
|---|---|---|---|---|
| H-1 | `app/Services/Auth/PasswordResetService.php:30-56` | `initiateReset()` claims to always return success to prevent account enumeration, but the response **shape differs**: when a user exists, response includes `data.reset_token`; when no user, no `data` key. A passive attacker can confirm account existence with one request. Defeats the entire defensive comment at line 30. | A07 Identification & Auth | Always return a placeholder reset_token in the no-user branch (e.g., a single-use dead-end token that the subsequent steps treat as expired), OR drop the reset_token from the response entirely and require the user to fetch it from email. Recommended: drop from response — the email-coded flow is the auth path; the response token is redundant for legitimate users since they have the email. |
| H-2 | `app/Http/Controllers/Api/MFAController.php:287-310` (`disable`) | MFA can be disabled with only the account password — no active TOTP code or recovery code required. An attacker with a stolen password (phished, breached, keylogged) can disable MFA and remove the second factor entirely before the legitimate user is alerted. The protection MFA is supposed to add against credential theft is voided. | A07 | Require BOTH `password` AND a current `code` (TOTP or recovery code). Verify both before calling `MFAService::disableMFA`. Add audit log with `ACTION_MFA_DISABLED` already present — that part is correct. |
| H-3 | `app/Http/Controllers/Api/MFAController.php:316-347` (`regenerateRecoveryCodes`) | Same shape as H-2 — only password gate. Recovery code regen invalidates all existing codes and issues fresh ones; an attacker with a stolen password can rotate the codes, locking the legitimate user out of recovery and giving themselves a fresh set to keep. | A07 | Require BOTH `password` AND a current `code` (TOTP or existing recovery code). Verify both before calling `MFAService::regenerateRecoveryCodes`. |

## MEDIUM severity (log to triage backlog, fix as bandwidth allows)

| ID | File:Line | Issue | OWASP | Recommended fix |
|---|---|---|---|---|
| M-1 | `app/Http/Controllers/Api/AuthController.php:145-152` | Admin auto-promote runs BEFORE password verification on every login. If `ADMIN_EMAILS` env var is misconfigured (deploy mistake, staging-vs-prod env swap, infrastructure compromise), users matching the list get `is_admin=true` written to the database on next login attempt — even on failed attempts since the lookup runs before `Auth::attempt`. The promotion is silent (no audit log). | A01 Broken Access Control | Move admin auto-promote AFTER successful `Auth::attempt` so failed login attempts don't trigger writes. Better: remove the auto-promote entirely and require an explicit `php artisan user:promote` command. Best: gate auto-promote behind `app()->environment('production')` AND log to audit trail. |
| M-2 | `app/Http/Controllers/Api/AuthController.php:478-497` (`verifyCode` registration path) | Same admin auto-promote pattern fires on first registration if the email is in `ADMIN_EMAILS`. No audit log for the promotion event. | A01 | Same as M-1. Add explicit audit log of admin role assignment with `ACTION_ADMIN_PROMOTED` (new constant). |
| M-3 | `app/Http/Controllers/Api/AuthController.php:469` | `$pending->verification_code !== $request->code` is non-constant-time string comparison on a 6-digit code. Practical brute-force risk is low (10^6 space, 5-attempt limit, 24h expiry, throttle:5/min on `/verify-code`) but the pattern is wrong — auth comparisons must use `hash_equals`. | A02 Cryptographic Failures | Replace with `hash_equals((string) $pending->verification_code, (string) $request->code)`. Same fix at M-4. |
| M-4 | `app/Services/Auth/PasswordResetService.php:79` | `$session->email_code !== $code` — same non-constant-time comparison as M-3. | A02 | `hash_equals((string) $session->email_code, (string) $code)`. |
| M-5 | `app/Http/Controllers/Api/MFAController.php:254` (`useRecoveryCode`) | No lockout counter on failed recovery code attempts. Recovery codes are long random strings so practical brute-force is infeasible, but the missing counter breaks the auth-surface design pattern that the rest of the codebase follows (`LoginLockoutService::recordFailedAttempt` is called from login + MFA verify). | A07 | Add `$this->lockoutService->recordFailedAttempt($user->email, LoginAttempt::REASON_RECOVERY_CODE_FAILED)` on the failure path. May need to add the constant to `LoginAttempt`. |
| M-6 | `app/Http/Controllers/Api/AuthController.php:384-423` (`changePassword`) | Successful password change does NOT revoke other tokens. A user changing their password (because they suspect compromise) leaves all other sessions valid. Compare to `PasswordResetService::resetPassword:224` which DOES revoke all tokens — the two flows should be symmetric. | A07 | After `$user->save()` at line 414, add `$user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete()` to revoke OTHER tokens while keeping the current session alive. |
| M-7 | `app/Http/Middleware/PreviewWriteInterceptor.php:66` | `api/ai-chat/conversations` excluded from preview write blocking with comment "tool executor handles write blocking". This is single-point-of-failure defense-in-depth — if the tool executor's blocking logic has a bug, preview users gain unfettered write access through AI chat. The interceptor is meant to be the outer ring. | A04 Insecure Design | Two options: (a) keep the AI chat exclusion but add an explicit unit test asserting the tool executor enforces is_preview_user blocking on every write tool (preferred), (b) remove the exclusion and route AI-driven writes through a separate authorized-real-user-only endpoint. Tracked in coverage matrix G-2-a. |
| M-8 | `app/Http/Controllers/Api/MFAController.php:204` (`verify`) | `$user->tokens()->delete()` on MFA-verify wipes ALL of the user's Sanctum tokens — including legitimate mobile/iOS sessions established earlier. UX-as-security issue: if an attacker has a stolen single-use mfa_token + valid TOTP, they can complete MFA and DoS the legitimate user's mobile session. Stolen mfa_token cache key is single-use so the practical attack window is narrow, but the blast radius is wider than intended. | A04 | Scope token deletion by token name or device fingerprint. e.g., `$user->tokens()->where('name', 'auth_token')->delete()` so mobile (`mobile_auth_token`) and preview (`preview-access`) tokens survive. Verify alignment with mobile login flow in `app.js` / `BiometricPrompt.vue`. |
| M-9 | `app/Http/Controllers/Api/PreviewController.php:633-674` (`createSpouseAccount`) | Synthesises fake emails (`Str::slug($spouseData['name']).'@temp.fps.com'`) when spouse data lacks email. Creates real User rows with weak `Str::random(16)` passwords, no role assignment (NULL role_id), and an unverifiable domain. If `@temp.fps.com` ever becomes routable, the synthesised accounts can be claimed by anyone receiving mail at that domain. Orphaned accounts also accumulate in the users table. | A05 Security Misconfiguration | Require an explicit email field for spouse account creation; reject if missing or if domain looks reserved/non-routable. Add `is_preview_spouse_account` flag and a periodic purge job. Alternatively, model spouse without creating a User row (use a profile table). |
| M-10 | `app/Http/Controllers/Api/AuthController.php:197-209` | Preview-user login path skips email verification AND skips MFA. Preview seeders use weak passwords (`password`). If the `is_preview_user=true` flag ever gets set on a real account via a migration / seeder / admin tool bug, that account becomes one-credential-deep and bypasses every auth control. | A07 | Add a runtime assertion in `User::scopePreviewUsers` / model boot hook that `is_preview_user=true` implies a synthetic password (e.g., `password_hash` matches a known bcrypt fingerprint, or the user's `email` ends in `@fynla.example`). Best: gate the preview-skip path behind `app()->environment(['local', 'staging'])` so production never honours it. |

## LOW severity (note; not blocking)

| ID | File:Line | Issue | Recommended fix |
|---|---|---|---|
| L-1 | `app/Http/Controllers/Api/AuthController.php:175` | `Auth::attempt` re-runs the user lookup that line 142 already did. Performance nit, not security. | Use `Auth::login($user, $request->password)` flow with explicit password check via `Hash::check` to avoid duplicate query. Or just leave it. |
| L-2 | `app/Http/Middleware/PreviewWriteInterceptor.php:126` | `str_starts_with($currentPath, $excludedRoute.'/')` allows sub-path inheritance. If a future route adds e.g. `api/auth/login/extended`, it would inherit the exclusion. Add a comment, or convert excluded routes to a regex pattern set for tighter matching. | Document the behaviour in the class docblock, or switch to exact-match-only + explicit `EXCLUDED_PREFIXES` array for the intentional prefix-inheritance routes. |
| L-3 | `app/Http/Middleware/PreviewWriteInterceptor.php:158` | `PersonalAccessToken::findToken` doesn't check token expiration. Functionally safe (Sanctum guard checks expiry downstream and rejects the request) but conceptually redundant — the interceptor sees a "valid" preview token even when Sanctum will reject it. | Add `if ($accessToken->expires_at && $accessToken->expires_at->isPast()) return null;` after line 162 for defensive symmetry. |
| L-4 | `core/app/Core/Models/PendingRegistration.php:64` | `createOrUpdate` upserts on email — attacker who knows a victim's email can repeatedly re-register to invalidate the victim's verification code. Bounded by `throttle:5,1` on `/api/auth/register` (5/min per IP). | Acceptable risk given throttle. Document. |
| L-5 | `app/Http/Middleware/SecurityHeaders.php:60` | Production CSP includes `script-src 'self' 'unsafe-inline'` because of Revolut checkout SDK and Plausible analytics. Acknowledged in TODO comment. Inline-script XSS would be exploitable if anywhere in the app does dynamic HTML rendering with user-controlled content. Vue auto-escape mostly protects, but defense-in-depth is degraded. | Track Revolut SDK changelog for nonce support; migrate when available. Already an open item via gauntlet E-4 (CSP dual-definition). |
| L-6 | `app/Http/Middleware/SecurityHeaders.php` | Missing `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, no CSP `report-uri` / `report-to`. | Add these directives to production CSP. Low risk because no user-controlled HTML is rendered, but no reason not to harden. |
| L-7 | `app/Http/Middleware/SanitizeInput.php:102` | `strip_tags` is destructive on legitimate content containing `<` or `>` (e.g., a goal description like `target value > £100k`). UX issue more than security. | Either escape via `htmlspecialchars` (preserves content, encodes entities) or rely on Vue auto-escape and remove the strip_tags. Vue auto-escape is the more reliable defense; SanitizeInput's strip_tags is an extra layer that costs content fidelity. |

## VERIFIED CORRECT — invariants held during review

These are auth invariants I tested and confirmed are sound. Listing them so future audits don't re-do this work.

| ID | Invariant | Where verified |
|---|---|---|
| V-1 | `EmailVerificationCode::findValidCode` filters by `expires_at > now()`, `verified_at IS NULL`, `failed_attempts < 5`. Codes cannot be reused or replayed. | `core/app/Core/Models/EmailVerificationCode.php:138-147` |
| V-2 | `EmailVerificationCode::generate` invalidates all prior unverified codes for the same `(user_id, type)` before issuing a new one — no accumulation of valid codes. | Same file, `:92-111` |
| V-3 | `MFAController::verify` deletes ALL tokens via `$user->tokens()->delete()` before issuing the MFA-verified token, so pre-MFA state cannot persist. (M-8 is a side-effect of this being too aggressive, not a missing rotation.) | `app/Http/Controllers/Api/MFAController.php:204` |
| V-4 | `MFAController::validateChallengeToken` is single-use — `Cache::forget($cacheKey)` is called after read. | Same file, `:138` |
| V-5 | `PasswordResetService::resetPassword` revokes all user tokens on successful password reset. | `app/Services/Auth/PasswordResetService.php:224` |
| V-6 | `PreviewWriteInterceptor` excluded routes are minimal and intentional (login, register, password-reset, document-upload, AI chat, mobile auth) — sufficient for the preview-user → real-user conversion flow without leaking write access. | `app/Http/Middleware/PreviewWriteInterceptor.php:47-72` |
| V-7 | `EnsureMFAVerified` differentiates API-token (ability check via `currentAccessToken()->can('mfa_verified')`) from session-based (session flag check) auth. Returns 403 when MFA required. | `app/Http/Middleware/EnsureMFAVerified.php:29-44` |
| V-8 | `LoginLockoutService` is invoked from BOTH password-failed (`AuthController::login:177`) AND MFA-failed (`MFAController::verify:186`) paths — single shared lockout counter. IP-level lockout also present (`AuthController::login:133`). | Both files |
| V-9 | Admin promotion is NOT mass-assignable. Both `register` (line 497) and `login` auto-promote (line 149-150) set `is_admin` explicitly via `$user->is_admin = true; $user->save()` rather than via `User::create([...])`. | `app/Http/Controllers/Api/AuthController.php:149, 496` |
| V-10 | All logging of authentication events masks emails via `$this->maskEmail()` — no PII in logs. | Multiple sites in `AuthController.php` |
| V-11 | `SanitizeInput` exempts cryptographic / secret fields (`password`, `code`, `challenge_token`, `recovery_code`, `mfa_secret`, `access_token`, `mfa_token`) — strip_tags does not mutate them. | `app/Http/Middleware/SanitizeInput.php:36-48` |
| V-12 | `EmailVerificationCode::$hidden` excludes `code` and `challenge_token` from `toArray()` / JSON serialization — no leak via API resources. | `core/app/Core/Models/EmailVerificationCode.php:29-32` |
| V-13 | `PendingRegistration::$hidden` excludes `password` and `verification_code` — no leak via debug output or accidental serialization. | `core/app/Core/Models/PendingRegistration.php:43-46` |
| V-14 | Sanctum stores tokens as SHA-256 hashes at rest (Sanctum default — no plaintext storage). | Framework-level; no override in Fynla |
| V-15 | Every sensitive endpoint has an explicit `throttle:N,1` per-minute rate limit (`register` 5, `login` 5, `verify-code` 10, `resend-code` 5, `mfa/verify` 10, `mfa/recovery` 5, `password-reset/request` 3, `change-password` 5). | `routes/api.php:46-72` |
| V-16 | HSTS header `max-age=31536000; includeSubDomains` set in production. | `app/Http/Middleware/SecurityHeaders.php:28` |
| V-17 | `Cross-Origin-Opener-Policy: same-origin` set. `Permissions-Policy` denies camera, microphone, geolocation, USB, Bluetooth. | Same file `:64-67` |
| V-18 | `PasswordResetSession` token is 64 random alphanumeric chars (~384 bits entropy). Effectively unguessable. | Verified via `Str::random(64)` calls; matches the size:64 validator in `PasswordResetController` |
| V-19 | Password complexity (≥8 chars, mixed case, digit, special) enforced consistently on register (`RegisterRequest`), change-password (`AuthController:386`), and password reset (`PasswordResetController:138`). | All three sites |
| V-20 | `Auth::attempt` uses Laravel's bcrypt verification (`Hash::check` under the hood). No plaintext password comparison anywhere in the auth surface. | `AuthController:175`, `MFAController:295`, `MFAController:324` |
| V-21 | Pre-MFA state never persists. Login with MFA enabled returns a challenge token, not an access token — `mfa_token` is a single-use cache key, NOT a Sanctum token. | `AuthController:212-225` + `MFAController:111-141` |

## Recommended fix order (if all HIGH+MEDIUM applied this session)

Estimated ~3 hours total for HIGH; ~6 hours for HIGH+MEDIUM. Each fix is independent except where noted.

1. **H-1** — Drop `reset_token` from `initiateReset` response. 15 min, single-file change, requires frontend coordination on response shape (`resources/js/services/auth/passwordReset.js`).
2. **H-2** + **H-3** — Add MFA gate to `disable` and `regenerateRecoveryCodes`. 30 min total. Update form requests / inline `$request->validate` to require `code`. Update frontend dialogs to collect MFA code.
3. **M-3** + **M-4** — `hash_equals` swap. 10 min total.
4. **M-1** + **M-2** — Move admin auto-promote post-Auth::attempt + add audit logging. 45 min, includes new `AuditLog::ACTION_ADMIN_PROMOTED` constant + migration to add it to the enum if `audit_logs.action` is enum-typed.
5. **M-6** — Revoke OTHER tokens on password change (keep current). 15 min.
6. **M-5** — Add lockout counter on recovery code failure. 15 min, includes new `LoginAttempt::REASON_RECOVERY_CODE_FAILED` constant.
7. **M-8** — Scope token deletion in MFA::verify. 20 min, requires verifying mobile flow doesn't regress (test on iOS).
8. **M-7** — Add unit test for AI-chat tool-executor preview blocking. 1-2 hours (skill is "verify the invariant"; existing tool executor must already enforce it).
9. **M-9** — Spouse account email requirement. 30 min + frontend dialog change.
10. **M-10** — Production guard on preview-user skip. 10 min.

LOW items: log to triage as L-1..L-7 entries, fix opportunistically.

## Out of scope for this slice

Per `Plans/test-gauntlet-plan-v1.md` § G-4-b:
- **Slice 2 (next)**: Revolut webhook + payment endpoints
- **Slice 3 (after)**: 89 API controllers + 83 form requests sweep
- **G-4-c**: Horizontal-privilege morph escalation test (separate gate)
- **G-4-d**: Secret management audit (separate gate)
- **G-4-e**: Auth-flow review (this slice is part of it; G-4-e is the broader review)
- **G-4-f**: Externalised LLM-driven audit (separate gate)

The CSP `unsafe-inline` issue is tracked separately as **E-4** in the triage backlog and gauntlet **H-5** for resolution.
