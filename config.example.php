<?php
// Copy to config.php (gitignored) and adjust.
return array(
    // Command used to call a model. {prompt} and {model} are substituted.
    // No tools are enabled: context is inlined in the prompt, so nothing can be modified.
    'command' => array('copilot', '-p', '{prompt}', '--model', '{model}'),
    'timeout_s' => 600,
    // Runs per (model, task). Use 3+ to see variance.
    'runs' => 3,
    // Models to compare. Keep the premium-request multipliers up to date (placeholders here).
    'models' => array(
        array('id' => 'claude-sonnet-5.5', 'multiplier' => 1.0),
        array('id' => 'gpt-6.1-sol', 'multiplier' => 1.0),
        array('id' => 'gemini-4-argon', 'multiplier' => 1.0),
    ),
    // Judge model: strong, and ideally not one of the contenders (self-preference bias).
    'judge' => array('model' => 'claude-opus-5.5'),
    // Root used to resolve "context_files" of tasks (e.g. a local checkout of a project).
    'context_root' => 'C:/path/to/your/project',
    // PHP binary used to run hidden code-writing tests.
    'php' => 'php',
    // Category weights for the composite score.
    'weights' => array(
        'comprehension' => 1.0,
        'review' => 1.0,
        'planning' => 1.0,
        'code_writing' => 1.0,
        'architecture' => 1.0,
        'healthcare_jargon' => 1.0,
    ),
);
