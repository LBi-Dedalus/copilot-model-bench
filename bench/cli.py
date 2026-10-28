"""Command line: cmb run | score | report | list."""
from __future__ import annotations

import argparse
import json
from pathlib import Path

from . import report as rpt
from .judge import judge_answer
from .runner import RESULTS, read_jsonl, run_benchmark
from .scoring import auto_score
from .tasks import build_prompt, load_config, load_tasks


def latest_run() -> Path:
    runs = sorted(p for p in RESULTS.glob("*") if (p / "raw.jsonl").exists())
    if not runs:
        raise SystemExit("No results yet. Run `cmb run` first.")
    return runs[-1]


def cmd_list(args, cfg) -> None:
    for t in load_tasks():
        print(f"{t.category:18} {t.id}")


def cmd_run(args, cfg) -> None:
    d = run_benchmark(cfg, args.task, args.model)
    print(f"Next: cmb score --run {d.name}")


def cmd_score(args, cfg) -> None:
    run_dir = RESULTS / args.run if args.run else latest_run()
    tasks = {t.id: t for t in load_tasks()}
    recs = read_jsonl(run_dir / "raw.jsonl")
    out = run_dir / "scored.jsonl"
    with out.open("w", encoding="utf-8") as fh:
        for i, r in enumerate(recs, 1):
            t = tasks.get(r["task"])
            if t is None or not r["ok"]:
                fh.write(json.dumps(r, ensure_ascii=False) + "\n")
                continue
            print(f"[{i}/{len(recs)}] scoring {r['task']} | {r['model']}", flush=True)
            r["auto"] = auto_score(t, r["output"], cfg)
            if t.use_judge and not args.no_judge:
                r["judge"] = judge_answer(cfg, t, build_prompt(t, cfg.get("context_root")), r["output"])
            fh.write(json.dumps(r, ensure_ascii=False) + "\n")
    print(f"Scored: {out}")


def cmd_report(args, cfg) -> None:
    run_dir = RESULTS / args.run if args.run else latest_run()
    path = run_dir / "scored.jsonl"
    if not path.exists():
        raise SystemExit("Run `cmb score` first.")
    agg = rpt.aggregate(read_jsonl(path), cfg.get("weights", {}))
    print(rpt.console_table(agg))
    rpt.html_report(agg, run_dir / "report.html")
    print(f"\nHTML: {run_dir / 'report.html'}")


def main() -> None:
    ap = argparse.ArgumentParser(prog="cmb", description="Copilot model benchmark")
    ap.add_argument("--config", help="Path to config.yaml")
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("list").set_defaults(fn=cmd_list)
    p = sub.add_parser("run")
    p.add_argument("--task", nargs="*", help="Task ids or categories")
    p.add_argument("--model", nargs="*", help="Model ids")
    p.set_defaults(fn=cmd_run)
    p = sub.add_parser("score")
    p.add_argument("--run")
    p.add_argument("--no-judge", action="store_true")
    p.set_defaults(fn=cmd_score)
    p = sub.add_parser("report")
    p.add_argument("--run")
    p.set_defaults(fn=cmd_report)
    args = ap.parse_args()
    cfg = load_config(args.config) if args.cmd != "list" else {}
    args.fn(args, cfg)


if __name__ == "__main__":
    main()
