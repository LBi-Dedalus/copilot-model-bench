"""Calls a model through the Copilot CLI and records speed / cost / length."""
from __future__ import annotations

import json
import re
import subprocess
import time
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from .tasks import Task, build_prompt, load_tasks

RESULTS = Path(__file__).resolve().parent.parent / "results"


@dataclass
class Call:
    ok: bool
    output: str
    seconds: float
    error: str = ""


def estimate_tokens(text: str) -> int:
    # Rough, model-agnostic estimate (about 4 chars per token) so models stay comparable.
    return max(1, round(len(text) / 4))


def call_model(cfg: dict[str, Any], model: str, prompt: str) -> Call:
    cmd = [a.replace("{prompt}", prompt).replace("{model}", model) for a in cfg["command"]]
    start = time.perf_counter()
    try:
        proc = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=cfg.get("timeout_s", 600),
        )
    except subprocess.TimeoutExpired:
        return Call(False, "", time.perf_counter() - start, "timeout")
    elapsed = time.perf_counter() - start
    if proc.returncode != 0:
        return Call(False, proc.stdout, elapsed, proc.stderr.strip()[:500])
    return Call(True, proc.stdout.strip(), elapsed)


def parse_usage(raw: str) -> dict[str, int]:
    """Best-effort extraction of token usage if the CLI prints it; else empty."""
    usage: dict[str, int] = {}
    for key, pat in (("input_tokens", r"input[_ ]tokens\D+(\d[\d,]*)"), ("output_tokens", r"output[_ ]tokens\D+(\d[\d,]*)")):
        m = re.search(pat, raw, re.IGNORECASE)
        if m:
            usage[key] = int(m.group(1).replace(",", ""))
    return usage


def run_benchmark(cfg: dict[str, Any], only_tasks: list[str] | None, only_models: list[str] | None,
                  run_dir: Path | None = None) -> Path:
    tasks = [t for t in load_tasks() if not only_tasks or t.id in only_tasks or t.category in only_tasks]
    models = [m for m in cfg["models"] if not only_models or m["id"] in only_models]
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    run_dir = run_dir or RESULTS / stamp
    run_dir.mkdir(parents=True, exist_ok=True)
    out = run_dir / "raw.jsonl"
    total = len(tasks) * len(models) * cfg.get("runs", 3)
    n = 0
    with out.open("a", encoding="utf-8") as fh:
        for task in tasks:
            prompt = build_prompt(task, cfg.get("context_root"))
            for model in models:
                for run in range(cfg.get("runs", 3)):
                    n += 1
                    print(f"[{n}/{total}] {task.id} | {model['id']} | run {run + 1}", flush=True)
                    call = call_model(cfg, model["id"], prompt)
                    rec = {
                        "task": task.id,
                        "category": task.category,
                        "model": model["id"],
                        "multiplier": model.get("multiplier", 1.0),
                        "run": run,
                        "ok": call.ok,
                        "error": call.error,
                        "seconds": round(call.seconds, 2),
                        "chars": len(call.output),
                        "tokens_est": estimate_tokens(call.output),
                        "prompt_tokens_est": estimate_tokens(prompt),
                        "usage": parse_usage(call.output),
                        "output": call.output,
                    }
                    fh.write(json.dumps(rec, ensure_ascii=False) + "\n")
                    fh.flush()
    print(f"Raw results: {out}")
    return run_dir


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    return [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
