# copilot-model-bench

A personal benchmark to compare AI models available through the GitHub Copilot CLI on:
**speed**, **cost**, **output quality** and **output length**, with a focus on software
engineering in **healthcare** (HL7, point-of-care testing, bacteriology...).

Pure PHP (>= 7.4), no Composer dependencies.

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

1. `run` calls each model on each task `runs` times via `copilot -p ... --model ...`
   (no tools enabled, context is inlined: identical prompts, no side effects). Records
   wall-clock time, estimated tokens, premium-request multiplier and the raw output.
2. `score` applies objective checks (regex rubric, forbidden claims, cited-path existence,
   PHP lint + hidden tests) and a **blind LLM judge** (per-criterion scores, hallucination
   penalty, length ignored).
3. `report` prints a table and writes `report.html`: composite and per-category quality
   (median over runs), speed, tokens/s, length, quality per premium request, quality per 1k
   tokens, spread across runs and failures.

Quality = 0.5 x objective + 0.5 x judge. Spot-check ~20% of judged answers yourself: LLM
judges tend to favour verbose answers and their own model family.

## Usage

```
copy config.example.php config.php    # edit models, multipliers, judge, context_root
php bin/cmb.php list
php bin/cmb.php run --task healthcare_jargon --model claude-sonnet-5.5
php bin/cmb.php score
php bin/cmb.php report
```

Requires the Copilot CLI on PATH; adjust `command` in `config.php` if your flags differ.
Prompts are passed as an argument, so very large context files may hit the Windows
command-line length limit (~32k chars).

## Adding tasks

Add PHP files in `tasks/` (public, generic) or `private/` (gitignored; for tasks based on
proprietary code via `context_files` relative to `context_root`). Each file returns
`array('tasks' => [...])`; see existing files for fields: `id`, `category`, `prompt`,
`inline_context`, `context_files`, `rubric` (`patterns` are regexes without delimiters or
backticks, any match satisfies the item), `forbidden`, `check_paths`, `code_test`, `use_judge`.

## Caveats

- Token counts are estimated (chars/4) unless the CLI prints usage; premium multipliers are
  user-maintained in the config.
- Regex rubrics are a cheap proxy; the judge and your own review are the authority.
- Keep a held-out set of tasks so you do not tune prompts toward the benchmark.
- Never commit proprietary code or real patient data.
