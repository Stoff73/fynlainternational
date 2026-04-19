#!/usr/bin/env bash
# find-orphans.sh — print vault markdown files with zero incoming wikilinks.
#
# A file is "orphaned" if nothing else in the vault links to it (by basename
# without .md, or by full relative path without .md). Home.md is always
# excluded — it's the root, so having no incoming links is fine.
#
# Usage: find-orphans.sh <vault-root>
# Output: one orphan path (relative to vault root) per line, sorted.
#
# Works on macOS bash 3.2+ (no mapfile, no arrays needed).

set -eu

VAULT="${1:-}"
if [ -z "$VAULT" ] || [ ! -d "$VAULT" ]; then
  echo "usage: $0 <vault-root>" >&2
  exit 1
fi

cd "$VAULT"

# All markdown files (relative, no .md extension), one per line.
ALL_FILES_FILE=$(mktemp)
trap 'rm -f "$ALL_FILES_FILE" "$TARGETS_FILE"' EXIT

find . -type f -name "*.md" \
  -not -path "./.obsidian/*" \
  -not -path "./.trash/*" \
  2>/dev/null \
  | sed 's|^\./||; s|\.md$||' \
  | sort > "$ALL_FILES_FILE"

# Every wikilink target across the vault.
# Forms handled:
#   [[Target]]              → "Target"
#   [[Target|Display]]      → "Target"
#   [[Folder/Target]]       → "Folder/Target"
#   [[Target#Heading]]      → "Target" (fragment stripped)
#   ![[Target]]             → "Target" (embeds count as links)
TARGETS_FILE=$(mktemp)

grep -rho '!\{0,1\}\[\[[^]]*\]\]' . \
  --include="*.md" \
  --exclude-dir=".obsidian" \
  --exclude-dir=".trash" \
  2>/dev/null \
  | sed 's|^!||; s|^\[\[||; s|\]\]$||' \
  | awk -F'|' '{print $1}' \
  | awk -F'#' '{print $1}' \
  | sort -u > "$TARGETS_FILE"

# For each file, accept it as linked if either its full path or its basename
# appears in the target set. Obsidian resolves links by shortest unique path,
# so a basename match is sufficient.
while IFS= read -r file; do
  [ -z "$file" ] && continue
  [ "$file" = "Home" ] && continue    # root index — never orphaned by definition

  basename="${file##*/}"

  if grep -qxF "$file" "$TARGETS_FILE" 2>/dev/null; then
    continue
  fi
  if grep -qxF "$basename" "$TARGETS_FILE" 2>/dev/null; then
    continue
  fi

  echo "$file"
done < "$ALL_FILES_FILE"
