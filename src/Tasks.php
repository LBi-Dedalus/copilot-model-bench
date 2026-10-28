<?php
// Task and config loading. PHP >= 7.4, no dependencies.

const CATEGORIES = array('comprehension', 'review', 'planning', 'code_writing', 'architecture', 'healthcare_jargon');

function root_dir()
{
    return dirname(__DIR__);
}

function load_config($path = null)
{
    $p = $path ? $path : root_dir() . '/config.php';
    if (!is_file($p)) {
        fwrite(STDERR, "Missing $p. Copy config.example.php to config.php and edit it.\n");
        exit(1);
    }
    return require $p;
}

/** Each task file returns array('tasks' => [...]). Public tasks in tasks/, private ones in private/. */
function load_tasks()
{
    $tasks = array();
    foreach (array(root_dir() . '/tasks', root_dir() . '/private') as $dir) {
        foreach (glob($dir . '/*.php') ?: array() as $f) {
            $data = require $f;
            foreach ($data['tasks'] as $t) {
                $t += array(
                    'inline_context' => '', 'context_files' => array(), 'forbidden' => array(),
                    'check_paths' => false, 'code_test' => null, 'use_judge' => true, 'rubric' => array(),
                );
                if (!in_array($t['category'], CATEGORIES, true)) {
                    fwrite(STDERR, "$f: unknown category {$t['category']} in task {$t['id']}\n");
                    exit(1);
                }
                foreach ($t['rubric'] as &$r) {
                    $r += array('points' => 1.0, 'patterns' => array());
                }
                unset($r);
                $tasks[$t['id']] = $t;
            }
        }
    }
    return $tasks;
}

/** Identical prompt for every model; context is inlined so no tools are needed. */
function build_prompt(array $task, array $cfg)
{
    $parts = array(trim($task['prompt']));
    if ($task['inline_context'] !== '') {
        $parts[] = "\n--- CONTEXT ---\n" . trim($task['inline_context']);
    }
    foreach ($task['context_files'] as $rel) {
        if (empty($cfg['context_root'])) {
            fwrite(STDERR, "Task {$task['id']} needs context_root in config.php\n");
            exit(1);
        }
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($cfg['context_root'] . '/' . $rel));
        $numbered = array();
        foreach ($lines as $i => $ln) {
            $numbered[] = ($i + 1) . ': ' . $ln;
        }
        $parts[] = "\n--- FILE: $rel (line-numbered) ---\n" . implode("\n", $numbered);
    }
    return implode("\n", $parts);
}

/** Case-insensitive, dot matches newline. Patterns must not contain a backtick. */
function re_match($pattern, $text)
{
    return preg_match('`' . $pattern . '`is', $text) === 1;
}
