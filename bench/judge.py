"""Blind LLM judge: scores an answer 0-5 per rubric item, without knowing the author model."""
from __future__ import annotations

import json
import random
import re
from typing import Any

from .runner import call_model
from .tasks import Task

JUDGE_TEMPLATE = """You are a strict, impartial evaluator of answers to technical questions.
Do not reward length or confident tone; reward only correctness, specificity and completeness.
Penalise invented facts, files, functions or standards.

TASK GIVEN TO THE ANSWERER:
{prompt}

EVALUATION CRITERIA:
{criteria}

ANSWER TO EVALUATE:
<<<
{answer}
>>>

Reply with ONLY a JSON object: {{"scores": {{"<criterion id>": <0-5 integer>, ...}}, "hallucinations": <integer count>, "comment": "<one sentence>"}}"""


def judge_answer(cfg: dict[str, Any], task: Task, prompt: str, answer: str) -> dict[str, Any]:
    criteria = "\n".join(f"- {r.id}: {r.description}" for r in task.rubric)
    msg = JUDGE_TEMPLATE.format(prompt=prompt[:6000], criteria=criteria, answer=answer[:12000])
    call = call_model(cfg, cfg["judge"]["model"], msg)
    if not call.ok:
        return {"judge": None, "error": call.error}
    m = re.search(r"\{.*\}", call.output, re.DOTALL)
    if not m:
        return {"judge": None, "error": "no json"}
    try:
        data = json.loads(m.group(0))
    except json.JSONDecodeError:
        return {"judge": None, "error": "bad json"}
    scores = data.get("scores", {})
    maxes = {r.id: r.points for r in task.rubric}
    total = sum(maxes.values()) or 1.0
    got = sum(min(max(float(scores.get(i, 0)), 0), 5) / 5 * p for i, p in maxes.items())
    h = int(data.get("hallucinations", 0) or 0)
    score = max(0.0, got / total - 0.1 * h)
    return {"judge": round(score, 4), "hallucinations": h, "comment": data.get("comment", "")}


def shuffled(items: list[Any], seed: int = 0) -> list[Any]:
    """Judge answers in random order so position/model order cannot leak."""
    r = random.Random(seed)
    items = list(items)
    r.shuffle(items)
    return items
