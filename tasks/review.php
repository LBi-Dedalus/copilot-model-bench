<?php
return array('tasks' => array(
    array(
        'id' => 'review-seeded-php',
        'category' => 'review',
        'prompt' => <<<'TXT'
Review this PHP change from a healthcare lab application. List every defect you find
(security, correctness, data integrity). For each: line number, severity, and a fix.
Do not report style nitpicks.
TXT
        ,
        'inline_context' => <<<'TXT'
1:  <?php
2:  // Updates a patient's result and returns the list of results
3:  function saveResult($db, $user) {
4:      $id = $_POST['result_id'];
5:      $value = $_POST['value'];
6:      $db->query("UPDATE result SET value = '$value', validated_by = " . $user->id . " WHERE id = $id");
7:      echo "Saved: " . $_POST['comment'];
8:      $rows = $db->query("SELECT * FROM result WHERE patient_id = " . intval($_GET['patient']));
9:      foreach ($rows as $r) {
10:         if ($r['value'] > $r['crit_high']) { notifyDoctor($r['patient_id']); }
11:     }
12:     return $rows;
13: }
14: function deleteResult($db) {
15:     $db->query("DELETE FROM result WHERE id = " . (int)$_GET['id']);
16: }
TXT
        ,
        'rubric' => array(
            array('id' => 'sqli', 'description' => 'SQL injection on line 6 ($value and $id concatenated)', 'points' => 2, 'patterns' => array('(line )?6.{0,200}(sql injection|injection|prepared|parameter)', 'sql injection')),
            array('id' => 'xss', 'description' => 'Reflected XSS on line 7 (unescaped comment echoed)', 'points' => 2, 'patterns' => array('(line )?7.{0,200}(xss|cross-site|escape|htmlspecialchars)')),
            array('id' => 'authz', 'description' => 'No authorization / right check on update (any logged user can modify results) or on delete', 'points' => 2, 'patterns' => array('authori[sz]ation|permission|access control|right')),
            array('id' => 'csrf', 'description' => 'No CSRF protection on state-changing endpoints (update via POST, delete via GET)', 'points' => 2, 'patterns' => array('csrf|cross-site request', 'delete.{0,80}GET')),
            array('id' => 'float-cmp', 'description' => 'String vs numeric comparison of values on line 10 (type juggling), possible missed critical results', 'patterns' => array('(line )?10.{0,200}(string|numeric|cast|float|type|compar)')),
            array('id' => 'audit', 'description' => 'Missing audit trail / validated_by overwrite and no soft-delete traceability in a clinical context', 'patterns' => array('audit|trace|history|soft.?delete|log')),
        ),
    ),
    array(
        'id' => 'review-hl7-handler',
        'category' => 'review',
        'prompt' => <<<'TXT'
Review this HL7 inbound handler for correctness and patient-safety issues. List defects with
line numbers and a fix, ordered by severity.
TXT
        ,
        'inline_context' => <<<'TXT'
1:  <?php
2:  function handleOru(string $raw, PDO $pdo): string {
3:      $segments = explode("\n", $raw);
4:      $msh = explode('|', $segments[0]);
5:      $msgId = $msh[9];
6:      foreach ($segments as $seg) {
7:          $f = explode('|', $seg);
8:          if ($f[0] === 'PID') { $patient = $f[3]; }
9:          if ($f[0] === 'OBX') {
10:             $pdo->exec("INSERT INTO result (patient, code, value) VALUES ('$patient', '{$f[3]}', '{$f[5]}')");
11:         }
12:     }
13:     return "MSH|^~\\&|LIS|LAB|||" . date('YmdHis') . "||ACK|$msgId|P|2.5\rMSA|AA|$msgId";
14: }
TXT
        ,
        'rubric' => array(
            array('id' => 'seg-sep', 'description' => 'Segments are separated by \\r in HL7, not \\n (parsing fails or relies on non-standard input)', 'patterns' => array('\\\\r|carriage return|segment separator')),
            array('id' => 'sqli', 'description' => 'SQL injection via unescaped HL7 values on line 10', 'points' => 2, 'patterns' => array('sql injection|injection|prepared|parameter')),
            array('id' => 'idempotency', 'description' => 'No duplicate detection on message control ID: redelivery inserts duplicates', 'patterns' => array('duplicate|idempot|replay|retransmi|redeliver')),
            array('id' => 'always-aa', 'description' => 'ACK always returns AA even if parsing/insert fails (should return AE/AR); no error handling or transaction', 'points' => 2, 'patterns' => array('(always|unconditional).{0,60}(AA|accept)', '\bAE\b|\bAR\b|transaction')),
            array('id' => 'patient-undefined', 'description' => '$patient may be undefined or stale if OBX precedes PID or message contains several PIDs; wrong-patient risk', 'points' => 2, 'patterns' => array('undefined|uninitiali[sz]ed|wrong.patient|stale|before PID')),
            array('id' => 'escapes', 'description' => 'HL7 escape sequences and component separators (^) not handled; field indexing of MSH is off by one', 'patterns' => array('escape|component|off.by.one|MSH-?\d')),
        ),
    ),
));
