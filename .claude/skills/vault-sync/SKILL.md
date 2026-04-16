---
name: vault-sync
description: Sync project documentation and session transcripts to the FynlaInter Obsidian vault. Captures the full AI conversation (user prompts + Claude responses), updates git history, indexes, and audits vault integrity. Use when the user says "sync vault", "update vault", "sync docs", or at session end.
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

**This is the most important phase.** Capture the current session's conversation as a vault document.

### 0a: Create session transcript file

Determine today's date and session number:

```bash
MONTH_NAME=$(date +%B)
TODAY=$(date +%d)
YEAR=$(date +%Y)
TODAY_FULL=$(date +%Y-%m-%d)
VAULT="/Users/CSJ/Desktop/FynlaInter/FynlaInter"
SESSION_DIR="$VAULT/Sessions/${MONTH_NAME} ${YEAR}"
mkdir -p "$SESSION_DIR"

# Find the next session number for today
existing=$(ls "$SESSION_DIR"/${MONTH_NAME}${TODAY}-session-*.md 2>/dev/null | wc -l | tr -d ' ')
next=$((existing + 1))
echo "Session file: ${MONTH_NAME}${TODAY}-session-${next}.md"
```

### 0b: Write the session transcript

Create the file at `$SESSION_DIR/${MONTH_NAME}${TODAY}-session-${next}.md` with this structure:

```markdown
---
tags:
  - session
  - ${month_name_lowercase}-${YEAR}
date: ${TODAY_FULL}
session: ${next}
duration: [approximate duration if known]
---

# Session ${next} — ${TODAY} ${MONTH_NAME} ${YEAR}

Back to [[Home]] | [[${MONTH_NAME}/${MONTH_NAME} Index|${MONTH_NAME} Index]]

## Summary

[2-3 sentence summary of what was accomplished this session]

## Key Decisions

- [Bullet list of important decisions made during the session]

## Conversation

[The full conversation follows. For each exchange, use this format:]

### User
> [The user's exact prompt/message, quoted verbatim. Include typos — they're authentic.]

### Claude
[Your response, summarised if very long (e.g. large code blocks can be replaced with "[Generated 200-line PHP file — see commit X]"), but all explanations, decisions, and reasoning preserved in full. Key code snippets should be included.]

[Repeat ### User / ### Claude for every exchange in the session]

## Files Changed

- [List every file created, modified, or deleted this session with a brief note]

## Status at Session End

- **Tests:** [pass count] passing, [fail count] failing
- **Branch:** [current branch]
- **Next steps:** [what should happen next]
```

### 0c: How to write the conversation

**Rules for capturing the conversation:**

1. **User prompts are verbatim.** Copy them exactly, including typos, emphasis, and tone. Use blockquotes (`>`).
2. **Your responses are faithfully represented.** Include the reasoning, explanations, and decisions. Summarise only when the response was extremely long (500+ lines) or contained repetitive code output.
3. **Tool calls and results:** Don't include every tool call verbatim. Instead, describe what was done: "Ran `./vendor/bin/pest` — 2284 tests passing, 4 failing." Include the key output, not the full dump.
4. **Code changes:** Include the significant code snippets. For large file writes, note what was written and where rather than reproducing the entire file.
5. **Preserve the flow.** The reader should be able to follow the session's logic: what was asked, what was tried, what worked, what didn't, and why decisions were made.
6. **Include failed attempts.** If something was tried and didn't work, capture that — it's valuable context for future sessions.

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

## Phase 6: Memory File Audit

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

## Phase 7: Summary Report

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

### Memory
- [N] files audited, [N] stale, [N] new
```

---

## Important Rules

- The vault is at `/Users/CSJ/Desktop/FynlaInter/FynlaInter/` — write files directly
- Use Obsidian format: YAML frontmatter, `[[wikilinks]]` without `.md` extension
- **Session transcripts are the priority** — capture the conversation faithfully
- User prompts are quoted verbatim including typos
- This skill is idempotent — safe to run multiple times
- If nothing changed, say so
- Only sync files relevant to the international project — NOT main fynla project update notes
