---
type: handover
mode: context-clear
date: 2026-05-13
session: 2
branch: refactor/uk-pack-relocation
previous_session: 2026-05-13 session 1 (end-of-day) — G-4-b slice 2 close + deploy note
---

# Context Clear Handover — 2026-05-13, Session 2

## Immediate state

G-4-b slice 3 **audit phase complete** — top-10 high-sensitivity controllers reviewed, 3 HIGH + 1 MEDIUM + 5 LOW findings documented at `May/May13Updates/g-4-b-slice-3-controllers-audit.md`. **No code changes applied yet** (HIGH fixes deferred to next working session — see "Pick up from here"). Slice 1+2 deploy to `csjones.co/fynla_inter` **still not done** — blocker is the ssh-fynla MCP is hardcoded to fynla.org (production, frozen), and there is no ssh-csjones-dev MCP configured. Branch `refactor/uk-pack-relocation` tip `2670f9f`, 132 commits ahead of `main`, working tree dirty only with the new untracked audit doc.

## The thread

- Session bootstrapped via `/session-start`, picked up the handover-2026-05-13-session-1 "default direction of travel" — `(a) deploy slice 1+2 to dev, then (b) G-4-b slice 3`.
- Built the dev frontend bundle (`./deploy/csjones-fynla/build.sh`, 1m 19s, 7.7 MB) — ready to upload.
- Surfaced the slice 1+2 upload manifest (14 files + bundle + lockfiles + 1 migration).
- CSJ asked me to upload via the `ssh-fynla` MCP. Discovered the MCP's `working_directory` default is `~/www/fynla.org/public_html` — i.e. production. Refused to upload (would violate `feedback_prod_deploy_freeze.md`). Surfaced 4 options for the upload path; CSJ chose **"keep auditing"** without picking an upload option, so deploy is parked.
- CSJ asked sanity questions ("why are we doing this?", "is local working?", "do both countries reflect each other?"). Answered in detail — local is healthy, GB/ZA share the same base (contracts, namespaces, provider pattern), feature density differs by design (GB 51 controllers, ZA 5).
- Resumed slice 3 audit. Reviewed 14 controllers total in this session: AdminController, AdvisorController, DocumentController, GDPRController, IHTController, GiftingController, WillController, WillDocumentController, LpaController, TrustController, AiChatController, SpousePermissionController, FamilyMembersController, ReferralController.
- Found **3 HIGHs**, only one of which is a simple route-file fix; H-3 needs a refactor of the `handleSpouseCreation` consent flow.

## Files touched (uncommitted)

```
?? May/May13Updates/g-4-b-slice-3-controllers-audit.md
```

That's it — no source modifications this session.

## Audit findings — G-4-b slice 3 (cross-reference to audit doc § 4-5)

| ID | Severity | Where | What |
|---|---|---|---|
| **H-1** | HIGH | `routes/api.php:326-388` admin group | Write endpoints (users CRUD, ai-provider switch, backup restore/delete, discount codes) lack `mfa.verified`. Same risk class as slice 2 H-4; larger blast radius. Fix shape: split admin route group into read/write, apply `mfa.verified` to write group. ~15 min + tests. |
| **H-2** | HIGH | `routes/api.php:108-112` legacy GDPR | `POST /erasure` + `/erasure/{id}/confirm` bypass the 3-step verification flow. Frontend uses only the new flow (verified via grep of `resources/js/services/privacyService.js`). Stolen session token → account destruction in 2 calls. Fix shape: delete 4 legacy route registrations, keep controller methods inert. ~10 min + tests. |
| **H-3** | HIGH | `FamilyMembersController.php:183` `handleSpouseCreation` | Adding existing user as "spouse" auto-links both accounts, **overwrites the victim's `spouse_id`/`marital_status`/`annual_employment_income`/address**, auto-accepts bidirectional `SpousePermission` — **no consent step from the victim**. Attacker with target's email gets full data-sharing read access in one request. Fix shape: rewire to use existing `SpousePermissionController::request → accept` flow; only `accept` on B's side should commit the linkage and permissions. **Non-trivial refactor**: 1–2 hours of careful work + tests. |
| M-1 | MEDIUM | `TrustController::create/updateTrust` | 8 currency fields accept `numeric|min:0` with no `max:`. Use `ValidationLimits::currencyRules()`. |
| L-1..L-5 | LOW | IHT/Gifting/Trust + FamilyMembers logging | Currency overflow on profile/DGT calc, missing `max:` on Trust text fields, inline validate vs FormRequest convention drift, PII in INFO logs. |

Full evidence + fix shapes + tests-to-add: `May/May13Updates/g-4-b-slice-3-controllers-audit.md`.

## What the next Claude needs to know

- **Don't propose deploying to prod.** `feedback_prod_deploy_freeze.md` — prod frozen ~2 months from 2026-05-12. Only `csjones.co/fynla_inter`.
- **`ssh-fynla` MCP is configured for production**, not dev — using it to "deploy to dev" would silently push to fynla.org. CSJ has not yet picked an upload path for slice 1+2. Wait for direction before touching deploy.
- **H-3 is the marquee finding** — it's a privacy/consent bypass, severity peer to "stolen session token grants read access to another user's full financial data". Treat as production-blocking even if cosmetically a refactor. Worst of the three HIGHs.
- The slice 1+2 deploy is **still pending** (3rd consecutive session). Each "audit on locally-fixed but not-dev-deployed code" session widens the gap between local and dev. Not fatal, but priority creeping.
- The CSJ instruction "don't stop for clarifying questions, make the reasonable call" applies — but CSJ does want to *know* when a blocker actually blocks (e.g. the MCP-points-at-prod issue). Surface concisely, don't grind through.
- The audit doc is the durable artefact — even if no fixes ship, the findings are recorded.

## Pick up from here

1. **Apply slice 3 HIGH fixes (the contract pattern from slices 1+2):**
   - **H-1 first** — easiest. Split admin route group in `routes/api.php` into read (no `mfa.verified`) and write (with `mfa.verified`). Add `AdminWriteEndpointsRequireMfaTest`.
   - **H-2 second** — easier still. Delete `routes/api.php:108-112` (4 legacy GDPR erasure route lines). Add `LegacyGdprErasureRoutesAreUnroutableTest` asserting 404 on both legacy paths.
   - **H-3 third** — separate commit with its own audit/test plan. Rewire `FamilyMembersController::handleSpouseCreation` to reuse `SpousePermissionController::request` flow. Tests: `FamilyMembersControllerCannotAutoLinkExistingUserTest`, `FamilyMembersControllerCreatesPendingSpousePermissionTest`, `FamilyMembersControllerCannotOverwriteSpouseIncomeTest`.
2. **Log M-1 + L-1..L-5 to triage** — append to `May/May12Updates/triage-backlog.md` as E-16..E-22 (next free IDs).
3. **Update `Plans/test-gauntlet-plan-v1.md`** — mark G-4-b slice 3 PASS once the three HIGHs are closed and tests are green.
4. **Resolve the dev-deploy blocker** — CSJ needs to either upload via SiteGround File Manager (Rule #1) or configure an `ssh-csjones-dev` MCP server pointing at `ssh.csjones.co:18765 / u163-ptanegf9edny`. Until then, slice 1/2/3 fixes stay local-only.

## Standing context (unchanged this session)

- Production (fynla.org): **frozen, untouched**, no MCP-driven actions allowed.
- Dev (csjones.co/fynla_inter): still running session-4-deployed code; sessions 5, 6, 7 fixes pending upload.
- Pest serial baseline: 1002 (988 base + 14 slice-2 tests). No new tests added this session (audit only).
- CSJ-only carry-over: SiteGround cron for `fynla_inter` (G-0-i), Revolut sandbox webhook URL registration (G-0-iii).

Co-Authored-By: Claude Opus 4.7 (1M context) &lt;noreply@anthropic.com&gt;
