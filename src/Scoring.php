<?php
// Objective scoring: rubric patterns, forbidden claims, path existence, hidden PHP tests.

function rubric_score(array $task, $output)
{
    $total = 0.0;
    $got = 0.0;
    $hits = array();
    $missed = array();
    foreach ($task['rubric'] as $item) {
        $total += $item['points'];
        $hit = false;
        foreach ($item['patterns'] as $p) {
            if (re_match($p, $output)) {
                $hit = true;
                break;
            }
        }
        if ($hit) {
            $got += $item['points'];
            $hits[] = $item['id'];
        } else {
            $missed[] = $item['id'];
        }
    }
    return array('rubric' => $total ? $got / $total : 0.0, 'hits' => $hits, 'missed' => $missed);
}

function path_accuracy($output, $root, array $known)
{
    preg_match_all('~[\w./\\\\-]+\.(?:php|js|py|ts|yaml|yml|json|md|sql|xml|html|twig)\b~', $output, $m);
    $cited = array();
    foreach ($m[0] as $c) {
        $cited[ltrim(str_replace('\\', '/', $c), './')] = true;
    }
    if (!$cited || !$root) {
        return array('cited' => 0, 'valid' => 0, 'invalid' => array());
    }
    $valid = 0;
    $invalid = array();
    foreach (array_keys($cited) as $c) {
        $ok = file_exists($root . '/' . $c);
        foreach ($known as $k) {
            if (substr($k, -strlen($c)) === $c || substr($c, -strlen($k)) === $k) {
                $ok = true;
            }
        }
        if ($ok) {
            $valid++;
        } else {
            $invalid[] = $c;
        }
    }
    return array('cited' => count($cited), 'valid' => $valid, 'invalid' => $invalid);
}

/** Extracts the last code block, lints it, runs the hidden harness. The harness prints "PASSED n/m". */
function run_code_test(array $task, $output, $php)
{
    if (!preg_match_all('/```(?:php)?[ \t]*\r?\n(.*?)```/is', $output, $m) || !$m[1]) {
        return array('extracted' => false, 'lint' => false, 'tests' => 0.0);
    }
    $code = trim(end($m[1]));
    if (strpos($code, '<?php') !== 0) {
        $code = "<?php\n" . $code;
    }
    $dir = sys_get_temp_dir() . '/cmb_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/solution.php', $code . "\n");
    file_put_contents($dir . '/harness.php', $task['code_test']['harness']);
    $lint = shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($dir . '/solution.php') . ' 2>&1');
    $res = array('extracted' => true, 'lint' => false, 'tests' => 0.0);
    if (strpos((string) $lint, 'No syntax errors') !== false) {
        $res['lint'] = true;
        $out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($dir . '/harness.php') . ' '
            . escapeshellarg($dir . '/solution.php') . ' 2>&1');
        if (preg_match('/PASSED\s+(\d+)\/(\d+)/', (string) $out, $mm) && (int) $mm[2] > 0) {
            $res['tests'] = $mm[1] / $mm[2];
        }
        $res['detail'] = substr((string) $out, -300);
    }
    @unlink($dir . '/solution.php');
    @unlink($dir . '/harness.php');
    @rmdir($dir);
    return $res;
}

function auto_score(array $task, $output, array $cfg)
{
    $res = rubric_score($task, $output);
    $forbidden = array();
    foreach ($task['forbidden'] as $p) {
        if (re_match($p, $output)) {
            $forbidden[] = $p;
        }
    }
    $res['forbidden'] = $forbidden;
    $q = max(0.0, $res['rubric'] - 0.25 * count($forbidden));
    if ($task['check_paths']) {
        $pa = path_accuracy($output, isset($cfg['context_root']) ? $cfg['context_root'] : null, $task['context_files']);
        $res['paths'] = $pa;
        if ($pa['cited']) {
            $q *= 0.5 + 0.5 * $pa['valid'] / $pa['cited'];
        }
    }
    if ($task['code_test']) {
        $ct = run_code_test($task, $output, isset($cfg['php']) ? $cfg['php'] : 'php');
        $res['code'] = $ct;
        $q = $ct['lint'] ? 0.7 * $ct['tests'] + 0.3 * $q : 0.3 * $q;
    }
    $res['auto'] = round($q, 4);
    return $res;
}
