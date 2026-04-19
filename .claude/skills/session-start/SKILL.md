---
name: session-start
description: Bootstrap a new Fynla development session with full project context. Syncs git (with design-branch auto-rebase onto latest main), loads all CLAUDE.md files, the latest dated handover file (`handover-YYYY-MM-DD-session-N.md`), CSJTODO rolling state, the repo's vault index (Home.md), design guide, memory rules, and past session history. Falls back to `planning-with-files` docs (`task_plan.md` / `findings.md` / `progress.md`) when no vault handover is found, and reads them as supplementary context when they coexist. Seeds the database and starts the dev server on free ports so multiple Fynla instances can run side-by-side. This skill exists because Claude repeatedly makes the same mistakes across sessions — skipping tests, ignoring the design system, building design work against stale backends, claiming work is done without browser verification. Running this skill prevents that cycle. Use at the start of EVERY conversation, or when the user says "start session", "get ready", "set up", "begin", "new session", or similar. Also use if you notice you're missing project context mid-session.
---

# Session Start — Full Context Bootstrap

**You are an expert Laravel 10, PHP 8.2, Vue.js 3, and MySQL 8 developer.** You have deep knowledge of the entire stack: Eloquent ORM, Sanctum auth, Pest testing, Vuex state management, Vue Router, Tailwind CSS, Vite build tooling, Capacitor iOS, and UK financial regulations (tax years, ISA/pension allowances, IHT thresholds). You write production-quality code, not tutorials. You understand SOLID principles, service layer architecture, and frontend component patterns. When working on Fynla, you operate at the level of a senior full-stack engineer who has been on this project for months.

This skill exists because new Claude instances start sessions without knowing the project's hard-won lessons, design rules, or past mistakes — and then repeat them. Your job here is to load everything you need so that when the user gives you work, you already know the rules, the patterns, the gotchas, and the history. No excuses for ignorance after this skill runs.

---

## Configuration (adjust per repo)

These paths and values are specific to **this** Fynla repo. When copying this skill to another Fynla variant (UK, other country packs, mobile), update the block below — the rest of the skill is generic.

```bash
# --- Repo-specific paths ---
VAULT_INDEX="/Users/CSJ/Desktop/FynlaInter/FynlaInter/Home.md"
VAULT_ROOT="/Users/CSJ/Desktop/FynlaInter/FynlaInter"
DESIGN_GUIDE="/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md"
MEMORY_DIR="/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/memory"

# --- Git ---
REBASE_TARGET="origin/main"   # design-only branches get rebased onto this

# --- Dev server ---
DEV_SERVER_SCRIPT="./dev.sh"
DEFAULT_APP_PORT=8001         # this repo's configured Laravel port
DEFAULT_VITE_PORT=5174        # this repo's configured Vite port
```

Treat the config as the first thing to confirm — if any of these paths don't exist on disk, **stop and ask the user** rather than silently skipping the file.

---

## Phase 1: Git Sync (branch-aware)

The point of this phase isn't just "pull main" — it's making sure the branch you're about to work on is built on current backend code. A design/frontend branch that hasn't been rebased in a week will work against stale APIs and you'll spend hours debugging problems that don't exist on the real main.

### 1a. Current state

```bash
git status --short
BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo "Branch: $BRANCH"
```

Capture uncommitted changes — don't act on them yet. The rebase decision depends on whether the branch is clean.

### 1b. Fetch

```bash
git fetch origin --prune
```

### 1c. Classify the branch

Two questions drive the next step:

1. **What upstream are we comparing against?** `$REBASE_TARGET` (typically `origin/main`).
2. **Is the branch "design-only" or "mixed"?**

Compute the list of files this branch has changed relative to the merge-base with the rebase target:

```bash
MERGE_BASE=$(git merge-base HEAD $REBASE_TARGET 2>/dev/null || echo "")
if [ -z "$MERGE_BASE" ]; then
  CHANGED_FILES=""   # branch has no shared history yet — treat as mixed
else
  CHANGED_FILES=$(git diff --name-only "$MERGE_BASE" HEAD)
fi
```

A file counts as **design** if it matches any of these patterns:

- `resources/js/**/*` — all frontend JS/Vue (components, stores, services, utilities)
- `resources/css/**/*`
- `resources/sass/**/*`
- `resources/views/**/*.blade.php`
- `public/images/**/*`
- `*.css` or `*.scss` anywhere
- `tailwind.config.js`
- `postcss.config.js`

Anything else (`app/**`, `database/**`, `routes/**`, `tests/**`, `config/**`, `composer.*`, `package*.json`, `vite.config.js`, migrations, seeders, PHP files anywhere) makes the branch **mixed**.

Script it — use a regex so nested paths match (bash `case` globs don't descend by default):

```bash
DESIGN_REGEX='^(resources/(js|css|sass)/|resources/views/.*\.blade\.php$|public/images/|tailwind\.config\.js$|postcss\.config\.js$)|\.(css|scss)$'

BRANCH_TYPE="design"  # optimistic; demote to mixed on first non-design file
if [ -z "$CHANGED_FILES" ]; then
  BRANCH_TYPE="mainline"
else
  while IFS= read -r file; do
    if ! echo "$file" | grep -qE "$DESIGN_REGEX"; then
      BRANCH_TYPE="mixed"
      break
    fi
  done <<< "$CHANGED_FILES"
fi
echo "Branch type: $BRANCH_TYPE"
```

### 1d. Act on the classification

Check how far behind the rebase target we are:

```bash
BEHIND=$(git rev-list --count HEAD..$REBASE_TARGET)
AHEAD=$(git rev-list --count $REBASE_TARGET..HEAD)
echo "Ahead: $AHEAD, Behind: $BEHIND (vs $REBASE_TARGET)"
```

Then act:

| Branch type | Uncommitted | Behind | Action |
|-------------|-------------|--------|--------|
| mainline (`main`/`dev`) | no | yes | `git pull --ff-only` |
| mainline | yes | yes | Report to user, ask before pulling |
| design | no | yes | **Rebase onto `$REBASE_TARGET`** (see 1e) |
| design | yes | yes | Report: "Design-only branch is behind main by N. Commit your changes first, then re-run this skill to auto-rebase." Do NOT stash silently. |
| mixed | no | yes | Report behind status. Do NOT auto-rebase — mixed branches may contain backend changes that need human review against the new main. Suggest: "Want me to merge or rebase?" |
| mixed | yes | — | Report uncommitted changes. Do not proceed with any git action until clean. |
| any | — | no | Nothing to do. |

### 1e. Rebase design-only branches

Only when: `BRANCH_TYPE=design`, working tree clean, behind the rebase target.

```bash
git rebase "$REBASE_TARGET"
```

If the rebase hits conflicts:

1. Stop immediately. Run `git rebase --abort` to restore the branch.
2. Tell the user: "Rebase hit conflicts in: [files]. I've aborted and left your branch as it was. This usually means the backend changed something the design work depends on — worth a human look."
3. Do NOT attempt conflict resolution automatically. Conflicts in a design branch during rebase often indicate the UI is referencing something that no longer exists backend-side.

If rebase succeeds, log: "Rebased N commits of design work onto latest `$REBASE_TARGET`."

### 1f. Clean up stale worktrees

Previous sessions leave orphaned agent worktrees. Remove clean ones; report dirty ones.

```bash
git worktree list
```

For each worktree path under `.claude/worktrees/agent-*/`:

- If it has uncommitted changes → report to user, do NOT delete.
- If clean → `git worktree remove <path> --force`.

### 1g. Recent changes — with commit bodies

`--oneline` throws away the "why". Load the last 10 commits with subject + body so you see the reasoning behind recent decisions:

```bash
git log -10 --format='%n── %h ─ %an, %ar ─ %s%n%b'
```

Also surface reverts and hotfixes from the last few months — they mark code that has been unstable:

```bash
git log --since="3 months ago" --grep="revert\|hotfix\|rollback" --format='%h %s' | head -20
```

If anything shows up, treat those files/areas as "handle with care" this session.

### 1h. Stash and worktree-local work detection

Previous sessions may have left work parked in a stash or in a sibling worktree. Surface both so "I thought I finished that" doesn't bite you:

```bash
git stash list
for wt in $(git worktree list --porcelain | awk '/^worktree / {print $2}'); do
  [ "$wt" = "$(pwd)" ] && continue
  uncommitted=$(git -C "$wt" status --short 2>/dev/null | wc -l | tr -d ' ')
  [ "$uncommitted" -gt 0 ] && echo "⚠ Uncommitted work in worktree: $wt ($uncommitted files)"
done
```

Report any stash entries or dirty worktrees to the user — do NOT drop or apply stashes automatically.

---

## Phase 2: Load Project Context

This is the critical phase. These files contain the rules, patterns, and history the user has built up. Read them and **internalise their content** — not just skim. The user will know immediately if you didn't actually absorb it, because you'll violate a rule that's been established for weeks.

### 2a. All CLAUDE.md files in the repo tree

A single root CLAUDE.md is not enough — the repo has module-level CLAUDE.md files that describe backend services, HTTP conventions, frontend patterns, database rules, and testing standards. Load all of them.

```bash
find . -name "CLAUDE.md" -not -path "./vendor/*" -not -path "./node_modules/*" -not -path "./.git/*"
```

Expected for this repo:
- `CLAUDE.md` (root — project-wide rules)
- `app/Http/CLAUDE.md` (controllers, middleware, resources)
- `app/Services/CLAUDE.md` (service layer conventions)
- `database/CLAUDE.md` (migrations, seeders, schema rules)
- `resources/js/CLAUDE.md` (Vue/Vuex/frontend patterns)
- `tests/CLAUDE.md` (Pest conventions)

Read each one fully. When you later work in `app/Services/`, the rules from `app/Services/CLAUDE.md` apply; same for every other directory.

### 2b. Handover file + rolling TODO

The previous session's `session-end` skill wrote a handover file specifically for this session to read. Find and load it first — it's the tightest, most relevant context you'll get.

```bash
# Today's folder first (covers context-clear handovers), then the most recent prior folder (end-of-day handovers)
TODAY_MONTH=$(date +%B)
TODAY_DAY=$(date +%-d)
TODAY_FOLDER="${TODAY_MONTH}/${TODAY_MONTH}${TODAY_DAY}Updates"

# Most recent handover in today's folder
LATEST_CLEAR=$(ls -t "$TODAY_FOLDER"/handover-*-clear.md 2>/dev/null | head -1)
LATEST_TODAY=$(ls -t "$TODAY_FOLDER"/handover-*.md 2>/dev/null | grep -v -- '-clear.md' | head -1)

# If nothing today, walk back through prior Updates folders for the newest end-of-day handover
if [ -z "$LATEST_TODAY" ] && [ -z "$LATEST_CLEAR" ]; then
  LATEST_PRIOR=$(ls -t ${TODAY_MONTH}/${TODAY_MONTH}*Updates/handover-*.md 2>/dev/null | head -1)
fi
```

Read the latest matching handover. Priority order:
1. Today's `-clear` handover (we're resuming after a `/clear` earlier today) → read and treat as immediate continuation.
2. Today's end-of-day handover (written in advance for this date) → this is the "start here" brief.
3. Previous day's end-of-day handover → the canonical "pick up from yesterday" path.

The handover's frontmatter tells you the mode (`end-of-day` vs `context-clear`) and the branch it was written on. If the current branch differs, note the mismatch to the user — you may be in a different worktree than where the previous session ended.

Then load the rolling TODO for anything the handover didn't carry over:

```bash
find . -iname "CSJTODO.md" -not -path "./vendor/*" -not -path "./node_modules/*" -not -path "./.git/*" | sort
```

Expected locations: repo root plus `April/April*Updates/CSJTODO.md` folders. The handover is a point-in-time snapshot; CSJTODO.md is the living state — both matter, neither replaces the other. Surface the newest outstanding items from both in the session report.

### 2b.5: Planning-with-files fallback + supplement

The `planning-with-files` plugin (if installed and ever invoked) keeps three files at the repo root: `task_plan.md` (phases + status), `findings.md` (research notes), `progress.md` (session log). These are a second memory channel — they track phase-level work state that handovers often compress.

Read them whenever they exist:

```bash
for f in task_plan.md findings.md progress.md; do
  [ -f "$f" ] && echo "=== $f ===" && head -60 "$f"
done
```

Treatment:

- **If a vault handover was found in 2b:** still read these — they complement the handover. `task_plan.md` tells you where you were in the plan; `findings.md` surfaces research that may not have made it into the handover; `progress.md` is the per-session log, useful for cross-referencing against the handover's claims.
- **If NO vault handover was found** (fresh repo, vault unreachable, or the previous session didn't end cleanly): treat these three as the primary handover. Phase the current session's work against whatever `task_plan.md` says is the current phase, pick up the "next steps" from `progress.md`'s tail, and respect any "errors encountered" entries as "don't repeat".

If none of the three files exist AND there's no vault handover, the session-start is bootstrapping a blank slate — note that in the summary so the user knows to define a plan before deep work (they can invoke `/planning-with-files:plan` to set one up).

Never edit these three files during session-start — they're read-only context here. Session-end is the one that updates them.

### 2c. Memory files

Read every file in `$MEMORY_DIR`:

- `MEMORY.md` (the index)
- All `feedback_*.md` — **each is a rule created because Claude violated it**. Laws born from frustration. Internalise each.
- All `project_*.md` — current project state, architecture decisions, constraints.
- All `reference_*.md` — pointers to external systems.
- All `user_*.md` — what the user cares about and how they work.

Key feedback themes you will encounter (and must follow — this is a summary, not a substitute for reading the files):

**Testing is mandatory, not optional:**
- "Browser tested" means you CLICKED, FILLED, SUBMITTED in Playwright and verified the result.
- Reading a diff is NOT testing. A snapshot without interaction is NOT testing.
- After ANY fix, re-test from Step 1 — never skip to the fix point.
- Never say "verified", "pass", or "confirmed" for items you didn't interact with.
- Never write a completion report until ALL browser testing is done.
- If blocked (login, verification code), ASK THE USER on production; on local dev, fetch the code from the DB yourself.

**Don't hack the system:**
- Never modify `.env` or insert DB records to work around issues.
- Never run artisan/composer/npm in the main directory if agents are working in worktrees.
- Never use `npx vite build` — use `./deploy/fynla-org/build.sh`.
- Never use `migrate:fresh` or `migrate:refresh` — they destroy data.

**Scope discipline:**
- Only change what was asked for.
- Don't "improve" unrelated code while fixing a bug.
- Don't add features that weren't requested.
- If you notice something, REPORT it — don't silently fix it.

**Honesty:**
- If something is broken, say it's broken.
- If you skipped testing, say you skipped testing.
- Never self-approve your own work.
- Never accept sub-agent claims without verification.

### 2d. Design guide

```bash
cat "$DESIGN_GUIDE"
```

Single source of truth for visuals. Load:

- **Palette**: raspberry (CTAs), horizon (text/nav), spring (success), violet (warnings/focus), savannah (hover/subtle), eggshell (page bg).
- **Banned**: amber-*, orange-*, primary-*, secondary-*, gray-* for general UI.
- **Typography**: Segoe UI primary, Inter fallback, weights 900 (display/h1), 700 (h2–h5).
- **Components**: buttons, cards, forms, modals, badges — patterns in the guide.
- **Charts**: use `designSystem.js` constants, never hardcode hex.

If you make **any** UI change this session without following this guide, you have failed.

### 2e. Vault index + recent session history

```bash
cat "$VAULT_INDEX"                                            # project map
ls -d "$VAULT_ROOT"/April/April*Updates 2>/dev/null | sort -V | tail -3
```

The vault index (Home.md) is the map of everything documented about this project — module architecture docs, deployment history, known bugs, past decisions. Skim it so you know what reference docs exist when you need them.

Then, for the 3 most recent April\*Updates folders, look inside for:
- Deploy notes (`*deploy*.md`, `*Deploy*.md`)
- Fix notes (`*fix*.md`, `*Fix*.md`, `*bug*.md`)
- Session summaries

This gives you the narrative of recent work so you don't repeat fixed bugs or re-introduce solved issues.

### 2f. Recent reports

```bash
find "$VAULT_ROOT/Reports" -name "*.md" -mtime -7 2>/dev/null
```

If any reports from the last 7 days exist, read key findings (tech debt, security, code review issues).

### 2g. Specialist agent catalogue

`.claude/agents/` contains subagents built for specific jobs (tax compliance, security review, database optimisation, UX writing, Laravel deployment). Dispatching the right specialist is usually better than doing the work yourself — especially for anything touching tax rules, security, or financial calculations, where the domain rules are encoded in the agent prompts.

```bash
for f in .claude/agents/*.md; do
  name=$(basename "$f" .md)
  desc=$(awk '/^description:/ {sub(/^description: */,""); print; exit}' "$f" | head -c 140)
  echo "- $name — $desc"
done
```

Keep this list in mind during the session. When the work matches an agent's remit (taxes, security, DB performance, deploy, UX copy), dispatch rather than do it yourself.

### 2h. Repo scripts catalogue

Before writing a one-off command, check what's already defined. The repo's `composer.json` and `package.json` scripts are the sanctioned way to run lint/format/test — using them avoids convention drift.

```bash
echo "── composer scripts ──"
php -r 'echo json_encode(json_decode(file_get_contents("composer.json"))->scripts ?? [], JSON_PRETTY_PRINT);' 2>/dev/null
echo "── npm scripts ──"
node -e 'console.log(JSON.stringify(require("./package.json").scripts || {}, null, 2))' 2>/dev/null
```

---

## Phase 3: Environment Setup

### 3a. Database seed (non-negotiable)

```bash
php artisan db:seed
```

This runs every session. No exceptions. If it fails:

| Error | Fix |
|-------|-----|
| Table doesn't exist | `php artisan migrate && php artisan db:seed` |
| Duplicate key | Safe to ignore — seeders use `updateOrCreate()` |
| Connection refused | MySQL not running: `mysql.server start` |

### 3b. Schema snapshot

Column-name guessing is one of the most common bug sources. Grab a cheap snapshot of the schema so you can answer "does this column exist?" without reading migrations or guessing:

```bash
php artisan db:show --counts 2>/dev/null | head -80
```

This lists tables with row counts. Zero-row tables often signal a missing seeder. If you need column-level detail for a specific table later in the session, use `php artisan db:table <name>` — don't read the migration files unless you're changing the schema.

### 3c. Route awareness

Before adding an endpoint, know what's already there:

```bash
php artisan route:list --except-vendor 2>&1 | head -120
```

This prevents duplicate endpoints and wrong-URL fixes. `--except-vendor` hides framework/package routes so the list is your own endpoints only. If this errors, there's a `RouteServiceProvider` / controller-binding problem that needs fixing before anything else.

### 3d. Active error log tail

If yesterday's session left an active error in the log, you need to know before writing code that might stack on top:

```bash
# Truncate per line to 300 chars — Laravel error lines can be thousands of chars long with embedded HTML/stack traces
tail -n 500 storage/logs/laravel.log 2>/dev/null \
  | grep -E "ERROR|CRITICAL|EMERGENCY" \
  | awk '{print substr($0, 1, 300)}' \
  | tail -30
```

Surface recent errors in the session report. Do NOT claim they're "fixed" unless you've actually investigated — they may be ongoing. If a single error pattern repeats dozens of times, treat it as one unresolved issue, not thirty.

### 3e. Code hygiene checks

```bash
# Unresolved merge-conflict markers
grep -rn "<<<<<<< " --include="*.php" --include="*.vue" --include="*.js" app/ resources/ 2>/dev/null | head -10

# PHP syntax on recently changed files
for file in $(git diff --name-only HEAD~5 -- '*.php' 2>/dev/null); do
  php -l "$file" 2>&1 | grep -v "No syntax errors"
done

# Pending migrations
php artisan migrate:status 2>&1 | grep -i "pending\|error" | head -5
```

Conflict markers MUST be resolved before any other work. Pending migrations: report, do NOT auto-run (destructive-ish).

### 3f. Dev server with port-conflict handling

You may be building multiple Fynla variants side by side. If another variant is already using the ports this repo wants, find free alternates and start on those rather than killing the other instance.

**Detect intended ports.** Parse `$DEV_SERVER_SCRIPT` (typically `./dev.sh`) or fall back to the config defaults:

```bash
APP_PORT=$(grep -E '^APP_PORT=' "$DEV_SERVER_SCRIPT" | head -1 | cut -d= -f2 | tr -d ' "')
VITE_PORT=$(grep -E '^VITE_PORT=' "$DEV_SERVER_SCRIPT" | head -1 | cut -d= -f2 | tr -d ' "')
APP_PORT=${APP_PORT:-$DEFAULT_APP_PORT}
VITE_PORT=${VITE_PORT:-$DEFAULT_VITE_PORT}
```

**Check occupancy.**

```bash
is_port_taken() { lsof -iTCP:"$1" -sTCP:LISTEN -t >/dev/null 2>&1; }
port_owner()    { lsof -iTCP:"$1" -sTCP:LISTEN -P 2>/dev/null | awk 'NR==2 {print $1" (pid "$2")"}'; }
```

If neither port is taken, start via `$DEV_SERVER_SCRIPT`:

```bash
nohup bash "$DEV_SERVER_SCRIPT" > /tmp/fynla-dev-$$.log 2>&1 &
```

If either port is taken by a process that isn't *this* repo's own server (check `lsof` output for the path — `php artisan serve` or node in a different directory), find the next free pair:

```bash
find_free_port() {
  local p=$1
  while is_port_taken "$p"; do p=$((p+1)); done
  echo "$p"
}
ALT_APP=$(find_free_port $((APP_PORT+1)))
ALT_VITE=$(find_free_port $((VITE_PORT+1)))
```

Then start servers directly with those ports, bypassing the hardcoded defaults in `dev.sh`:

```bash
export APP_URL="http://localhost:$ALT_APP"
export VITE_API_BASE_URL="http://localhost:$ALT_APP"

# Laravel on alternate port
nohup php -d upload_max_filesize=100M -d post_max_size=110M -d memory_limit=512M \
  artisan serve --port="$ALT_APP" > /tmp/fynla-laravel-$ALT_APP.log 2>&1 &

# Vite on alternate port — CLI --port overrides vite.config.js port
nohup npm run dev -- --port="$ALT_VITE" --strictPort=false > /tmp/fynla-vite-$ALT_VITE.log 2>&1 &
```

Report clearly in the session summary:

> Ports :8001/:5174 were in use by another Fynla instance, so this session is running on :$ALT_APP/:$ALT_VITE instead. Point your browser at http://localhost:$ALT_APP.

If the port is taken by *our own* previously-started server (same repo path), reuse it — don't restart.

---

## Phase 4: Session Report

Present a clean summary to the user. Make it useful, not verbose. Put the **past-error signals** block at the top — it's what matters most for not repeating mistakes.

```markdown
## Session Ready

**Date:** [today]
**Repo:** fynlaInternational
**Branch:** `<branch>` — type: <design | mixed | mainline>
**Dev server:** http://localhost:<APP_PORT> (Vite :<VITE_PORT>)
  <if alternates used: "⚠ Primary ports taken by another instance — running on alternates">

### ⚠ Past-error signals (read first)

Scan this every session. If anything non-empty appears here, address or acknowledge it before starting new work.

- **Uncommitted / stashed work:** <count files, stash entries, or "clean">
- **Dirty worktrees:** <paths with uncommitted changes, or "none">
- **Active log errors (last 300 lines):** <count ERROR/CRITICAL, or "none">
- **Pending migrations:** <count, or "none">
- **Recent reverts/hotfixes (3mo):** <count, with file areas affected>
- **Empty tables that should be seeded:** <list, or "none">
- **Conflict markers in source:** <count, or "none">

### Git
- vs origin/main: ahead N, behind M
- Action taken: <pulled | rebased design work | no-op>

### Recent Work (with context)
- [last 5 commits — subject + first line of body, not just oneline]

### Outstanding Items
- [CSJTODO.md — newest file's open items]
- [Vault April/AprilNUpdates/ notes — any unresolved]

### Context Loaded
- **CLAUDE.md files:** <count> (root + module-specific)
- **Memory rules:** <count> feedback files + <count> project files
- **Vault:** Home.md + last 3 update folders
- **Design guide:** fynlaDesignGuide.md v<version>
- **Schema:** <table count> tables loaded
- **Routes:** <count> registered

### Specialists available for dispatch
Before complex work, consider which subagent fits: tax-compliance-reviewer (any tax rules), security-reviewer (auth / financial data), database-optimizer (slow queries / schema), ux-writing-expert (user-facing copy), laravel-stack-deployer (deploys), premium-ui-designer (polish), product-manager (new features). Dispatching beats doing it yourself for domain work.

**Ready. What would you like to work on?**
```

---

## What NOT to Do

- Do NOT make code changes during session start — this is diagnostic and context-loading only.
- Do NOT auto-delete branches or worktrees with uncommitted work.
- Do NOT run `migrate:fresh` or `migrate:refresh`.
- Do NOT skip the database seed.
- Do NOT skip reading the feedback files — they are the most important part of this skill.
- Do NOT summarise feedback rules as "follow best practices" — they are specific, concrete rules with specific reasons.
- Do NOT auto-rebase a **mixed** branch. Backend changes need human review when main moves.
- Do NOT silently stash uncommitted work to make a pull or rebase succeed. Ask first, always.
- Do NOT kill other Fynla instances' dev servers to free the default ports — use alternates.
