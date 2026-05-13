---
type: handover
mode: context-clear
date: 2026-05-13
session: 3
branch: refactor/uk-pack-relocation
previous_session: 2026-05-13 session 2 (context-clear) — G-4-b slice 3 audit only, no fixes shipped
---

# Context Clear Handover — 2026-05-13, Session 3

## Immediate state

G-4-b slice 3 **PASS** — all 3 HIGH closed in-session with 60 new pinning tests. Pest serial baseline 2975 passing, 0 regressions. Two commits shipped and pushed: `09302e9` (H-1 admin write MFA + H-2 legacy GDPR erasure) and `711b0d7` (H-3 spouse consent refactor + slice 3 docs). Branch `refactor/uk-pack-relocation` tip `711b0d7`, **134 commits ahead of `main`, all pushed**. Working tree clean. Slice 1+2+3 deploy to dev still NOT done — same `ssh-fynla MCP points at prod` blocker, 4th consecutive session.

## The thread

- Bootstrapped via `/session-start`. Picked up handover-2026-05-13-session-2-clear's default direction-of-travel: apply slice 3 HIGH fixes inline.
- **H-1** (15 min + tests): split `routes/api.php` admin route group into a read group (no `mfa.verified`) and a write group (with `mfa.verified`). 17 admin write endpoints now MFA-gated. 39 new tests in `tests/Feature/Admin/AdminWriteEndpointsRequireMfaTest.php`. One snag: `Sanctum::actingAs` doesn't create a real bearer token, so the middleware's session-flag branch fires — fixed by setting `session(['mfa_verified' => true])` alongside `Sanctum::actingAs($user, ['mfa_verified'])`.
- **H-2** (10 min + tests): deleted 4 legacy GDPR erasure routes (`/erasure`, `/erasure/status`, `/erasure/{id}/confirm`, `/erasure/{id}/cancel`). Marked controller methods `@deprecated` (kept inert in case admin tooling ever needs them). 8 new tests in `tests/Feature/GDPR/LegacyGdprErasureRoutesAreUnroutableTest.php` — they use **route-table inspection**, not HTTP status, because `routes/web.php` has an SPA catch-all GET that turns unknown POSTs into 405 (not 404).
- **H-3** (1.5 hr — the marquee fix): rewrote `FamilyMembersController::handleSpouseCreation` existing-account branch. Pre-fix it auto-linked accounts, overwrote the invitee's `annual_employment_income` + address, auto-accepted bidirectional `SpousePermission` — no consent step. Post-fix: invite creates ONLY a pending `SpousePermission` + a `FamilyMember` on the inviter's side. `SpousePermissionController::accept` extended to be the SINGLE source of truth for finalising a spouse link — atomically sets `spouse_id` on both, creates reciprocal accepted permission + reciprocal `FamilyMember`, race-safe via `lockForUpdate()`. `reject` mirrors. 2 new files (`SpouseDataSharingRequest` mailable + Blade template) for the pre-acceptance email. 13 new tests in `tests/Feature/Api/FamilyMembersControllerSpouseConsentTest.php`.
- 5 pre-existing `Tests\Feature\Auth\GDPRApiTest > Data Erasure` cases hit the now-deleted legacy routes — replaced the whole `Data Erasure` describe with a single preview-user-blocked-from-`/initiate` invariant (the legacy CRUD is durably covered by H-2's test). Arch test got a `SpousePermissionController` DB-facade exception (uses `DB::transaction` for atomic accept).
- Logged M-1 + L-1..L-5 + M-2 candidate + 1 cosmetic to triage backlog as **E-16..E-23**.
- Updated `Plans/test-gauntlet-plan-v1.md` slice 3 → PASS and flipped the audit doc status from IN PROGRESS to PASS with file-by-file closure detail.
- Two clean commits, pushed, vault synced (Haiku 4.5 subagent).

## Files touched (this session)

```
M  May/May12Updates/triage-backlog.md            (E-16..E-23 added)
M  May/May13Updates/g-4-b-slice-3-controllers-audit.md  (status → PASS)
M  May/May13Updates/deploy-2026-05-13.md         (slice 3 entries appended)
M  Plans/test-gauntlet-plan-v1.md                (slice 3 PASS)
M  app/Http/Controllers/Api/FamilyMembersController.php
M  app/Http/Controllers/Api/GDPRController.php
M  app/Http/Controllers/Api/SpousePermissionController.php
M  routes/api.php
M  tests/Architecture/ApplicationArchitectureTest.php
M  tests/Feature/Auth/GDPRApiTest.php
A  app/Mail/SpouseDataSharingRequest.php
A  resources/views/emails/spouse-data-sharing-request.blade.php
A  tests/Feature/Admin/AdminWriteEndpointsRequireMfaTest.php
A  tests/Feature/Api/FamilyMembersControllerSpouseConsentTest.php
A  tests/Feature/GDPR/LegacyGdprErasureRoutesAreUnroutableTest.php
```

All committed and pushed across `09302e9` + `711b0d7`. Working tree clean.

## What the next Claude needs to know

- **Slice 3 is fully closed locally — not on dev.** Same deploy blocker as the past 3 sessions: the `ssh-fynla` MCP defaults to `~/www/fynla.org/public_html` (production, frozen per `feedback_prod_deploy_freeze.md`). No `ssh-csjones-dev` MCP exists. CSJ has not yet picked an upload path. Until resolved, slices 1+2+3 stay local-only — the gap from local to dev is now 4 sessions wide.
- **Two non-obvious patterns from this session worth remembering:**
  1. `routes/web.php` has a global SPA catch-all GET (`Route::get('/{any}', ...)->where('any', '.*')`). This means a missing POST route returns **405** (Laravel finds the catch-all GET, says "method not allowed"), not 404. Tests for unrouted endpoints should use route-table inspection (`Route::getRoutes()`), not HTTP status assertions. There's a `legacyRouteRegistered($method, $uri)` helper in `LegacyGdprErasureRoutesAreUnroutableTest.php` worth copying.
  2. `Sanctum::actingAs($user, ['mfa_verified'])` does NOT set a real bearer token; `$request->bearerToken()` returns null and the `EnsureMFAVerified` middleware falls through to its session-flag branch. Tests need both `Sanctum::actingAs($user, ['mfa_verified'])` AND `session(['mfa_verified' => true])`.
- **`SpousePermissionController::accept` is now THE single source of truth for spouse-link finalisation.** Do NOT re-introduce auto-link logic in `FamilyMembersController::handleSpouseCreation` if you're tempted to "simplify" the flow. The vault-sync subagent flagged this as a memory candidate; CSJ to decide whether to save.
- **`Tests\Feature\Auth\GDPRApiTest > Data Erasure`** has been collapsed to one invariant. If you see Pest output mentioning a missing "Data Erasure: it requests account erasure" test — that's expected, the legacy flow is gone.
- **`Current State/Auth.md`** in the vault is now stale wrt today's admin/GDPR/spouse changes (last modified ~7 days ago). Vault-sync subagent flagged it; not updated automatically per CSJ-decision-only policy. Worth a 10-min refresh next time auth touches happen.

## Pick up from here

Default order if CSJ says "go":

1. **(a) Resolve the dev-deploy blocker** — pick ONE: (i) upload via SiteGround File Manager (Rule #1), (ii) configure `ssh-csjones-dev` MCP server (`u163-ptanegf9edny@ssh.csjones.co:18765`, key `~/.ssh/fynlaDev`), or (iii) `git pull` on the dev server. Until resolved, no security work lands on dev. **Recommended next step** — the gap is 4 sessions wide now.
2. **(b) G-4-b slice 4 — Form Requests sweep (~half-day)** — only thing left in slice 3 scope is the 83 Form Request authorize() spot-check. Slice 3 was sample-driven on the top 10 controllers; a sample of 10 random form requests would close G-4-b.
3. **(c) G-4-c — Horizontal-privilege morph escalation test (~0.5 day)** — well-defined test in `Plans/test-gauntlet-plan-v1.md` § G-4-c. Creates two users, tries to point a PAT at a different user, asserts ownership middleware rejects.
4. **(d) G-1-c logic fixtures** — still blocked on CSJ sample sign-off (~4-6 hr CSJ effort on 2 personas).
5. **(e) Parallel-mode test flake triage (30 min)** — known carry-over.

**Default if CSJ says "go":** (a) first (unblock the deploy gap), then (b).

## Standing context (unchanged)

- Production (fynla.org) **FROZEN** for ~2 months from 2026-05-12. No prod deploys.
- Dev (csjones.co/fynla_inter) running session-4-deployed code. Sessions 5, 6, 7 + 13-session-1 + 13-session-3 fixes ALL pending upload.
- Pest serial baseline: **2975** (up from 2915 at session 2 close — +60 slice 3 tests).
- CSJ-only carry-over: SiteGround cron for `fynla_inter` (G-0-i), Revolut sandbox webhook URL (G-0-iii).
- Triage backlog: 16 enhancements logged (E-1 through E-23, with gaps for closed items).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
