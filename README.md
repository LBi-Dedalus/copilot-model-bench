# copilot-model-bench

A personal benchmark to compare AI models available through the GitHub Copilot CLI on:
**speed**, **cost**, **output quality** and **output length**, with a focus on software
engineering in **healthcare** (HL7, point-of-care testing, bacteriology...).

## Categories

| Category | What is tested |
|---|---|
| comprehension | Tracing code flows, citing correct lines, no invented paths |
| review | Finding seeded bugs (precision / recall) in realistic code |
| planning | Ordered, convention-aware implementation plans |
| code_writing | PHP functions validated by hidden test harnesses |
| architecture | Trade-offs, failure modes, data integrity |
| healthcare_jargon | HL7, POCT, bacteriology, including false-premise trap questions |

## How it works

1. `cmb run` calls each model on each task `runs` times via `copilot -p ... --model ...`
   (no tools enabled, context is inlined, so identical prompts and no side effects).
   Records wall-clock time, estimated tokens, premium-request multiplier and the raw output.
2. `cmb score` applies objective checks (regex rubric, forbidden claims, cited-path existence,
   PHP lint + hidden tests) and a **blind LLM judge** (scores per rubric item, penalises
   hallucinations, ignores length).
3. `cmb report` prints a table and writes `report.html`: composite and per-category quality
   (median over runs), speed, tokens/s, length, quality per premium request, quality per 1k tokens,
   spread across runs and failures.

Quality = 0.5 x objective + 0.5 x judge. Spot-check ~20% of judged answers yourself:
LLM judges tend to favour verbose answers and their own family.

## Setup

```
pip install -e .
copy config.example.yaml config.yaml   # edit models, multipliers, judge, context_root
cmb list
cmb run --task healthcare_jargon --model claude-sonnet-5.5
cmb score
cmb report
```

Requires the Copilot CLI on PATH. Adjust the `command` template in `config.yaml` if your CLI
flags differ. Code tasks need `php` on PATH (or set `php` in the config).

## Adding tasks

Add YAML files in `tasks/` (public, generic) or `private/` (gitignored; use for tasks based on
proprietary code, referenced through `context_files` relative to `context_root`). See existing
files for the schema: `id`, `category`, `prompt`, `inline_context`, `context_files`, `rubric`
(`patterns` are regexes, any match satisfies the item), `forbidden`, `check_paths`, `code_test`.

## Caveats

- Token counts are estimated (chars/4) unless the CLI prints usage; premium multipliers are
  user-maintained in the config.
- Regex rubrics are a cheap proxy; the judge and your own review are the authority.
- Keep a held-out set of tasks so you do not tune prompts toward the benchmark.
- Never commit proprietary code or real patient data.
