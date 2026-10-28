<?php
// Calls a model through the Copilot CLI and records speed / cost / length.

function estimate_tokens($text)
{
    // Rough, model-agnostic estimate (about 4 chars per token) so models stay comparable.
    return max(1, (int) round(strlen($text) / 4));
}

/** Returns array(ok, output, seconds, error). */
function call_model(array $cfg, $model, $prompt)
{
    $cmd = array();
    foreach ($cfg['command'] as $a) {
        $cmd[] = str_replace(array('{prompt}', '{model}'), array($prompt, $model), $a);
    }
    $start = microtime(true);
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($proc)) {
        return array('ok' => false, 'output' => '', 'seconds' => 0.0, 'error' => 'cannot start command');
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $err = '';
    $timeout = isset($cfg['timeout_s']) ? $cfg['timeout_s'] : 600;
    while (true) {
        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        $st = proc_get_status($proc);
        if (!$st['running']) {
            break;
        }
        if (microtime(true) - $start > $timeout) {
            proc_terminate($proc);
            return array('ok' => false, 'output' => $out, 'seconds' => microtime(true) - $start, 'error' => 'timeout');
        }
        usleep(100000);
    }
    $out .= stream_get_contents($pipes[1]);
    $err .= stream_get_contents($pipes[2]);
    $code = $st['exitcode'];
    proc_close($proc);
    $seconds = microtime(true) - $start;
    if ($code !== 0) {
        return array('ok' => false, 'output' => $out, 'seconds' => $seconds, 'error' => substr(trim($err), 0, 500));
    }
    return array('ok' => true, 'output' => trim($out), 'seconds' => $seconds, 'error' => '');
}

/** Best-effort extraction of token usage if the CLI prints it. */
function parse_usage($raw)
{
    $u = array();
    if (preg_match('/input[_ ]tokens\D+(\d[\d,]*)/i', $raw, $m)) {
        $u['input_tokens'] = (int) str_replace(',', '', $m[1]);
    }
    if (preg_match('/output[_ ]tokens\D+(\d[\d,]*)/i', $raw, $m)) {
        $u['output_tokens'] = (int) str_replace(',', '', $m[1]);
    }
    return $u;
}

function run_benchmark(array $cfg, array $onlyTasks, array $onlyModels)
{
    $tasks = array_filter(load_tasks(), function ($t) use ($onlyTasks) {
        return !$onlyTasks || in_array($t['id'], $onlyTasks, true) || in_array($t['category'], $onlyTasks, true);
    });
    $models = array_filter($cfg['models'], function ($m) use ($onlyModels) {
        return !$onlyModels || in_array($m['id'], $onlyModels, true);
    });
    $runs = isset($cfg['runs']) ? $cfg['runs'] : 3;
    $dir = root_dir() . '/results/' . gmdate('Ymd-His');
    mkdir($dir, 0777, true);
    $fh = fopen($dir . '/raw.jsonl', 'a');
    $total = count($tasks) * count($models) * $runs;
    $n = 0;
    foreach ($tasks as $task) {
        $prompt = build_prompt($task, $cfg);
        foreach ($models as $model) {
            for ($run = 0; $run < $runs; $run++) {
                $n++;
                echo "[$n/$total] {$task['id']} | {$model['id']} | run " . ($run + 1) . "\n";
                $c = call_model($cfg, $model['id'], $prompt);
                $rec = array(
                    'task' => $task['id'], 'category' => $task['category'], 'model' => $model['id'],
                    'multiplier' => isset($model['multiplier']) ? $model['multiplier'] : 1.0,
                    'run' => $run, 'ok' => $c['ok'], 'error' => $c['error'],
                    'seconds' => round($c['seconds'], 2), 'chars' => strlen($c['output']),
                    'tokens_est' => estimate_tokens($c['output']),
                    'prompt_tokens_est' => estimate_tokens($prompt),
                    'usage' => (object) parse_usage($c['output']), 'output' => $c['output'],
                );
                fwrite($fh, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
            }
        }
    }
    fclose($fh);
    echo "Raw results: $dir/raw.jsonl\n";
    return basename($dir);
}

function read_jsonl($path)
{
    $recs = array();
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $recs[] = json_decode($line, true);
    }
    return $recs;
}
