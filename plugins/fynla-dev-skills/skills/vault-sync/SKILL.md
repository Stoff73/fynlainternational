---
name: vault-sync
description: Sync project documentation and session transcripts to the FynlaInter Obsidian vault. Captures the full AI conversation (user prompts + Claude responses), updates git history and indexes, detects orphaned notes and links them from the appropriate index, then audits the vault against Obsidian formatting standards (frontmatter, wikilinks, callouts, file-name conventions, broken links, tag vocabulary) so the graph stays connected and navigable. Use when the user says "sync vault", "update vault", "sync docs", "audit vault", "find vault orphans", "clean up vault", or at session end.
disable-model-invocation: true
---

# Vault Sync — Full Documentation & Session Capture

Sync all project documentation to the FynlaInter Obsidian vault, capture the current session's conversation, then verify formatting and connections.

**Vault location:** `/Users/CSJ/Desktop/FynlaInter/FynlaInter/` (NOT a git repo — write files directly)
**Obsidian config:** `/Users/CSJ/Desktop/FynlaInter/FynlaInter/.obsidian/`
**Source docs:** `/Users/CSJ/Desktop/fynlaInternational/`
**Memory dir:** `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/memory/`

---

## Phase 0: Session Transcript Capture

**This is the most important phase.** Capture the full session — every user message, every Claude response, every tool use (Skill invocations, Bash commands, file edits), and every sub-agent dispatched via the Task tool — as a vault document.

Rather than reconstruct the conversation from memory (lossy, skips failed attempts, can't quote verbatim after thousands of messages), read it from the authoritative JSONL that Claude Code writes to disk. Every turn, every tool call, every sub-agent — it's all there.

### 0a: Locate the session JSONL

Claude Code stores the current session at:

```
~/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/<session-id>.jsonl
```

Sub-agents dispatched via the `Task` tool get their own JSONL at:

```
~/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/<session-id>/subagents/agent-*.jsonl
```

The session id is a UUID. You don't need to know it — the transcript builder finds the latest session automatically.

### 0b: Determine target path and session number

```bash
MONTH_NAME=$(date +%B)
TODAY=$(date +%d)
YEAR=$(date +%Y)
TODAY_FULL=$(date +%Y-%m-%d)
VAULT="/Users/CSJ/Desktop/FynlaInter/FynlaInter"
SESSION_DIR="$VAULT/Sessions/${MONTH_NAME} ${YEAR}"
mkdir -p "$SESSION_DIR"

existing=$(ls "$SESSION_DIR"/${MONTH_NAME}${TODAY}-session-*.md 2>/dev/null | wc -l | tr -d ' ')
next=$((existing + 1))
OUT="$SESSION_DIR/${MONTH_NAME}${TODAY}-session-${next}.md"
```

### 0c: Run the transcript builder

```bash
python3 .claude/skills/vault-sync/scripts/build-session-transcript.py -- --latest "$OUT"
```

The builder:

- Parses the current session JSONL.
- Emits every **user message** verbatim (blockquoted).
- Emits every **assistant message** — the text content plus a bullet list of every tool the assistant used in that turn.
- Summarises each tool use in one line: `Bash — <command>`, `Skill — <skill name>`, `Edit — <file>`, `Sub-agent (<type>) — <description>`, etc.
- For each tool result, includes a truncated result (up to ~1500 chars) inside a `<details>` block so the transcript stays scannable.
- Detects every `Task`/`Agent` invocation, lists them in a dedicated "Sub-agents dispatched" section, and writes each sub-agent's full JSONL as a linked file under `<out_dir>/subagents/agent-*.md`.
- Skips noisy tool results (e.g. `Read` contents, which are in the file anyway).

Use `--latest` unless you need a specific session; the builder finds the most recently-modified JSONL automatically. If you need a specific session by id:

```bash
python3 .claude/skills/vault-sync/scripts/build-session-transcript.py <session-id> "$OUT"
```

### 0d: Add session metadata + summary to the head

The builder produces the raw transcript but doesn't know the human-readable framing. Open the generated file and prepend a frontmatter block plus a short "Summary" section:

```markdown
---
tags:
  - session
  - ${month_name_lowercase}-${YEAR}
date: ${TODAY_FULL}
type: session
session: ${next}
source_jsonl: <session-id>.jsonl
---

# Session ${next} — ${TODAY} ${MONTH_NAME} ${YEAR}

Back to [[Home]] | [[April/April Index|April Index]]

## Summary

[2-3 sentence summary: what was accomplished, what was decided, what's next.]

## Key decisions

- [Each non-obvious decision made this session — what was chosen, what was rejected, why.]

## Files changed

- [From `git log --since="midnight" --name-only` — list each modified path with a one-line note.]

## Status at session end

- **Tests:** <pass/fail>
- **Branch:** <current>
- **Deploy:** <state>
- **Next steps:** <what the next session should start with>

---

## Full transcript

[The transcript builder's output follows below — every user message, every Claude response,
every tool use, every sub-agent dispatched.]
```

Paste the builder's output under that last heading. Do not edit the builder's output — it's the faithful record.

### 0e: Why it matters

The transcript captures decisions AND the path that led to them. Failed attempts, rejected approaches, and mid-session course-corrections often contain the most valuable context for a future session. Summaries lose that; the raw transcript preserves it.

The JSONL is ~1–6 MB; the markdown transcript will land somewhere between 50 KB and 500 KB depending on session length — readable in Obsidian, easily searchable, and small enough to embed in future session-start reads without blowing context.

If the session had sub-agents, look at the linked `subagents/agent-*.md` files — each one holds the sub-agent's own prompt, tool uses, and response. Tax-compliance reviews, security audits, doc research — all preserved.

---

## Phase 1: Codebase Metrics

Count current codebase metrics and compare with CLAUDE.md:

```bash
echo "Vue Components: $(find resources/js/components resources/js/views resources/js/mobile -name '*.vue' 2>/dev/null | wc -l | tr -d ' ')"
echo "PHP Services: $(find app/Services -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Controllers: $(find app/Http/Controllers -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Models: $(find app/Models -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Vuex Stores: $(find resources/js/store/modules -name '*.js' 2>/dev/null | wc -l | tr -d ' ')"
echo "Agents: $(find app/Agents -name '*Agent.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Core Contracts: $(find core/app/Core/Contracts -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Core Tests: $(find tests/Unit/Core -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Country Packs: $(ls -d packs/country-* 2>/dev/null | wc -l | tr -d ' ')"
```

If any counts changed vs CLAUDE.md metrics table, update them.

---

## Phase 2: Sync Update Notes to Vault

### Dynamic Variables

```bash
MONTH_NAME=$(date +%B)
MONTH_SHORT=$(date +%b)
YEAR=$(date +%Y)
TODAY=$(date +%d)
VAULT="/Users/CSJ/Desktop/FynlaInter/FynlaInter"
SRC="/Users/CSJ/Desktop/fynlaInternational"
```

### 2a: Identify and sync update notes

Only sync files from `April/April[DD]Updates/` folders that are relevant to the international project (handovers, plans, session notes). Skip files that were copied from the main fynla repo and aren't international-specific.

```bash
# Sync only April16+ updates and Plans
for dir in "$SRC"/April/April{16,17,18,19,20,21,22,23,24,25,26,27,28,29,30}Updates; do
  [ -d "$dir" ] || continue
  folder=$(basename "$dir")
  vault_dir="$VAULT/April/$folder"
  mkdir -p "$vault_dir"

  for file in "$dir"/*.md; do
    [ -f "$file" ] || continue
    filename=$(basename "$file")
    if [ ! -f "$vault_dir/$filename" ] || ! diff -q "$file" "$vault_dir/$filename" > /dev/null 2>&1; then
      cp "$file" "$vault_dir/$filename"
      echo "SYNCED: $folder/$filename"
    fi
  done
done

# Always sync Plans
mkdir -p "$VAULT/Plans"
for file in "$SRC"/Plans/*.md; do
  [ -f "$file" ] || continue
  filename=$(basename "$file")
  if [ ! -f "$VAULT/Plans/$filename" ] || ! diff -q "$file" "$VAULT/Plans/$filename" > /dev/null 2>&1; then
    cp "$file" "$VAULT/Plans/$filename"
    echo "SYNCED: Plans/$filename"
  fi
done
```

---

## Phase 3: Update Git History

### 3a: Create/update today's daily commit log

```bash
TODAY=$(date +%d)
TODAY_FULL=$(date +%Y-%m-%d)
COMMITS=$(git log --oneline --since="$(date +%Y-%m-%d) 00:00:00" --until="$(date -v+1d +%Y-%m-%d) 00:00:00" 2>/dev/null)
COMMIT_COUNT=$(echo "$COMMITS" | grep -c '^' 2>/dev/null || echo 0)
```

If there are no commits, skip git history entirely. This is a new repo and may not have commits yet.

If there ARE commits, create `/Users/CSJ/Desktop/FynlaInter/FynlaInter/Git History/${MONTH_SHORT}${YEAR}/${MONTH_SHORT}${TODAY}.md`:

```markdown
---
tags:
  - git-history
date: [TODAY_FULL]
commits: [COMMIT_COUNT]
---

# Commits — [DAY] ${MONTH_NAME} ${YEAR}

Back to [[Home]]

| Time | Hash | Type | Message |
|------|------|------|---------|
| HH:MM | `abcd1234` | + | feat: description |
```

**Type codes:** `+` feat, `~` fix, `D` docs, `^` refactor, `T` test, `C` chore, `S` style, `P` perf, `-` other

---

## Phase 4: Update ${MONTH_NAME} Index

Read `/Users/CSJ/Desktop/FynlaInter/FynlaInter/April/April Index.md` (or create it if it doesn't exist).

### 4a: Add/update session entry for today

Under `## Sessions`, add or update the entry for today:

```markdown
### ${MONTH_NAME}${TODAY} ([N] sessions — [N] commits)

Session [N]: [Brief summary]
```

### 4b: Add update note links

Under `## Update Notes`, add/update for today's folder:

```markdown
### ${MONTH_NAME}${TODAY}Updates

- [[filename]] — Brief description
```

### 4c: Add session transcript link

Link to the session file created in Phase 0:

```markdown
### Sessions

- [[Sessions/${MONTH_NAME} ${YEAR}/${MONTH_NAME}${TODAY}-session-${next}|Session ${next}]] — Brief summary
```

---

## Phase 5: Update Home.md

Read `/Users/CSJ/Desktop/FynlaInter/FynlaInter/Home.md` and update:

1. **Current Status table** — phase/workstream progress
2. **Sessions section** — add today's session summary
3. **Update Notes section** — link new docs

Only update what actually changed.

---

## Phase 6: Orphan Detection + Linking

A file with no incoming wikilinks is effectively invisible in Obsidian — it exists on disk but can't be reached by clicking through the graph, and it won't show up in backlink panes. Every sync pass should catch orphans and wire them into the right index so the graph stays fully connected.

### 6a: Run the orphan scanner

```bash
bash .claude/skills/vault-sync/scripts/find-orphans.sh "$VAULT"
```

The script prints one orphan path per line (relative to the vault root, no `.md` extension). `Home.md` is always excluded — it's the root index.

If the list is empty, log "no orphans" and move on. If non-empty, continue to 6b.

### 6b: Decide where each orphan belongs

Don't just dump orphans into Home.md. The graph stays meaningful only when notes are linked from the *right* parent. Use the folder as the hint:

| Orphan location | Correct parent index | Section to add under |
|-----------------|----------------------|----------------------|
| `April/April Index.md` itself (shouldn't happen, but if it does) | `Home.md` | `## Indexes` or equivalent |
| `April/AprilDDUpdates/*.md` | `April/April Index.md` | `## Update Notes → ### AprilDDUpdates` |
| `Sessions/Month Year/*.md` | `April/April Index.md` (or the matching month index) | `## Sessions` |
| `Git History/MonShortYYYY/*.md` | `April/April Index.md` (matching month) | `## Git History` |
| `Plans/*.md` | `Plans/Plans Index.md` (create if missing) or `Home.md` `## Plans` section | under the relevant workstream heading |
| `Assets/*` (image/PDF) | whichever note embeds it — if none, flag as unused attachment | n/a |
| Anywhere else / no clear home | `Home.md` `## Uncategorised` section (create if missing) + flag to user | user reviews next session |

### 6c: Add the wikilink

For each orphan, open the target index and add a wikilink under the appropriate section. Format the entry as:

```markdown
- [[Folder/Filename|Display text]] — one-line description
```

Use the display-text form when the bare filename would read awkwardly (dates, hashes, workstream IDs). Derive the one-line description from the orphan's H1 or first paragraph.

**Example** — the orphan `Git History/Apr2026/Apr16` lands in `April/April Index.md` under `## Git History`:

```markdown
## Git History

- [[Git History/Apr2026/Apr16|16 April 2026]] — 5 commits
- [[Git History/Apr2026/Apr17|17 April 2026]] — 12 commits
```

### 6d: If an index doesn't exist, create it

When a folder has three or more notes but no index file, create one. Template:

```markdown
---
tags:
  - index
type: index
---

# [Folder Name]

Back to [[Home]]

Brief description of what this folder holds.

## [Section 1]

- [[Note A]] — description
- [[Note B]] — description
```

Then link the new index from `Home.md`.

### 6e: Re-run the scanner to verify

After the edits, run `find-orphans.sh` again — the list should be empty (or only contain things the user explicitly wants left orphaned, like scratch notes). If anything remains, explain what and why rather than forcing links that don't belong.

---

## Phase 7: Obsidian Standards Audit

The vault's value comes from its connections and its consistency. Both decay without active maintenance. Run these checks against the standards defined in `references/obsidian-standards.md` — **read that file first** if you haven't this session.

### 7a: Frontmatter check

Every `.md` outside `.obsidian/` and `.trash/` should have YAML frontmatter. Detect missing:

```bash
find "$VAULT" -name "*.md" -not -path "*/.obsidian/*" -not -path "*/.trash/*" \
  | while read -r f; do
      head -1 "$f" | grep -q '^---$' || echo "MISSING FRONTMATTER: ${f#$VAULT/}"
    done
```

For files missing frontmatter, propose the minimum set (`tags`, `type`, `date` if applicable) based on folder and content. Don't auto-inject — offer the addition to the user.

### 7b: Broken wikilink detection

Find wikilinks whose target doesn't resolve to any file in the vault:

```bash
cd "$VAULT"
# Extract every link target (basename, fragments stripped)
grep -rho '\[\[[^]]*\]\]' . \
  --include="*.md" \
  --exclude-dir=".obsidian" \
  --exclude-dir=".trash" \
  | sed 's|^\[\[||; s|\]\]$||' \
  | awk -F'|' '{print $1}' \
  | awk -F'#' '{print $1}' \
  | sort -u > /tmp/vault-targets.txt

# Build set of existing filenames (basename, no extension)
find . -name "*.md" -not -path "./.obsidian/*" -not -path "./.trash/*" \
  | sed 's|\.md$||' \
  | awk -F'/' '{print $NF}' \
  | sort -u > /tmp/vault-basenames.txt

# Also include full paths so [[Folder/Name]] links validate
find . -name "*.md" -not -path "./.obsidian/*" -not -path "./.trash/*" \
  | sed 's|^\./||; s|\.md$||' \
  | sort -u > /tmp/vault-paths.txt

cat /tmp/vault-basenames.txt /tmp/vault-paths.txt | sort -u > /tmp/vault-all.txt

# Broken = target not in either set
comm -23 /tmp/vault-targets.txt /tmp/vault-all.txt
```

Each broken link means either (a) the target was renamed — fix the link, or (b) the target should exist — create the note. Present findings to the user; don't silently delete broken links.

### 7c: Back-link line on subpages

Every non-root note should start (after frontmatter + H1) with a `Back to [[Home]]` or `Back to [[April/April Index|April Index]]` line. Grep for files missing it:

```bash
find "$VAULT" -name "*.md" -not -path "*/.obsidian/*" -not -path "*/.trash/*" \
  -not -name "Home.md" \
  | while read -r f; do
      grep -q 'Back to \[\[' "$f" || echo "NO BACK-LINK: ${f#$VAULT/}"
    done
```

### 7d: Tag vocabulary drift

Dump the tag vocabulary and flag outliers:

```bash
grep -rh '^  - ' --include="*.md" "$VAULT" \
  | grep -v '^  - \[' \
  | sort | uniq -c | sort -rn | head -40
```

Look for:
- Mixed case variants (`Session` vs `session`) — pick one.
- Typos (`sesson`, `handoever`).
- One-off tags that probably aren't earning their keep (appear on 1 file only).

Report; don't bulk-rename without confirmation.

### 7e: H1 / filename sanity check

Verify every file's first heading is an H1 (not H2) and is present. This is what renders as the note title.

```bash
find "$VAULT" -name "*.md" -not -path "*/.obsidian/*" -not -path "*/.trash/*" \
  | while read -r f; do
      # Skip frontmatter, find first content line
      first=$(awk '/^---$/{c++; next} c>=2' "$f" | grep -m1 '^#')
      case "$first" in
        '# '*) ;;  # good
        *) echo "NO H1: ${f#$VAULT/}" ;;
      esac
    done
```

### 7f: Callout modernisation (optional)

Flag `>` blockquotes that lead with `⚠️`, `ℹ️`, `💡`, `✅`, `❌`, `🚨` — those are legacy emoji-quotes and should be replaced with proper callouts (`> [!warning]`, `> [!note]`, etc.) per the standards doc. Offer the change; don't auto-rewrite — the user may have been intentional.

---

## Phase 8: Memory File Audit

### 6a: Check for stale memories

```bash
ls -lt /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynlaInternational/memory/*.md
```

Read each memory file. Flag any that reference outdated state.

### 6b: Check MEMORY.md index

Verify every `.md` file in the memory directory has an entry in MEMORY.md, and vice versa.

### 6c: Suggest new memories

Based on this session's work, should any new memories be saved? Only if non-obvious and useful across sessions.

---

## Phase 9: Summary Report

```markdown
## Vault Sync Complete

**Date:** [today]

### Session Transcript
- [filename] — [word count] words, [N] exchanges captured

### Files Synced
- [N] new files, [N] updated
- [folder list]

### Git History
- [status]

### Index Updates
- April Index: [added/updated]
- Home.md: [added/updated]

### Orphans linked
- [N] orphans found, [M] linked into [indexes], [K] flagged for user review

### Standards audit
- Missing frontmatter: [N]
- Broken wikilinks: [N] — [list or "none"]
- Missing back-links: [N]
- Missing H1: [N]
- Tag drift: [outliers found, or "clean"]
- Legacy emoji-quotes to modernise: [N]

### Memory
- [N] files audited, [N] stale, [N] new
```

---

## Important Rules

- The vault is at `/Users/CSJ/Desktop/FynlaInter/FynlaInter/` — write files directly
- **Obsidian standards live in `references/obsidian-standards.md`** — read them when authoring new vault files or running the Phase 7 audit
- Use Obsidian format: YAML frontmatter, `[[wikilinks]]` without `.md` extension, `> [!callout]` blocks over emoji-quotes
- **Session transcripts are the priority** — use `scripts/build-session-transcript.py` to parse the authoritative JSONL. Don't reconstruct from memory
- User prompts, Claude responses, tool uses, sub-agent dispatches — everything the JSONL records lands in the transcript verbatim
- Sub-agent transcripts are written as linked sibling files; don't inline them in the main transcript
- This skill is idempotent — safe to run multiple times
- If nothing changed, say so
- Only sync files relevant to the international project — NOT main fynla project update notes
- **Never auto-delete orphans** — link them into the right index instead. If a file really shouldn't exist, let the user decide
- **Never auto-fix broken wikilinks or frontmatter** — report findings, let the user approve each change. A silent rename is how the vault gets corrupted
- Flag, don't bulk-rewrite. Callouts, tag vocabulary, H1 fixes — all presented as suggestions
