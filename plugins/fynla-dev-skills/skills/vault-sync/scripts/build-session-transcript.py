#!/usr/bin/env python3
"""
build-session-transcript.py — turn a Claude Code session JSONL into an
Obsidian-ready markdown transcript.

Captures, losslessly:
  - Every user message (verbatim)
  - Every assistant message (text + thinking-summary line)
  - Every tool use (Skill, Task/subagent, Bash, Edit, Write, Read, Agent,
    AskUserQuestion, etc.) with a one-line summary of the key inputs
  - Every tool result (truncated to keep the file readable — full content
    stays in the source JSONL)
  - Subagent invocations dispatched via the Task tool, with a separate
    full transcript file linked from the main transcript

Why a script, not "have Claude summarise the conversation": Claude cannot
reliably remember exact user prompts after thousands of messages, and
summarising loses decisions and failed attempts. The JSONL is the source
of truth.

Usage:
  build-session-transcript.py <session-jsonl-or-id> <output-md>
  build-session-transcript.py --latest <project-dir> <output-md>
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path
from datetime import datetime
from typing import Any, Iterator

# --- Constants ----------------------------------------------------------------

# Max chars we inline for any single tool-result body. The rest stays in the
# JSONL — the whole point is a readable transcript, not a raw dump.
MAX_RESULT_CHARS = 1500

# Max chars we inline for a subagent prompt before linking to the sibling file.
MAX_SUBAGENT_PROMPT_PREVIEW = 600

# Tools whose results are noisy and unhelpful inline (mostly file reads —
# the content is in the file, we don't need a snapshot in the transcript).
SUPPRESS_RESULT_FOR = {"Read"}


# --- JSONL parsing ------------------------------------------------------------

def iter_lines(path: Path) -> Iterator[dict]:
    with path.open("r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                yield json.loads(line)
            except json.JSONDecodeError:
                continue


def extract_text_blocks(content: Any) -> str:
    """content is either a string or a list of blocks. Return concatenated text."""
    if isinstance(content, str):
        return content
    if isinstance(content, list):
        parts = []
        for block in content:
            if not isinstance(block, dict):
                continue
            if block.get("type") == "text":
                parts.append(block.get("text", ""))
        return "\n".join(p for p in parts if p)
    return ""


def extract_tool_uses(content: Any) -> list[dict]:
    if not isinstance(content, list):
        return []
    return [b for b in content if isinstance(b, dict) and b.get("type") == "tool_use"]


def extract_tool_results(content: Any) -> list[dict]:
    if not isinstance(content, list):
        return []
    return [b for b in content if isinstance(b, dict) and b.get("type") == "tool_result"]


# --- Tool-use summaries --------------------------------------------------------

def summarise_tool_use(tu: dict) -> str:
    """One-line human summary of a tool_use block."""
    name = tu.get("name", "unknown")
    inp = tu.get("input", {}) or {}

    if name == "Bash":
        cmd = (inp.get("command") or "").strip().splitlines()[0] if inp.get("command") else ""
        cmd = cmd[:200]
        return f"**Bash** — `{cmd}`"

    if name == "Edit":
        return f"**Edit** — `{inp.get('file_path','?')}`"

    if name == "Write":
        return f"**Write** — `{inp.get('file_path','?')}`"

    if name == "Read":
        return f"**Read** — `{inp.get('file_path','?')}`"

    if name == "Glob":
        return f"**Glob** — `{inp.get('pattern','?')}`"

    if name == "Grep":
        pattern = (inp.get("pattern") or "")[:100]
        return f"**Grep** — `{pattern}`"

    if name == "Skill":
        args = inp.get("args") or ""
        args_str = f" (args: `{args[:80]}`)" if args else ""
        return f"**Skill** — `{inp.get('skill','?')}`{args_str}"

    if name == "Task" or name == "Agent":
        subagent = inp.get("subagent_type", "general-purpose")
        desc = inp.get("description", "?")
        return f"**Sub-agent ({subagent})** — {desc}"

    if name == "AskUserQuestion":
        questions = inp.get("questions") or []
        first = questions[0].get("question", "?") if questions else "?"
        return f"**AskUserQuestion** — {first[:120]}"

    if name == "TodoWrite":
        todos = inp.get("todos") or []
        return f"**TodoWrite** — {len(todos)} todo(s)"

    if name == "WebFetch":
        return f"**WebFetch** — {inp.get('url','?')[:100]}"

    if name == "WebSearch":
        return f"**WebSearch** — `{(inp.get('query') or '')[:100]}`"

    # Generic fallback — show the first input key/value
    preview = ""
    if inp:
        k = next(iter(inp))
        v = str(inp[k])[:100]
        preview = f" — `{k}={v}`"
    return f"**{name}**{preview}"


def summarise_tool_result(tu_name: str, result: dict) -> str:
    """Truncated rendering of a tool result."""
    if tu_name in SUPPRESS_RESULT_FOR:
        return ""
    content = result.get("content")
    text = extract_text_blocks(content) if isinstance(content, list) else str(content or "")
    text = text.strip()
    if not text:
        return ""
    if len(text) > MAX_RESULT_CHARS:
        text = text[:MAX_RESULT_CHARS] + f"\n… [truncated, {len(text)} chars total]"
    # Fence it so long output doesn't destroy the markdown layout
    return f"\n<details><summary>result</summary>\n\n```\n{text}\n```\n\n</details>"


# --- Main transcript builder --------------------------------------------------

def build_transcript(
    session_jsonl: Path,
    subagents_dir: Path | None,
    out_path: Path,
    session_dir_for_subagent_files: Path,
) -> dict:
    """Returns stats dict."""
    events = list(iter_lines(session_jsonl))

    # Collect tool_use → tool_result mapping (keyed by tool_use_id)
    results_by_id: dict[str, dict] = {}
    for ev in events:
        msg = ev.get("message") or {}
        if msg.get("role") != "user":
            continue
        for r in extract_tool_results(msg.get("content")):
            tuid = r.get("tool_use_id")
            if tuid:
                results_by_id[tuid] = r

    # Find Task/Agent invocations in the main transcript so we can link subagent files
    subagent_invocations: list[dict] = []
    for ev in events:
        msg = ev.get("message") or {}
        if msg.get("role") != "assistant":
            continue
        for tu in extract_tool_uses(msg.get("content")):
            if tu.get("name") in ("Task", "Agent"):
                subagent_invocations.append({
                    "id": tu.get("id"),
                    "name": tu.get("name"),
                    "subagent_type": (tu.get("input") or {}).get("subagent_type", "general-purpose"),
                    "description": (tu.get("input") or {}).get("description", ""),
                    "prompt": (tu.get("input") or {}).get("prompt", ""),
                    "timestamp": ev.get("timestamp") or ev.get("ts"),
                })

    # Dump subagent JSONLs to sibling .md files so the main transcript can link
    subagent_files: list[tuple[str, Path]] = []
    if subagents_dir and subagents_dir.exists():
        for jsonl in sorted(subagents_dir.glob("agent-*.jsonl")):
            subagent_md = session_dir_for_subagent_files / "subagents" / (jsonl.stem + ".md")
            subagent_md.parent.mkdir(parents=True, exist_ok=True)
            write_subagent_transcript(jsonl, subagent_md)
            subagent_files.append((jsonl.stem, subagent_md))

    # Now walk the main transcript in order and produce markdown
    lines: list[str] = []
    stats = {
        "user_messages": 0,
        "assistant_messages": 0,
        "tool_uses": 0,
        "subagents": len(subagent_invocations),
        "skills_invoked": [],
    }

    for ev in events:
        t = ev.get("type")
        msg = ev.get("message") or {}
        role = msg.get("role")
        ts = ev.get("timestamp", "")

        if t == "user" and role == "user":
            text = extract_text_blocks(msg.get("content"))
            text = text.strip()
            if not text:
                continue  # user "message" that's only a tool_result is handled with the tool use
            stats["user_messages"] += 1
            lines.append(f"\n### User — {ts}\n")
            for line in text.split("\n"):
                lines.append(f"> {line}" if line else ">")
            lines.append("")

        elif t == "assistant" and role == "assistant":
            stats["assistant_messages"] += 1
            text = extract_text_blocks(msg.get("content")).strip()
            tool_uses = extract_tool_uses(msg.get("content"))

            lines.append(f"\n### Claude — {ts}\n")
            if text:
                lines.append(text)
                lines.append("")

            for tu in tool_uses:
                stats["tool_uses"] += 1
                if tu.get("name") == "Skill":
                    skill = (tu.get("input") or {}).get("skill")
                    if skill:
                        stats["skills_invoked"].append(skill)

                lines.append(f"- {summarise_tool_use(tu)}")
                r = results_by_id.get(tu.get("id"))
                if r:
                    summary = summarise_tool_result(tu.get("name", ""), r)
                    if summary:
                        lines.append(summary)
            lines.append("")

    # Subagent section
    if subagent_invocations:
        lines.append("\n---\n\n## Sub-agents dispatched\n")
        for inv in subagent_invocations:
            lines.append(f"\n### {inv['description']} ({inv['subagent_type']})\n")
            lines.append(f"*Invocation time:* {inv['timestamp']}\n")
            prompt = inv["prompt"] or ""
            if len(prompt) > MAX_SUBAGENT_PROMPT_PREVIEW:
                preview = prompt[:MAX_SUBAGENT_PROMPT_PREVIEW] + "…"
            else:
                preview = prompt
            lines.append(f"**Prompt:**\n\n```\n{preview}\n```\n")
            # Try to match to a subagent JSONL
            matched = None
            for stem, path in subagent_files:
                # Claude writes the subagent jsonl keyed by its internal agent id, not the
                # tool_use id. We can't perfectly correlate without more data, so list all
                # subagent transcripts separately below.
                pass
        if subagent_files:
            lines.append("\n**Full sub-agent transcripts:**\n")
            for stem, path in subagent_files:
                rel = path.relative_to(out_path.parent)
                lines.append(f"- [[{rel.with_suffix('')}|{stem}]]")

    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text("\n".join(lines).lstrip() + "\n", encoding="utf-8")

    return stats


def write_subagent_transcript(jsonl: Path, out: Path) -> None:
    """Dump a subagent's JSONL as a simpler conversation-only markdown."""
    events = list(iter_lines(jsonl))
    lines = [
        "---",
        "type: subagent-transcript",
        f"source_file: {jsonl.name}",
        "---",
        "",
        f"# Sub-agent transcript — {jsonl.stem}",
        "",
        "Back to [[Home]]",
        "",
    ]
    for ev in events:
        msg = ev.get("message") or {}
        role = msg.get("role")
        ts = ev.get("timestamp", "")
        if role == "user":
            text = extract_text_blocks(msg.get("content")).strip()
            if text:
                lines.append(f"\n### User/Parent — {ts}\n")
                for line in text.split("\n"):
                    lines.append(f"> {line}" if line else ">")
        elif role == "assistant":
            text = extract_text_blocks(msg.get("content")).strip()
            tool_uses = extract_tool_uses(msg.get("content"))
            lines.append(f"\n### Sub-agent — {ts}\n")
            if text:
                lines.append(text)
            for tu in tool_uses:
                lines.append(f"- {summarise_tool_use(tu)}")
    out.write_text("\n".join(lines) + "\n", encoding="utf-8")


# --- Entrypoint ---------------------------------------------------------------

def resolve_session_path(arg: str, project_dir: Path) -> Path:
    """Accept either a full path, a session id, or --latest sentinel."""
    p = Path(arg)
    if p.is_file():
        return p
    # session id
    candidate = project_dir / f"{arg}.jsonl"
    if candidate.is_file():
        return candidate
    raise SystemExit(f"Could not resolve session: {arg}")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("session", help="Session JSONL path or session id, or '--latest'")
    ap.add_argument("output", help="Output markdown path")
    ap.add_argument(
        "--project-dir",
        default=str(Path.home() / ".claude/projects/-Users-CSJ-Desktop-fynlaInternational"),
        help="Claude Code project dir holding session JSONLs",
    )
    args = ap.parse_args()
    project_dir = Path(args.project_dir)

    if args.session == "--latest":
        candidates = sorted(project_dir.glob("*.jsonl"), key=lambda p: p.stat().st_mtime)
        if not candidates:
            print(f"No JSONL files in {project_dir}", file=sys.stderr)
            return 1
        session_path = candidates[-1]
    else:
        session_path = resolve_session_path(args.session, project_dir)

    session_id = session_path.stem
    subagents_dir = project_dir / session_id / "subagents"
    out_path = Path(args.output)

    stats = build_transcript(
        session_jsonl=session_path,
        subagents_dir=subagents_dir if subagents_dir.exists() else None,
        out_path=out_path,
        session_dir_for_subagent_files=out_path.parent,
    )

    print(f"Transcript written: {out_path}")
    print(f"  Session: {session_id}")
    print(f"  User messages: {stats['user_messages']}")
    print(f"  Assistant messages: {stats['assistant_messages']}")
    print(f"  Tool uses: {stats['tool_uses']}")
    print(f"  Subagent invocations: {stats['subagents']}")
    if stats["skills_invoked"]:
        uniq = {}
        for s in stats["skills_invoked"]:
            uniq[s] = uniq.get(s, 0) + 1
        print(f"  Skills invoked: " + ", ".join(f"{k}×{v}" for k, v in uniq.items()))
    return 0


if __name__ == "__main__":
    sys.exit(main())
