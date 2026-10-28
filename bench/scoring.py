"""Automatic (objective) scoring: rubric patterns, forbidden claims, path existence, PHP tests."""
from __future__ import annotations

import re
import subprocess
import tempfile
from pathlib import Path
from typing import Any

from .tasks import Task, compile_patterns

PATH_RE = re.compile(r"[\w./\\-]+\.(?:php|js|py|ts|yaml|yml|json|md|sql|xml|html|twig|blade\.php)\b")
CODE_BLOCK_RE = re.compile(r"```(?:php|python|js|javascript)?\s*\n(.*?)```", re.DOTALL | re.IGNORECASE)


def rubric_score(task: Task, output: str) -> dict[str, Any]:
    """Fraction of rubric points matched by regex, minus nothing; 0..1."""
    total = sum(r.points for r in task.rubric) or 1.0
    hits, missed = [], []
    for item in task.rubric:
        if any(p.search(output) for p in compile_patterns(item.patterns)):
            hits.append(item.id)
        else:
            missed.append(item.id)
    got = sum(r.points for r in task.rubric if r.id in hits)
    return {"rubric": got / total, "hits": hits, "missed": missed}


def forbidden_hits(task: Task, output: str) -> list[str]:
    return [p.pattern for p in compile_patterns(task.forbidden) if p.search(output)]


def path_accuracy(output: str, context_root: str | None, known: list[str]) -> dict[str, Any]:
    """Share of cited file paths that really exist (hallucination check)."""
    cited = {m.group(0).replace("\\", "/").lstrip("./") for m in PATH_RE.finditer(output)}
    if not cited or not context_root:
        return {"cited": 0, "valid": 0, "invalid": []}
    root = Path(context_root)
    valid, invalid = 0, []
    for c in sorted(cited):
        if (root / c).exists() or any(k.endswith(c) or c.endswith(k) for k in known):
            valid += 1
        else:
            invalid.append(c)
    return {"cited": len(cited), "valid": valid, "invalid": invalid}


def run_code_test(task: Task, output: str, php: str = "php") -> dict[str, Any]:
    """Extract the last code block, append the hidden PHP harness, run it.

    The harness must print a line `PASSED n/m` on success paths; exit code 0 means all passed.
    """
    spec = task.code_test or {}
    blocks = CODE_BLOCK_RE.findall(output)
    if not blocks:
        return {"extracted": False, "lint": False, "tests": 0.0}
    code = blocks[-1].strip()
    if not code.startswith("<?php"):
        code = "<?php\n" + code
    harness = spec["harness"]
    with tempfile.TemporaryDirectory() as td:
        f = Path(td) / "solution.php"
        f.write_text(code + "\n?>", encoding="utf-8")
        t = Path(td) / "harness.php"
        t.write_text(harness, encoding="utf-8")
        lint = subprocess.run([php, "-l", str(f)], capture_output=True, text=True)
        if lint.returncode != 0:
            return {"extracted": True, "lint": False, "tests": 0.0, "detail": lint.stdout[:300]}
        try:
            run = subprocess.run([php, str(t), str(f)], capture_output=True, text=True, timeout=60)
        except subprocess.TimeoutExpired:
            return {"extracted": True, "lint": True, "tests": 0.0, "detail": "timeout"}
        m = re.search(r"PASSED\s+(\d+)/(\d+)", run.stdout)
        frac = int(m.group(1)) / int(m.group(2)) if m and int(m.group(2)) else 0.0
        return {"extracted": True, "lint": True, "tests": frac, "detail": run.stdout[-300:]}


def auto_score(task: Task, output: str, cfg: dict[str, Any]) -> dict[str, Any]:
    res: dict[str, Any] = rubric_score(task, output)
    bad = forbidden_hits(task, output)
    res["forbidden"] = bad
    q = res["rubric"]
    if bad:
        q = max(0.0, q - 0.25 * len(bad))
    if task.check_paths:
        pa = path_accuracy(output, cfg.get("context_root"), task.context_files)
        res["paths"] = pa
        if pa["cited"]:
            q *= 0.5 + 0.5 * pa["valid"] / pa["cited"]
    if task.code_test:
        ct = run_code_test(task, output, cfg.get("php", "php"))
        res["code"] = ct
        q = 0.7 * ct["tests"] + 0.3 * q if ct.get("lint") else 0.3 * q
    res["auto"] = round(q, 4)
    return res
