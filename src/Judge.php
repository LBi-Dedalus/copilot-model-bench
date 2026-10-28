<?php
// Blind LLM judge: scores an answer 0-5 per rubric item without knowing the author model.

function judge_answer(array $cfg, array $task, $prompt, $answer)
{
    $criteria = array();
    foreach ($task['rubric'] as $r) {
        $criteria[] = "- {$r['id']}: {$r['description']}";
    }
    $msg = "You are a strict, impartial evaluator of answers to technical questions.\n"
        . "Do not reward length or confident tone; reward only correctness, specificity and completeness.\n"
        . "Penalise invented facts, files, functions or standards.\n\n"
        . "TASK GIVEN TO THE ANSWERER:\n" . substr($prompt, 0, 6000) . "\n\n"
        . "EVALUATION CRITERIA:\n" . implode("\n", $criteria) . "\n\n"
        . "ANSWER TO EVALUATE:\n<<<\n" . substr($answer, 0, 12000) . "\n>>>\n\n"
        . 'Reply with ONLY a JSON object: {"scores": {"<criterion id>": <0-5 integer>, ...}, '
        . '"hallucinations": <integer count>, "comment": "<one sentence>"}';
    $c = call_model($cfg, $cfg['judge']['model'], $msg);
    if (!$c['ok']) {
        return array('judge' => null, 'error' => $c['error']);
    }
    if (!preg_match('/\{.*\}/s', $c['output'], $m)) {
        return array('judge' => null, 'error' => 'no json');
    }
    $data = json_decode($m[0], true);
    if (!is_array($data)) {
        return array('judge' => null, 'error' => 'bad json');
    }
    $scores = isset($data['scores']) && is_array($data['scores']) ? $data['scores'] : array();
    $total = 0.0;
    $got = 0.0;
    foreach ($task['rubric'] as $r) {
        $total += $r['points'];
        $s = isset($scores[$r['id']]) ? (float) $scores[$r['id']] : 0.0;
        $got += min(max($s, 0.0), 5.0) / 5 * $r['points'];
    }
    $h = isset($data['hallucinations']) ? (int) $data['hallucinations'] : 0;
    $score = max(0.0, ($total ? $got / $total : 0.0) - 0.1 * $h);
    return array('judge' => round($score, 4), 'hallucinations' => $h,
        'comment' => isset($data['comment']) ? $data['comment'] : '');
}
