---
type: handover
mode: context-clear
date: 2026-05-12
session: 4
branch: refactor/uk-pack-relocation
previous_session: 2026-05-12 session 3 (context-clear) — plan v3 closed + G-(-1) + G-1-d SHIPPED; dev .env APP_KEY broken
---

# Context Clear Handover — 2026-05-12, Session 4

## Immediate state

**Gauntlet workstreams G-0 (3/5 done, 2 CSJ-only) + G-1 (3/4 done: G-1-a/b/d) all green on dev.** Branch `refactor/uk-pack-relocation` tip **`29aecfd`**, **122 commits ahead of `main`**, all pushed. Pest baseline holds at **2,836 passed / 1 skipped** locally AND on dev. Working tree clean. The previous session's blocker — dev `.env` `APP_KEY` placeholder causing HTTP 500 — is **fixed**. The dev site `https://csjones.co/fynla_inter/` is **HTTP 200**, all 4 country packs discovered, full test suite runs cleanly. Next active task is **G-1-c** (logic fixtures, ~3 days, needs CSJ sample sign-off on 2 personas) or **G-4 prep** (security audit, can run in parallel).

## The thread

- Session-4 bootstrapped via session-start. Session-3 handover surfaced **Step A** as a CSJ-only decision (APP_KEY restoration), and Steps C/D as solo-actionable. Started C (triage backlog) + D (13 observer test scaffolds with 59 `it()->todo()` placeholders) in parallel while waiting on Step A.
- **CSJ challenged the session-3 handover narrative** — "we have NEVER deployed Fynla International to csjones.co". SSH probe revealed a Laravel install **does** exist at `~/www/csjones.co/fynla_inter-app/` (Apr 14 symlink, May 11 code edits) but it's clearly an incomplete bootstrap (vault/doc files mixed in: `.obsidian/`, `Articles/`, `Home.md`, `Marketplace.md`, `Fynla_International_Handover.docx`, etc.). Logged as **B-2 sev-2 cleanup** in triage backlog. CSJ confirmed no real user data ever ran against this install → destructive APP_KEY regeneration is risk-free.
- **B-1 fixed (commit not needed — server state)**: `ssh ... php artisan key:generate --force && config:clear && optimize` + `db:seed --force`. New APP_KEY: `base64:5IWA2GxFCsX4Wy/OJU84TOwmYmagPgZVHcC30mbL5S4=`. Backup at `~/www/csjones.co/fynla_inter-app/.env.before-key-regen-20260512-123251`. Dev site HTTP 200 confirmed.
- **G-0-iv verified (not just ticked)**: per CSJ's "test it, we don't just mark it" feedback. Three exit-gate checks executed live: (1) `config('lifecycle.test_recipient_override')` returns `chris@fynla.org` via dev tinker; (2) `schedule:list` shows `0 7 * * *  php artisan lifecycle:run-daily`; (3) `tests/Unit/Services/Lifecycle/LifecycleEngineTest.php` 7/7 passing locally. Plan tracker updated.
- **G-0-v shipped**: `May/May12Updates/triage-backlog.md` created. 5 sections (sev-1/sev-2 bugs, enhancements, open questions, closed-resolved, conventions, companion docs). Seeded with B-1 (APP_KEY), B-2 (cruft cleanup), B-3 (APP_URL prefix), E-1/2/3/4 (standing tech debt), Q-2/3 (CSJ-only items).
- **G-1-a — significant prerequisite work uncovered.** Dev had **zero test files** in `tests/` or `packs/*/tests/`. Session-3's "deploy" only pushed 7 runtime PHP files. Rsync'd: `tests/`, `packs/country-{gb,za,xx-smoke}/tests/`, `phpunit.xml`, `composer.json`, `composer.lock`. Composer install + chmods + `chmod +x vendor/bin/pest` (binary was missing execute bit).
- **First Pest run on dev: 749 failed / 2,087 passed.** Pattern: POST endpoints → 405; GET endpoints → 200-where-401/403/422-expected. Worked through 4 hypotheses (route cache, PHP version, MySQL version, migrations) before finding the actual cause: **`APP_URL=https://csjones.co/fynla_inter` (subpath)** was being prepended to Pest's test URLs by `MakesHttpRequests::prepareUrlForRequest()`, so `postJson('/api/auth/register')` resolved to `/fynla_inter/api/auth/register` → 404 handler / catch-all routes → 405 for POSTs.
- **Fix in commit `b2ac915`:** added `<env name="APP_URL" value="http://localhost"/>` to `phpunit.xml`'s `<php>` block. Test-env-scoped only; runtime APP_URL on dev/prod unaffected. Works for any future subpath staging.
- **G-1-a result after fix: 2,836 passed / 1 skipped / 59 todos / 0 failed (11,018 assertions)** — exact parity with local. Plan tracker marked PASS.
- **G-1-b proper (commit `29aecfd`):** all 13 scaffold files converted from todos to real assertions. Pattern matrix:
  - **RiskObserver family (7 files):** `Bus::fake()` + `Bus::assertDispatched(RecalculateRiskProfileJob::class)` + `Cache::flush()` between create-and-action to bypass the parent's debounce key.
  - **NetWorthCacheObserver:** `Mockery::spy(NetWorthService::class)` + `shouldHaveReceived('invalidateCache')` (with `->twice()` for create+update and `->once()` for create-only). Joint owner branch tested. Registration smoke-check via regex against EventServiceProvider source.
  - **LifeEventMonteCarloObserver:** 3 spies (MonteCarloSimulator, GoalsProjectionService, CacheInvalidationService).
  - **RecommendationCacheObserver:** 7 agent spies, asserts per-model-type routing + always-CoordinatingAgent + joint-owner duplication.
  - **Goal observers (2 files):** real DB assertions on GoalContribution rows (like the existing `tests/Unit/Observers/GoalObserversTest.php`).
- Three test-implementation gotchas during G-1-b:
  - `LifeEvent.event_type` is an ENUM — `'birthday'` doesn't validate, used `'gift_received'` (valid + not risk-relevant).
  - `Property` uses split address columns — `address_line_1` not `address`.
  - `Goal::class` is NOT registered with `RecommendationCacheObserver` in `EventServiceProvider`, so the observer's `str_contains($class, 'Goal')` match arm is unreachable via Goal. Test routes via `LifeEvent` instead (same routing target).
- **G-1-b final: 59/59 passing / 110 assertions / 0 failures.** Suite-wide local Pest still 2,836 passed (no regressions).

## Files touched (all committed + pushed this session)

3 commits, branch tip `29aecfd`:

- `b2ac915` `fix(test)`: override APP_URL=http://localhost in phpunit.xml for subpath-deployed staging
- `7cb3342` `feat(gauntlet)`: G-0-iv/v + G-1-a green on dev — observer test scaffolds + triage backlog
- `29aecfd` `feat(gauntlet)`: G-1-b — observer-firing tests, 59/59 passing

15 files touched across all 3 commits:
- `phpunit.xml` (1 line — the gauntlet-saver)
- `Plans/test-gauntlet-plan-v1.md` (G-0-iv/v + G-1-a + G-1-b tracker updates)
- `May/May12Updates/triage-backlog.md` (new, G-0-v deliverable)
- 13 files in `tests/Feature/Observers/` (scaffolds → real assertions)

Server-side changes (NOT in git, server-state):
- `~/www/csjones.co/fynla_inter-app/.env` — new APP_KEY (`base64:5IWA2GxFCsX4Wy/OJU84TOwmYmagPgZVHcC30mbL5S4=`); backup at `.env.before-key-regen-20260512-123251`
- `~/www/csjones.co/fynla_inter-app/tests/` (260 files) + `packs/country-{gb,za,xx-smoke}/tests/` (33 files) rsync'd
- `~/www/csjones.co/fynla_inter-app/phpunit.xml`, `composer.json`, `composer.lock` synced
- `vendor/bin/pest` chmod +x
- DB reseeded twice (RefreshDatabase truncated everything during Pest runs — first run failed early on permission, second run failed on missing smoke-pack tests, third run completed clean)

## What the next Claude needs to know

1. **Gauntlet status as of session close:**
   - G-(-1) ✅ (session 3)
   - G-0-i (SiteGround cron) — pending, CSJ-only
   - G-0-ii ✅ (session 2: xAI key)
   - G-0-iii (Revolut webhook) — pending, CSJ-only
   - G-0-iv ✅ (session 4, verified live)
   - G-0-v ✅ (triage backlog at `May/May12Updates/triage-backlog.md`)
   - G-1-a ✅ (Pest baseline holds on dev)
   - G-1-b ✅ (59/59 observer firing tests)
   - G-1-c — **not started; ~3 days; substantial; needs CSJ sample sign-off on 2 personas**
   - G-1-d ✅ (session 3: widow persona surgery)

2. **G-1-c scope (next major task):** for each of 6 personas (`young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student`), walk the seeded data through every UK calculator (IHT, CGT, Income Tax, Pension AA, MPAA, ISA), document expected outputs to the penny, store as Pest data providers at `tests/Unit/LogicFixtures/{persona}_calculator_test.php`. **Sample sign-off:** CSJ reviews 2 personas full (~4-6 hr), spot-checks deltas for the other 4. See plan § 2 G-1-c.

3. **G-4 (security audit, ~1 week) can run in parallel with G-1-c** per plan calendar — doesn't need test infrastructure. May be worth starting while G-1-c is in CSJ's review queue.

4. **The APP_URL phpunit.xml fix in `b2ac915` is important for any subpath-deployed staging.** If a future deploy lands on e.g. `staging.example.com/app/`, the fix carries through — Pest still runs cleanly because the test-env override forces APP_URL to `http://localhost`.

5. **Dev server state is clean and healthy:**
   - URL: `https://csjones.co/fynla_inter/` → HTTP 200
   - Tests on dev: pass parity with local (2,836/1)
   - DB: reseeded post-Pest, all 6 personas + chris@fynla.org present
   - APP_KEY: real (post key:generate), `bootstrap/cache/config.php` regenerates cleanly
   - PHP 8.2.30 (ZTS) vs local 8.5.2 (NTS) — works fine, no compatibility issues

6. **`feedback_prod_deploy_freeze.md` still in force** — prod (fynla.org) frozen for ~2 months from 2026-05-12 pending gauntlet completion. Don't propose prod deploys.

7. **Standing tech debt unchanged from session-3:**
   - Full lifecycle email engine (post-cutover)
   - R-16 class_alias cleanup (post-cutover)
   - 2 R-14a residual bindings (`pack.gb.exchange_control`, `pack.gb.tax_optimisation`) — gauntlet G-2-g
   - CSP dual definition — gauntlet H-5

8. **Tech debt found this session: none.** The session-4 changes are well-structured. phpunit.xml is +1 line. The 13 observer tests follow the existing Pest convention. The triage backlog is clean markdown. Two minor nits worth noting (not blocking):
   - `NetWorthCacheObserverFiresTest::is registered as observer...` uses regex against `EventServiceProvider` source — brittle if file formatting changes. Acceptable as a smoke check.
   - Mockery::spy + `app()->instance()` pattern is repeated across 3 files (NetWorth, MonteCarlo, Recommendation). Could refactor to a trait, not worth it for 3 sites.

9. **B-2 cleanup task in triage backlog** — dev's `fynla_inter-app/` directory has non-Laravel files mixed in (`.obsidian/`, `Articles/`, `*.docx`, etc.) from an earlier broad rsync. Doesn't break anything; tracked as sev-2 cleanup.

10. **Vault sync skill deliberately NOT invoked** (4th consecutive session). Skill's hardcoded paths target legacy `/Users/CSJ/Desktop/fynla/` repo + `fynlaBrain/` — wrong project. Manual mirror to both `fynlaInter/FynlaInter/` (canonical) and `fynlaBrain/` (informational) instead. Don't run `/vault-sync` until the skill is fixed for this project.

## Pick up from here (auto-continue contract)

The handover skill's auto-continue contract applies — but session-4 ended at a natural break point (G-1-b complete, 3 commits pushed, working tree clean, all gauntlet G-0/G-1 work that I can solo is done). CSJ chose **end session here** when offered (a) G-1-c, (b) G-4 prep, (c) end. So next session has a decision to make:

### Step A: Choose direction

Ask CSJ at session-start:
- **(a) Start G-1-c** — logic fixtures (~3 days). Highest priority per the gauntlet calendar. Needs CSJ sign-off on 2 full personas (~4-6 hr CSJ effort). Begin with `young_family` or `peak_earners` as the deepest fixtures.
- **(b) G-4 prep** — security audit. Can run in parallel. Independent of G-1-c. Start with `composer audit` + `npm audit --production` (G-4-a, ~0.5 day) — pure read-only.
- **(c) Something CSJ chooses** — they may have shifted priorities.

Default direction-of-travel if CSJ says "go": **(b) G-4-a first** — it's a 30-minute pure-read task that builds context. Then move to G-1-c when CSJ is available for sample sign-off.

### Step B: Once direction chosen, work the plan

- For G-1-c: open `Plans/test-gauntlet-plan-v1.md` § 2 G-1-c and start scaffolding `tests/Unit/LogicFixtures/young_family_calculator_test.php`. Hit every UK calculator that touches the persona's seeded state.
- For G-4-a: SSH to dev (with CSJ approval each time per the standing rule), run `composer audit` + `npm audit --production`, capture findings, triage HIGH/CRITICAL.

## Decisions flagged (none unilateral this session)

All technical decisions in session-4 were within scope:
- `key:generate --force` chosen as the APP_KEY recovery path — CSJ explicitly authorised after I verified no user data existed.
- `APP_URL=http://localhost` override in phpunit.xml — straightforward test-framework fix, no ambiguity.
- Goal routing arm in RecommendationCacheObserver tested via LifeEvent — pragmatic given Goal isn't registered for that observer. Documented in test docblock.
- Two commits split (b2ac915 reusable fix + 7cb3342 gauntlet bookkeeping) for `git log` discoverability — matches session-3's pattern.

One decision deferred to next session: **G-1-c vs G-4 first** (CSJ called end-of-session before answering).

## Branch / deploy state

- Branch: `refactor/uk-pack-relocation`
- Behind origin: 0
- Ahead of origin: 0 (this handover will push 1 more after session-end commits)
- **122 commits ahead of `main`**, all pushed.
- **Dev (csjones.co/fynla_inter):** HTTP 200, Pest 2,836/1, all 4 country packs discovered. CLI + HTTP both verified.
- **Production (fynla.org):** untouched. Frozen per `feedback_prod_deploy_freeze.md` for ~2 months.
- **Nothing to deploy** — session-4 only changed test code + docs + phpunit.xml (test-env config). No backend code changes. No frontend changes. No migrations. Server-side state already updated via the rsyncs done during G-1-a prerequisites.

## Memory files this session

None new. MEMORY.md index unchanged from session-3.
