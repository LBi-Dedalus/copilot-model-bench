"""Task and config loading."""
from __future__ import annotations

import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import yaml

ROOT = Path(__file__).resolve().parent.parent
CATEGORIES = [
    "comprehension",
    "review",
    "planning",
    "code_writing",
    "architecture",
    "healthcare_jargon",
]


@dataclass
class RubricItem:
    id: str
    description: str
    points: float = 1.0
    # Regexes: the item is satisfied if ANY matches (case-insensitive).
    patterns: list[str] = field(default_factory=list)


@dataclass
class Task:
    id: str
    category: str
    prompt: str
    rubric: list[RubricItem]
    inline_context: str = ""
    context_files: list[str] = field(default_factory=list)
    # Regexes that must NOT appear (hallucination traps, accepting a false premise...).
    forbidden: list[str] = field(default_factory=list)
    # Reference file:line / path citations that are valid, used to spot invented paths.
    check_paths: bool = False
    # Code-writing task: hidden PHP test harness run against the extracted code block.
    code_test: dict[str, Any] | None = None
    use_judge: bool = True
    source: str = ""


def load_config(path: str | Path | None = None) -> dict[str, Any]:
    p = Path(path) if path else ROOT / "config.yaml"
    if not p.exists():
        raise SystemExit(f"Missing {p}. Copy config.example.yaml to config.yaml and edit it.")
    return yaml.safe_load(p.read_text(encoding="utf-8"))


def load_tasks(dirs: list[Path] | None = None) -> list[Task]:
    dirs = dirs or [ROOT / "tasks", ROOT / "private"]
    tasks: list[Task] = []
    for d in dirs:
        if not d.exists():
            continue
        for f in sorted(d.glob("*.y*ml")):
            data = yaml.safe_load(f.read_text(encoding="utf-8")) or {}
            for raw in data.get("tasks", []):
                rubric = [RubricItem(**r) for r in raw.pop("rubric", [])]
                t = Task(rubric=rubric, source=str(f), **raw)
                if t.category not in CATEGORIES:
                    raise SystemExit(f"{f}: unknown category {t.category!r} in task {t.id}")
                tasks.append(t)
    ids = [t.id for t in tasks]
    dup = {i for i in ids if ids.count(i) > 1}
    if dup:
        raise SystemExit(f"Duplicate task ids: {sorted(dup)}")
    return tasks


def build_prompt(task: Task, context_root: str | None) -> str:
    """Identical prompt for every model; context is inlined so no tools are needed."""
    parts = [task.prompt.strip()]
    if task.inline_context:
        parts.append("\n--- CONTEXT ---\n" + task.inline_context.strip())
    for rel in task.context_files:
        if not context_root:
            raise SystemExit(f"Task {task.id} needs context_root in config.yaml")
        fp = Path(context_root) / rel
        text = fp.read_text(encoding="latin-1")
        numbered = "\n".join(f"{i + 1}: {ln}" for i, ln in enumerate(text.splitlines()))
        parts.append(f"\n--- FILE: {rel} (line-numbered) ---\n{numbered}")
    return "\n".join(parts)


def compile_patterns(patterns: list[str]) -> list[re.Pattern[str]]:
    return [re.compile(p, re.IGNORECASE | re.DOTALL) for p in patterns]
