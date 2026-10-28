<?php
return array('tasks' => array(
    array(
        'id' => 'plan-antibiogram-export',
        'category' => 'planning',
        'prompt' => <<<'TXT'
A PHP/MySQL laboratory information system must add an "antibiogram export" feature: lab
managers export, per period and ward, a cumulative antibiotic susceptibility report (percent
S/I/R per organism x antibiotic, first isolate per patient, EUCAST or CLSI interpretation)
as CSV and PDF. Produce an ordered implementation plan (max 400 words): steps, data model
changes, validation of the epidemiology rules, rights/permissions, testing and rollout risks.
TXT
        ,
        'rubric' => array(
            array('id' => 'first-isolate', 'description' => 'De-duplicates to first isolate per patient per period (CLSI M39 style), key epidemiology rule', 'points' => 2, 'patterns' => array('first[- ]isolate|de-?duplicat')),
            array('id' => 'interp', 'description' => 'Stores or recomputes interpretation with versioned breakpoints (EUCAST/CLSI year) for reproducibility', 'points' => 2, 'patterns' => array('breakpoint.{0,100}(version|year)|version.{0,60}breakpoint')),
            array('id' => 'privacy', 'description' => 'Access rights, anonymisation / data protection (GDPR) of exports', 'patterns' => array('rights|permission|GDPR|anonymi[sz]')),
            array('id' => 'perf', 'description' => 'Performance on large result sets: indexing, async/queued generation, pagination or streaming', 'patterns' => array('index|queue|async|stream|background job')),
            array('id' => 'tests', 'description' => 'Test strategy with known datasets / golden files', 'patterns' => array('golden|fixture|known dataset|test data|unit test')),
            array('id' => 'order', 'description' => 'Plan is ordered with clear steps (numbered)', 'patterns' => array('(^|\n)\s*(1[.)]|Step 1)')),
        ),
    ),
    array(
        'id' => 'plan-oru-interface',
        'category' => 'planning',
        'prompt' => <<<'TXT'
Plan the addition of a new inbound HL7 v2.5 ORU^R01 interface from a third-party analyser
middleware into an existing LIS. Give an ordered plan (max 400 words) covering mapping,
identifiers, error handling and ACKs, testing with sample messages, monitoring and cut-over.
TXT
        ,
        'rubric' => array(
            array('id' => 'mapping', 'description' => 'Code mapping (local test codes to LOINC / internal codes) and unit handling', 'patterns' => array('LOINC|code mapping|unit')),
            array('id' => 'ack', 'description' => 'ACK/NACK semantics (AA/AE/AR) and retries', 'patterns' => array('\bAA\b|\bAE\b|\bAR\b|ACK')),
            array('id' => 'idem', 'description' => 'Idempotency on message control id; dead-letter or quarantine for failed messages', 'points' => 2, 'patterns' => array('idempot|duplicate|dead.letter|quarantine|MSH-10')),
            array('id' => 'patient-match', 'description' => 'Patient / order matching rules and handling of unmatched results', 'points' => 2, 'patterns' => array('match|unmatched|accession|placer|filler')),
            array('id' => 'monitor', 'description' => 'Monitoring/alerting and cut-over with parallel run or rollback', 'patterns' => array('monitor|alert|rollback|parallel')),
        ),
    ),
));
