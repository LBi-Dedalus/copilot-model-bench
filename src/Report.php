<?php
// Aggregation and reporting (console table + standalone HTML).

function quality(array $r)
{
    $a = isset($r['auto']['auto']) ? $r['auto']['auto'] : null;
    $j = isset($r['judge']['judge']) ? $r['judge']['judge'] : null;
    if ($a !== null && $j !== null) {
        return 0.5 * $a + 0.5 * $j;
    }
    return $a !== null ? $a : ($j !== null ? $j : 0.0);
}

function median(array $v)
{
    if (!$v) {
        return 0.0;
    }
    sort($v);
    $n = count($v);
    return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
}

function stdev(array $v)
{
    $n = count($v);
    if ($n < 2) {
        return 0.0;
    }
    $mean = array_sum($v) / $n;
    $s = 0.0;
    foreach ($v as $x) {
        $s += ($x - $mean) ** 2;
    }
    return sqrt($s / $n);
}

function aggregate(array $records, array $weights)
{
    $by = array();
    foreach ($records as $r) {
        $by[$r['model']][] = $r;
    }
    $out = array();
    foreach ($by as $model => $recs) {
        $ok = array_values(array_filter($recs, function ($r) {
            return $r['ok'];
        }));
        $cat = array();
        foreach (CATEGORIES as $c) {
            $vals = array();
            foreach ($ok as $r) {
                if ($r['category'] === $c) {
                    $vals[] = quality($r);
                }
            }
            if ($vals) {
                $cat[$c] = median($vals);
            }
        }
        $wsum = 0.0;
        $comp = 0.0;
        foreach ($cat as $c => $v) {
            $w = isset($weights[$c]) ? $weights[$c] : 1.0;
            $wsum += $w;
            $comp += $v * $w;
        }
        $qs = array_map('quality', $ok);
        $secs = array_column($ok, 'seconds');
        $toks = array_column($ok, 'tokens_est');
        $premium = array_sum(array_column($recs, 'multiplier'));
        sort($secs);
        $out[$model] = array(
            'composite' => $wsum ? $comp / $wsum : 0.0,
            'categories' => $cat,
            'median_s' => median($secs),
            'p90_s' => $secs ? $secs[(int) (0.9 * (count($secs) - 1))] : 0,
            'median_tokens' => median($toks),
            'tok_per_s' => array_sum($secs) ? array_sum($toks) / array_sum($secs) : 0,
            'spread' => stdev($qs),
            'premium_requests' => $premium,
            'quality_per_premium' => $premium ? array_sum($qs) / $premium : 0,
            'density' => array_sum($toks) ? array_sum($qs) / (array_sum($toks) / 1000) : 0,
            'failures' => count($recs) - count($ok),
        );
    }
    uasort($out, function ($a, $b) {
        return $b['composite'] <=> $a['composite'];
    });
    return $out;
}

function console_table(array $agg)
{
    $rows = array(array('model', 'composite', 'median_s', 'tok/s', 'med_tok', 'premium', 'q/prem', 'density', 'spread', 'fail'));
    foreach ($agg as $m => $a) {
        $rows[] = array($m, sprintf('%.3f', $a['composite']), sprintf('%.1f', $a['median_s']),
            sprintf('%.1f', $a['tok_per_s']), sprintf('%.0f', $a['median_tokens']),
            sprintf('%.1f', $a['premium_requests']), sprintf('%.3f', $a['quality_per_premium']),
            sprintf('%.3f', $a['density']), sprintf('%.3f', $a['spread']), (string) $a['failures']);
    }
    $w = array();
    foreach ($rows as $r) {
        foreach ($r as $i => $c) {
            $w[$i] = max(isset($w[$i]) ? $w[$i] : 0, strlen($c));
        }
    }
    $lines = array();
    foreach ($rows as $r) {
        $cells = array();
        foreach ($r as $i => $c) {
            $cells[] = str_pad($c, $w[$i]);
        }
        $lines[] = implode('  ', $cells);
    }
    return implode("\n", $lines);
}

function html_report(array $agg, $path)
{
    $h = function ($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'ISO-8859-1');
    };
    $head = '';
    foreach (array_merge(array('Model', 'Composite'), CATEGORIES,
        array('Median s', 'Tok/s', 'Median tokens', 'Premium req', 'Quality/premium', 'Quality/1k tok', 'Spread', 'Failures')) as $c) {
        $head .= '<th>' . $h($c) . '</th>';
    }
    $body = '';
    foreach ($agg as $m => $a) {
        $cats = '';
        foreach (CATEGORIES as $c) {
            $cats .= '<td>' . (isset($a['categories'][$c]) ? sprintf('%.2f', $a['categories'][$c]) : '-') . '</td>';
        }
        $body .= '<tr><td>' . $h($m) . '</td><td><b>' . sprintf('%.3f', $a['composite']) . '</b></td>' . $cats
            . sprintf('<td>%.1f</td><td>%.1f</td><td>%.0f</td><td>%.1f</td><td>%.3f</td><td>%.3f</td><td>%.3f</td><td>%d</td></tr>',
                $a['median_s'], $a['tok_per_s'], $a['median_tokens'], $a['premium_requests'],
                $a['quality_per_premium'], $a['density'], $a['spread'], $a['failures']);
    }
    file_put_contents($path, "<!doctype html><meta charset='iso-8859-1'><title>Model benchmark</title>"
        . '<style>body{font-family:sans-serif;margin:2em}table{border-collapse:collapse}'
        . 'td,th{border:1px solid #ccc;padding:4px 8px;text-align:right}td:first-child{text-align:left}</style>'
        . "<h1>Model benchmark</h1><table><tr>$head</tr>$body</table>"
        . '<p>Quality = 0.5 x objective checks + 0.5 x blind judge (0..1). Tokens are estimated (chars/4).</p>');
}
