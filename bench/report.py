"""Aggregation and reporting (console table + standalone HTML)."""
from __future__ import annotations

import html
import statistics as st
from collections import defaultdict
from pathlib import Path
from typing import Any

from .tasks import CATEGORIES


def quality(rec: dict[str, Any]) -> float:
    """Blend objective and judge scores; falls back to whichever is available."""
    a = rec.get("auto", {}).get("auto")
    j = rec.get("judge", {}).get("judge")
    if a is not None and j is not None:
        return 0.5 * a + 0.5 * j
    return a if a is not None else (j if j is not None else 0.0)


def aggregate(records: list[dict[str, Any]], weights: dict[str, float]) -> dict[str, Any]:
    by_model: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for r in records:
        by_model[r["model"]].append(r)
    out: dict[str, Any] = {}
    for model, recs in by_model.items():
        ok = [r for r in recs if r["ok"]]
        cat_q: dict[str, float] = {}
        for c in CATEGORIES:
            vals = [quality(r) for r in ok if r["category"] == c]
            if vals:
                cat_q[c] = st.median(vals)
        wsum = sum(weights.get(c, 1.0) for c in cat_q) or 1.0
        composite = sum(v * weights.get(c, 1.0) for c, v in cat_q.items()) / wsum
        qs = [quality(r) for r in ok]
        secs = [r["seconds"] for r in ok]
        toks = [r["tokens_est"] for r in ok]
        premium = sum(r["multiplier"] for r in recs)
        out[model] = {
            "composite": composite,
            "categories": cat_q,
            "median_s": st.median(secs) if secs else 0,
            "p90_s": sorted(secs)[int(0.9 * (len(secs) - 1))] if secs else 0,
            "median_tokens": st.median(toks) if toks else 0,
            "tok_per_s": (sum(toks) / sum(secs)) if secs and sum(secs) else 0,
            "spread": st.pstdev(qs) if len(qs) > 1 else 0,
            "premium_requests": premium,
            "quality_per_premium": (sum(qs) / premium) if premium else 0,
            "density": (sum(qs) / (sum(toks) / 1000)) if toks and sum(toks) else 0,
            "failures": len(recs) - len(ok),
            "n": len(recs),
        }
    return out


def console_table(agg: dict[str, Any]) -> str:
    cols = ["model", "composite", "median_s", "tok/s", "med_tok", "premium", "q/prem", "density", "spread", "fail"]
    rows = [cols]
    for m, a in sorted(agg.items(), key=lambda kv: -kv[1]["composite"]):
        rows.append([m, f"{a['composite']:.3f}", f"{a['median_s']:.1f}", f"{a['tok_per_s']:.1f}",
                     f"{a['median_tokens']:.0f}", f"{a['premium_requests']:.1f}",
                     f"{a['quality_per_premium']:.3f}", f"{a['density']:.3f}", f"{a['spread']:.3f}",
                     str(a["failures"])])
    w = [max(len(r[i]) for r in rows) for i in range(len(cols))]
    return "\n".join("  ".join(c.ljust(w[i]) for i, c in enumerate(r)) for r in rows)


def html_report(agg: dict[str, Any], path: Path) -> None:
    head = "".join(f"<th>{html.escape(c)}</th>" for c in
                   ["Model", "Composite", *CATEGORIES, "Median s", "Tok/s", "Median tokens",
                    "Premium req", "Quality/premium", "Quality/1k tok", "Spread", "Failures"])
    body = ""
    for m, a in sorted(agg.items(), key=lambda kv: -kv[1]["composite"]):
        cats = "".join(f"<td>{a['categories'].get(c, float('nan')):.2f}</td>" for c in CATEGORIES)
        body += (f"<tr><td>{html.escape(m)}</td><td><b>{a['composite']:.3f}</b></td>{cats}"
                 f"<td>{a['median_s']:.1f}</td><td>{a['tok_per_s']:.1f}</td><td>{a['median_tokens']:.0f}</td>"
                 f"<td>{a['premium_requests']:.1f}</td><td>{a['quality_per_premium']:.3f}</td>"
                 f"<td>{a['density']:.3f}</td><td>{a['spread']:.3f}</td><td>{a['failures']}</td></tr>")
    path.write_text(
        "<!doctype html><meta charset='utf-8'><title>Model benchmark</title>"
        "<style>body{font-family:sans-serif;margin:2em}table{border-collapse:collapse}"
        "td,th{border:1px solid #ccc;padding:4px 8px;text-align:right}td:first-child{text-align:left}"
        "</style><h1>Model benchmark</h1>"
        f"<table><tr>{head}</tr>{body}</table>"
        "<p>Quality = 0.5 x objective checks + 0.5 x blind judge (0..1). Tokens are estimated (chars/4).</p>",
        encoding="utf-8",
    )
