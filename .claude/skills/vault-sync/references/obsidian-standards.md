# Obsidian Standards for the FynlaInter Vault

Read this when running the standards audit in `vault-sync` Phase 8, or when authoring any new vault document. These rules aren't arbitrary — each one exists because a violation has made the vault harder to navigate or broken a link at some point.

---

## 1. YAML frontmatter

Every note has frontmatter at the very top, delimited by `---` on its own line before and after. Frontmatter fields Obsidian understands natively:

| Field | Purpose | Required for |
|-------|---------|-------------|
| `tags` | Categorisation — YAML list, lowercase, hyphen-separated | all notes |
| `aliases` | Alternate names the note can be linked as | optional |
| `date` | ISO `YYYY-MM-DD` | session, handover, git-history notes |
| `type` | Taxonomy (`session`, `handover`, `plan`, `index`, `git-history`, `prd`, `reference`) | all notes |

**Good:**

```yaml
---
tags:
  - session
  - april-2026
date: 2026-04-19
type: session
session: 3
---
```

**Bad:**

```yaml
---
Tags: [Session, April-2026]   # wrong casing, wrong list form
date: 19/04/2026              # non-ISO
---
```

Tags are **always lowercase with hyphens**, never snake_case or PascalCase. Mixed tag vocabularies fragment Obsidian's tag pane — pick one spelling and stick with it.

---

## 2. Wikilinks, not markdown links

For any reference *inside the vault*, use `[[Target]]`. Markdown-style `[text](Target.md)` breaks Obsidian's backlink graph and won't follow on rename.

- Bare: `[[Home]]`
- With display text: `[[April/April Index|April Index]]` (shows "April Index", links to the folder file)
- With heading: `[[Home#Current Status]]`
- With block ref: `[[Home#^block-id]]`
- Embed: `![[Diagram.png]]` (shows the file inline, still counts as an incoming link)

External URLs use standard markdown: `[HMRC](https://www.gov.uk/hmrc)`. That's fine — Obsidian's graph only tracks wikilinks.

---

## 3. The H1 / filename contract

The first markdown heading after frontmatter should be an `# H1` that reads like the file name (not necessarily identical, but clearly recognisable). This is the title shown in preview mode and at the top of the opened note.

- File: `April19-session-1.md` → H1: `# Session 1 — 19 April 2026`
- File: `handover-2026-04-20-session-1.md` → H1: `# Handover — 2026-04-20, Session 1`
- File: `April Index.md` → H1: `# April 2026`

Only one H1 per file. Everything beneath is H2 or lower.

---

## 4. Back-links at the top

Every subpage has a back-link line immediately under the H1. This is a navigation affordance — Obsidian users cmd-click to jump up the hierarchy without having to open the graph.

```markdown
# Session 1 — 19 April 2026

Back to [[Home]] | [[April/April Index|April Index]]
```

Home.md itself has no back-link line (it's the root).

---

## 5. Callouts over emoji quotes

Obsidian callouts render as coloured panels. Use them instead of decorating blockquotes with emojis.

**Preferred:**

```markdown
> [!warning]
> Never run `migrate:fresh` — it wipes data.

> [!note]
> SARS 2026/27 values are best-effort; cross-check before production.

> [!tip]
> Dispatch the `tax-compliance-reviewer` agent for any HMRC-facing change.

> [!info]
> Ports: app on :8001, Vite on :5174.

> [!todo]
> Carry this to tomorrow's session.
```

**Avoid:**

```markdown
> ⚠️ Never run migrate:fresh   ← emoji-decorated quote, no callout styling
```

---

## 6. Tables for structured data

Use markdown tables when the content has repeating structure (commit log, metrics, status). They're the second-best-indexed thing Obsidian renders (after headings).

```markdown
| Time  | Hash       | Type | Message                     |
|-------|------------|------|-----------------------------|
| 14:02 | `abcd123`  | +    | feat: add TFSA validator    |
```

Pipes at the start and end of each row. Dashes on the separator row matched to column count. Alignment colons only when actually needed.

---

## 7. File names follow folder convention

Each folder has its own pattern — don't cross them.

| Folder | Pattern | Example |
|--------|---------|---------|
| `April/` | `April Index.md` | `April Index.md` |
| `April/AprilDDUpdates/` | `handover-*.md`, `deploy-*.md`, `PRD-*.md`, `CSJTODO.md` | `handover-2026-04-19-session-1.md` |
| `Sessions/Month Year/` | `MonthDD-session-N.md` | `April19-session-1.md` |
| `Git History/MonShortYYYY/` | `MonShortDD.md` | `Apr19.md` |
| `Plans/` | `PRD-*.md`, `plan-*.md`, or descriptive kebab-case | `PRD-ws-1-3c-za-investment.md` |

New notes inherit the pattern of their folder. A session note never goes in `Plans/`; a PRD never goes in `Sessions/`.

---

## 8. Indexes (MOCs) make the vault navigable

Every folder that holds ≥3 notes gets an index file at its root. The index lists what's inside with wikilinks and one-line descriptions. Examples already in the vault: `April/April Index.md`, `Home.md` (which functions as the vault-wide index).

When the orphan audit in Phase 7 identifies a file with no incoming links:
1. Decide where it belongs (its parent index based on folder).
2. Add a wikilink to it from that index, under the appropriate section.
3. If no suitable section exists, add one.

Never just dump orphans into `Home.md` — Home is for top-level navigation. Deep files belong in their folder's own index.

---

## 9. No broken wikilinks

A link like `[[NonExistent Page]]` renders as an unresolved red link in Obsidian. These are technical debt — they either reference a page that was renamed (fix the link) or a page that should exist but doesn't (create the page, or remove the link).

During the standards audit, grep for `[[...]]` targets whose basename doesn't exist anywhere in the vault, and flag them for repair.

---

## 10. Attachments live together

Images, PDFs, and other binaries go in `Assets/` at the vault root, referenced with `![[Assets/image.png]]`. Don't scatter attachments across content folders — it makes cleanup later painful.

---

## 11. Tag vocabulary (keep it small)

Adding a new tag every week fragments the tag pane. Before introducing one, check if an existing tag covers it. Current canonical tags:

- `index` — MOC files (Home, April Index, Plans Index)
- `session` — session transcript
- `handover` — handover doc
- `prd` — product requirements doc
- `plan` — implementation plan
- `git-history` — daily commit log
- `home` — Home.md only
- Month-year: `april-2026`, `may-2026`, etc.

Domain tags (`tfsa`, `excon`, `retirement`) are fine when the content genuinely justifies them. Don't tag every note `fynla` — the whole vault is Fynla; redundant tags add noise without signal.
