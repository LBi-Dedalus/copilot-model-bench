<?php
// Usage: php bin/cmb.php list | run [--task a b] [--model x y] | score [--run ID] [--no-judge] | report [--run ID]
require __DIR__ . '/../src/Tasks.php';
require __DIR__ . '/../src/Runner.php';
require __DIR__ . '/../src/Scoring.php';
require __DIR__ . '/../src/Judge.php';
require __DIR__ . '/../src/Report.php';

function parse_opts(array $argv)
{
    $o = array();
    $cur = null;
    foreach ($argv as $a) {
        if (substr($a, 0, 2) === '--') {
            $cur = substr($a, 2);
            $o[$cur] = array();
        } elseif ($cur !== null) {
            $o[$cur][] = $a;
        }
    }
    return $o;
}

function results_dir()
{
    return root_dir() . '/results';
}

function resolve_run(array $o)
{
    if (!empty($o['run'][0])) {
        return results_dir() . '/' . $o['run'][0];
    }
    $runs = glob(results_dir() . '/*/raw.jsonl') ?: array();
    if (!$runs) {
        fwrite(STDERR, "No results yet. Run `php bin/cmb.php run` first.\n");
        exit(1);
    }
    sort($runs);
    return dirname(end($runs));
}

$cmd = isset($argv[1]) ? $argv[1] : '';
$o = parse_opts(array_slice($argv, 2));

if ($cmd === 'list') {
    foreach (load_tasks() as $t) {
        printf("%-18s %s\n", $t['category'], $t['id']);
    }
} elseif ($cmd === 'run') {
    $cfg = load_config();
    $id = run_benchmark($cfg, isset($o['task']) ? $o['task'] : array(), isset($o['model']) ? $o['model'] : array());
    echo "Next: php bin/cmb.php score --run $id\n";
} elseif ($cmd === 'score') {
    $cfg = load_config();
    $dir = resolve_run($o);
    $tasks = load_tasks();
    $recs = read_jsonl($dir . '/raw.jsonl');
    $fh = fopen($dir . '/scored.jsonl', 'w');
    foreach ($recs as $i => $r) {
        if (isset($tasks[$r['task']]) && $r['ok']) {
            $t = $tasks[$r['task']];
            echo '[' . ($i + 1) . '/' . count($recs) . "] scoring {$r['task']} | {$r['model']}\n";
            $r['auto'] = auto_score($t, $r['output'], $cfg);
            if ($t['use_judge'] && !isset($o['no-judge'])) {
                $r['judge'] = judge_answer($cfg, $t, build_prompt($t, $cfg), $r['output']);
            }
        }
        fwrite($fh, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    fclose($fh);
    echo "Scored: $dir/scored.jsonl\n";
} elseif ($cmd === 'report') {
    $cfg = load_config();
    $dir = resolve_run($o);
    if (!is_file($dir . '/scored.jsonl')) {
        fwrite(STDERR, "Run `php bin/cmb.php score` first.\n");
        exit(1);
    }
    $agg = aggregate(read_jsonl($dir . '/scored.jsonl'), isset($cfg['weights']) ? $cfg['weights'] : array());
    echo console_table($agg) . "\n";
    html_report($agg, $dir . '/report.html');
    echo "\nHTML: $dir/report.html\n";
} else {
    echo "Usage: php bin/cmb.php list | run | score | report\n";
    exit(1);
}
