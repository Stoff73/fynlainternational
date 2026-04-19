---
name: deploy-notes
description: Auto-generate deployment documentation from `git diff` for Fynla's SiteGround environments (prod `fynla.org` and dev `csjones.co/fynla`). Categorises changed files, decides whether a Vite build is needed, maps local paths to server paths, generates the SSH command sequence (cache clears, migrations, seeders, composer install), flags high-risk changes (middleware, observers, routes, PreviewWriteInterceptor), and saves a dated deploy note to both the repo (`<Month>/<Month>DDUpdates/deploy-<date>.md`) and the FynlaInter vault. Use whenever the user says "deploy notes", "deployment checklist", "generate deploy", "what needs deploying", "deploy this", "deploy to dev", "deploy to prod", "deployment docs", "ship this to production", or after completing a feature that needs to reach a live environment. Covers both the pre-deploy "what am I shipping" checklist and the post-session "record of what shipped" note in one skill.
disable-model-invocation: true
---

# Deploy Notes

Generate deployment documentation for Fynla's two SiteGround environments:

| Env | URL | Branch | Server path | SSH alias |
|-----|-----|--------|-------------|-----------|
| **Production** | `https://fynla.org` | `main` | `~/www/fynla.org/public_html/` | `ssh.fynla.org:18765` as `u2783-hrf1k8bpfg02` |
| **Dev / staging** | `https://csjones.co/fynla` | `dev` (when it exists) or `main` | `~/www/csjones.co/public_html/fynla/` | `ssh.csjones.co:18765` as `u163-ptanegf9edny` |

If the user doesn't specify which environment, ask once. Default to prod if they say "deploy" without qualification AND the branch is `main`.

---

## Step 1: Determine the comparison range

Find what has changed since the last deploy or merge to main:

```bash
# On a feature branch → compare against main
git diff --name-only origin/main...HEAD 2>/dev/null

# On main → compare against the previous commit or last deploy tag
git diff --name-only HEAD~1...HEAD 2>/dev/null

# User-specified ranges ("since last deploy", "last 3 commits") win
```

If there are no changed files, report "nothing to deploy" and stop.

---

## Step 2: Categorise changed files

| Category | Pattern | Deploy action |
|----------|---------|---------------|
| PHP backend | `app/**/*.php`, `config/*.php`, `routes/*.php` | Upload via SiteGround File Manager |
| Frontend | `resources/js/**`, `resources/css/**`, `resources/views/**/*.blade.php` | Build + upload `public/build/` |
| Migrations | `database/migrations/*.php` | Upload + SSH `php artisan migrate --force` |
| Seeders | `database/seeders/*.php` | Upload + SSH `php artisan db:seed --class=XSeeder --force` |
| Deploy config | `deploy/**`, `.htaccess` | Upload |
| Composer | `composer.json`, `composer.lock` | SSH `composer install --no-dev --optimize-autoloader` |
| Docs only | `*.md`, `docs/**`, `.claude/**`, `<Month>/<Month>*Updates/**` | No deploy |
| Tests only | `tests/**` | No deploy |
| Vendored | `vendor/**`, `node_modules/**` | Never upload — managed server-side |

If **all** changes are docs-only or tests-only, report "no deployment needed" and stop.

---

## Step 3: Decide whether a Vite build is required

If any file matches `resources/js/**`, `resources/css/**`, or `resources/views/**/*.blade.php`:

| Target env | Build command | Reason |
|------------|---------------|--------|
| `fynla.org` | `./deploy/fynla-org/build.sh` | Vite `base=/build/`, `RewriteBase=/` |
| `csjones.co/fynla` | `./deploy/csjones-fynla/build.sh` | Vite `base=/fynla/build/`, `RewriteBase=/fynla/`, sandbox flags |

**Never** suggest `npx vite build`, `npm run build`, or raw Vite commands — they bypass the per-environment config and produce the wrong router base. Also **never** build for one env and upload to the other — the SPA will render a blank page with no useful error.

---

## Step 4: Generate the upload list

Base server path per env:

- Prod: `~/www/fynla.org/public_html/`
- Dev: `~/www/csjones.co/public_html/fynla/`

Map each file's local path to the server path. Group by directory so the user can batch-upload. Example (prod):

```
app/Services/Estate/IHTCalculator.php
  → ~/www/fynla.org/public_html/app/Services/Estate/IHTCalculator.php

resources/js/components/Investment/...  (multiple files)
  → rebuild locally, upload public/build/ entirely
```

If frontend was built, the upload target is the whole `public/build/` directory, not individual files inside it — Vite's manifest depends on the full set.

---

## Step 5: Generate SSH commands

Always include the connection and cache clear:

```bash
# Prod
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Dev
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla

# Both — always
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

Conditional commands:

- Migrations present → `php artisan migrate --force`
- Seeders present → list each explicitly: `php artisan db:seed --class=<Seeder> --force`
- Composer changed → `composer install --no-dev --optimize-autoloader`

---

## Step 6: Flag warnings

| Warning | Trigger |
|---------|---------|
| Migration requires SSH | `database/migrations/*.php` changed |
| Env/config drift risk | `config/*.php` changed (check if `.env` on server needs updating) |
| New composer dependencies | `composer.json` or `composer.lock` changed |
| Route cache invalidated | `routes/*.php` changed (cache clear is mandatory) |
| Middleware changed | `app/Http/Middleware/*.php` changed |
| PreviewWriteInterceptor | `PreviewWriteInterceptor.php` changed — verify `EXCLUDED_ROUTES` covers any new auth routes (per CLAUDE.md rule 8) |
| Seeder run required | `database/seeders/*.php` changed |
| Observer side-effects | `app/Observers/*.php` changed (risk-recalc / goal-tracking / Monte Carlo observers run on every save — verify intended) |
| Design-system violation | Grep built artefacts for banned classes (`amber-`, `orange-`, `primary-`, `secondary-`, `gray-`) before uploading — they should have been blocked pre-build but verify |

---

## Step 7: Generate and save the deploy note

Compute date/folder dynamically:

```bash
MONTH=$(date +%B)
DAY=$(date +%-d)
DATE_FULL=$(date +%Y-%m-%d)
FOLDER="${MONTH}/${MONTH}${DAY}Updates"
FNAME="deploy-${DATE_FULL}.md"
mkdir -p "$FOLDER"
mkdir -p "/Users/CSJ/Desktop/FynlaInter/FynlaInter/$FOLDER"
```

Write to both locations:

- Repo: `<Month>/<Month>DDUpdates/deploy-YYYY-MM-DD.md`
- Vault: `/Users/CSJ/Desktop/FynlaInter/FynlaInter/<Month>/<Month>DDUpdates/deploy-YYYY-MM-DD.md`

Output template:

```markdown
---
type: deploy
env: <prod | dev>
date: <YYYY-MM-DD>
branch: <current branch>
commits: <count since last deploy>
---

# Deployment Notes — <DD Month YYYY> — <prod|dev>

Back to [[Home]] | [[<Month>/<Month> Index|<Month> Index]]

## Summary
- **Target:** <fynla.org | csjones.co/fynla>
- **Files changed:** <N>
- **Frontend build:** Yes / No
- **Migrations:** <count>
- **Seeders:** <list or None>
- **Composer changes:** Yes / No

## PHP files to upload

### <directory group>
- [ ] `path/to/file.php` → `<server-base>/path/to/file.php`

## Frontend build
- [ ] Run `<build script>`
- [ ] Upload `public/build/` → `<server-base>/public/build/`

## Migrations
- [ ] `database/migrations/xxxx_migration.php`

## Seeders
- [ ] `php artisan db:seed --class=<Seeder> --force`

## Post-upload SSH

```bash
<ssh command for target env>
cd <server-base>
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
<additional commands if needed>
```

## Warnings
- <from Step 6, or "None">

## Verification
- [ ] Visit `<env URL>` — no blank page
- [ ] Load a preview persona — renders correctly
- [ ] Exercise the changed feature in the browser
- [ ] Tail `storage/logs/laravel.log` for 5 minutes after upload — no new ERRORs
```

Use `> [!warning]`, `> [!note]`, `> [!tip]` callouts for anything that needs emphasis, not emoji-quotes.

---

## Step 8: Display summary

After saving, give the user a scannable summary:

```
Deploy notes saved:
  - <Month>/<Month>DDUpdates/deploy-<date>.md
  - FynlaInter vault mirror

Target: <env>
Summary: <N> PHP files · <build/no-build> · <N> migrations · <N> seeders
Warnings: <list or none>

Next: upload the listed files via SiteGround File Manager, then run the SSH block.
```

---

## Rules

- Deploy notes come from `git diff`, never from memory — Claude will miss files otherwise.
- Never build for one environment and upload to the other — the router base paths don't match and the SPA silently breaks.
- Never include `vendor/`, `node_modules/`, `*.md`, `docs/`, `.claude/`, or the `<Month>Updates/` folders in the upload list.
- Never suggest `npx vite build` / `npm run build`.
- Always include the verification checklist — the last line of defence before user-visible regressions.
- `.env` changes on the server are manual — flag when `config/*.php` drifts so the user knows to check.
