---
type: handover
mode: context-clear
date: 2026-05-12
session: 2
branch: refactor/uk-pack-relocation
previous_session: 2026-05-12 session 1 (end-of-day) — R-15 prep SHIPPED, dev bootstrap planned
---

# Context Clear Handover — 2026-05-12, Session 2

## Immediate state

About to clear context with **111 commits ahead of `main`** and a freshly-validated, codebase-audited PRD for the **test gauntlet** (Plan v3 successor workstream). Dev env `csjones.co/fynla_inter` is **live and smoke-tested**; PRD interview surfaced 14 architectural concerns and ~12 factual corrections that all landed in the amended spec + plan + new PRD. **Next session has explicit pickup: (b) close plan v3 paperwork, then (a) start G-(-1) (lifecycle MVP + singleton fix).**

## The thread (this session's arc — chronological)

1. **Session-start auto-resume** from yesterday's handover pointed at a "deploy the R-15 prep to dev + prod" workstream. Reality check revealed `csjones.co/fynla_inter` had **never been bootstrapped** — handover author had conflated it with the legacy UK-only `csjones.co/fynla`. Halted prod-bound auto-execution; surfaced the gap.
2. **Path picked: Option A** — bootstrap dev first, defer prod. Worked through `deploy/csjones-fynla/BOOTSTRAP.md` end-to-end. Three blockers hit + fixed: (a) `composer install --no-dev` failed because country packs were declared `require-dev`; (b) `rsync --exclude="Plans/"` was unanchored and dropped 98 critical files (`app/Services/Plans/`, `packs/country-gb/src/Plans/`, etc); (c) SiteGround's suexec rejected the macOS 664/775 perms with a silent 500. All three are now codified as repo fixes (commits `ef94699`, `2e9fcb6`).
3. **CSJ directive: prod freeze for ~2 months** pending full test gauntlet (unit / e2e / user / beta / logic / systems / security / hardening). Memory saved (`feedback_prod_deploy_freeze.md`). All prod-bound tasks deleted.
4. **Wrote spec + plan for the test gauntlet.** CSJ approved skipping review and ran `/prd-writer` straight away.
5. **`/prd-writer` flow:** two parallel validation agents (code-explorer for facts, code-architect for fit) returned heavy findings. 12-question rolling interview with CSJ. All 10 spec-time open questions resolved + 2 new ones from audit. Spec + plan amended in place; canonical PRD written at `May/May12Updates/PRD-test-gauntlet-v1.md`.
6. **XAI/Grok 4.3 key landed and live.** CSJ provided the key labelled `IFynlaInternational`. Server `.env` patched, model defaults flipped from `grok-4-1-fast-reasoning` → `grok-4.3` across `config/services.php`, `.env.production` template, `XaiClient.php` docstring. Smoke-tested through Laravel `XaiClient` — `model=grok-4.3`, response="PONG", 273 tokens.

## Files touched (all committed + pushed this session)

6 commits, branch tip `5633c93`:

- `ef94699` `fix(composer)`: move country packs to `require` so prod `composer install --no-dev` resolves them
- `2e9fcb6` `docs(deploy)`: R-15-bootstrap learnings — anchored rsync excludes, suexec chmod, /fynla_inter paths
- `40d9ac4` `docs(gauntlet)`: test gauntlet spec + plan v1 — 8-layer pre-prod validation
- `cc92d90` `docs(gauntlet)`: PRD + amended spec/plan after codebase-audit-driven interview
- `d9724f9` `chore(ai)`: bump xAI default model to grok-4.3
- `5633c93` `chore(ai)`: also bump config/services.php fallback to grok-4.3

Working tree: **clean**.

## What the next Claude needs to know

1. **Prod is frozen for ~2 months from 2026-05-12.** Memory file `feedback_prod_deploy_freeze.md` documents this. Don't propose prod deploys, prod migration runs, or "ship to fynla.org" steps as next-actions — even if older handovers reference them. Old `May/May12Updates/deploy-2026-05-12.md` is historical now, not a runbook.
2. **Canonical vault is `/Users/CSJ/Desktop/fynlaInter/FynlaInter/`**, NOT `/Users/CSJ/Desktop/fynlaBrain/`. The `fynlaBrain` vault has phantom handover files from a prior bad Haiku run; don't write to it (this session-end skipped the vault-sync skill for that reason — fynlaBrain is informational-only).
3. **The amended spec + plan are the authoritative gauntlet docs.** Read in this order: `Plans/test-gauntlet-spec-v1.md` → `Plans/test-gauntlet-plan-v1.md` → `May/May12Updates/PRD-test-gauntlet-v1.md`. The PRD's 18 must-have FRs are the work list.
4. **Dev `.env` on the server is fully populated.** DB password = `PixieRebecca2020` (same as everything else CSJ uses, per session-2 record); Revolut sandbox keys are real and live; XAI key is `xai-LDTNmd...` (label `IFynlaInternational`); mail `noreply@fynla.org` reused from prod. Server file `/home/u163-ptanegf9edny/www/csjones.co/fynla_inter-app/.env` is the source of truth. The repo template `deploy/csjones-fynla/.env.production` has only `YOUR_*` placeholders.
5. **`g-7` (closed beta) was deleted from the gauntlet** per CSJ Q7. Internal-only validation via G-6. No external recruitment, no GDPR exposure.
6. **`widow` persona doesn't exist** — replaced by `student` in `PreviewUserSeeder::PERSONAS`. CLAUDE.md still lists `widow` (CLAUDE.md amendment is task G-1-d in the gauntlet plan). Don't reference `widow` in new tests.
7. **`config/lifecycle.php` doesn't exist.** The lifecycle email engine was never implemented — full implementation is tech debt. G-(-1) builds only the MVP plumbing.
8. **2 R-14a residual bindings** still resolve `App\…`: `pack.gb.exchange_control` → `App\Services\ExchangeControl\UkExchangeControl`, `pack.gb.tax_optimisation` → `App\Agents\TaxOptimisationAgent`. Tracked in G-2-g; resolution post-cutover.
9. **`PackIsolationTest` allow-list is 60+ entries (R-14a deferrals), NOT empty.** The `App\Models` *sub-section* is empty — that's the R-14b exit gate. Don't propose "make the allow-list empty" as a near-term goal.
10. **dev server local on this Mac uses :8001/:5174** (port collision with the legacy `/Users/CSJ/Desktop/fynla` repo on :8000). Should still be running per Phase 1c of session-start; if not, `./dev.sh` will pick fresh ports.

## Pick up from here (CSJ explicit instruction)

**Next session sequence: (b) then (a).**

### First: Close plan v3 paperwork (task #6)

1. Update `Plans/architecture-plan-v3.md` frontmatter: `status: closed (dev-green; prod deferred per feedback_prod_deploy_freeze.md)`.
2. Archive the FRONT OF LIST in `CSJTODO.md` — the architecture campaign R-0 → R-14b is functionally done; the workstream has moved to the test gauntlet.
3. Add a closing entry to `CSJTODO.md` referencing the test gauntlet spec/plan/PRD as the active workstream.
4. Commit: `docs(architecture): close plan v3 — dev-green close, prod deferred to post-gauntlet`.

### Then: Start G-(-1) — lifecycle MVP + singleton fix (~5 hr total)

Per PRD `May/May12Updates/PRD-test-gauntlet-v1.md`:

**FR-M1 (lifecycle MVP, ~4 hr):**
- Create `config/lifecycle.php` returning `['test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT'), 'events' => []]`.
- Create `app/Services/Lifecycle/LifecycleEngine.php` with `public function dispatch(User $user, string $event, array $context = []): void` — logs the event, routes recipient via override, no actual mail send (stub).
- Register `lifecycle:run-daily` no-op stub in `app/Console/Kernel.php` so `schedule:list` shows it.
- Write a unit test asserting `config('lifecycle.test_recipient_override')` returns `chris@fynla.org` when env var is set.
- Verify on server: `php artisan tinker --execute="echo config('lifecycle.test_recipient_override').PHP_EOL;"` returns `chris@fynla.org`.
- All other lifecycle work = tech debt for post-cutover.

**FR-M2 (singleton fix, ~30 min):**
- Edit `packs/country-gb/src/Providers/GbPackServiceProvider.php`:
  - `pack.gb.user_relations` → `$this->app->singleton(...)` (was `bind()`)
  - `pack.gb.asset_repo` → `$this->app->singleton(...)`
  - `pack.gb.estate_repo` → `$this->app->singleton(...)`
  - `pack.gb.asset_resolver` → `$this->app->singleton(...)`
- Create `tests/Feature/PackBindingSingletonTest.php` with 4 identity assertions: `expect(app()->make($key))->toBe(app()->make($key))` for each binding.
- Run `./vendor/bin/pest tests/Feature/PackBindingSingletonTest.php` — must be green.

### After G-(-1), plan is to enter G-0 (setup completion) — the items still pending per task list

- Task #17: SiteGround cron for `fynla_inter` (CSJ-only; web UI)
- Revolut sandbox webhook registration (CSJ-only; sandbox dashboard) → paste me the `wsk_…`

Then G-1 (unit + logic).

## Context hints

- Active branch type: **mainline** (`refactor/uk-pack-relocation` — campaign branch). Will not merge to `main` until prod freeze lifts post-gauntlet.
- Behind origin/main by: **0 commits behind**; **114 ahead of origin/main**. Branch is pushed and current.
- Uncommitted: **none, working tree clean**
- Last commit: `5633c93` chore(ai): also bump config/services.php fallback to grok-4.3
- Test baseline: ~2,669 `it()` blocks across `tests/**/*.php` + `packs/**/*.php` (was incorrectly stated as 2,825 in prior handovers; corrected in spec + plan amendments)
- Outstanding tasks: #6 (plan v3 close), #17 (cron), Revolut webhook
- **Skipped this session-end:** `tech-debt-session` (no uncommitted changes), heavyweight `vault-sync` skill (canonical vault is fynlaInter/FynlaInter/; phantoms exist in fynlaBrain and the skill might propagate them). Vault is informational; next session can run `vault-sync` if convenient.
