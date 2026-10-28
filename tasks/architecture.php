<?php
return array('tasks' => array(
    array(
        'id' => 'arch-interface-engine',
        'category' => 'architecture',
        'prompt' => <<<'TXT'
Design the architecture of an interface engine that receives HL7 v2 messages (MLLP over TCP)
from several analysers and a HIS and routes them to a LIS database. Max 450 words. Cover:
components, message persistence, retry and idempotency, ACK/NACK handling, ordering,
failure modes (crash mid-message, poison message, downstream outage), and data-integrity
guarantees. State the main trade-offs you chose.
TXT
        ,
        'rubric' => array(
            array('id' => 'persist-first', 'description' => 'Persist raw message durably before ACK (store-and-forward / inbox queue)', 'points' => 2, 'patterns' => array('persist.{0,80}(before|prior).{0,40}ACK|store.and.forward|inbox|write.ahead|durabl')),
            array('id' => 'idempotency', 'description' => 'Idempotent processing keyed on message control ID (and source)', 'points' => 2, 'patterns' => array('idempot|MSH-10|control id|dedup')),
            array('id' => 'ordering', 'description' => 'Per-patient/per-order ordering guarantees (partition by key) and out-of-order handling', 'patterns' => array('order(ing)?.{0,100}(patient|key|partition)|out.of.order')),
            array('id' => 'poison', 'description' => 'Poison-message handling: dead-letter queue, bounded retries with backoff, operator alert', 'points' => 2, 'patterns' => array('dead.letter|poison|quarantine', 'backoff')),
            array('id' => 'ack-semantics', 'description' => 'ACK semantics: AA only after durable accept; AE retryable; AR non-retryable', 'patterns' => array('AA.{0,100}(after|only)|AE.{0,60}retr|AR.{0,60}(not|never).{0,20}retr')),
            array('id' => 'tradeoffs', 'description' => 'Explicit trade-offs (latency vs durability, exactly-once vs at-least-once + idempotency)', 'patterns' => array('trade-?off|at-least-once|exactly-once')),
        ),
    ),
    array(
        'id' => 'arch-poct-gateway',
        'category' => 'architecture',
        'prompt' => <<<'TXT'
Design a point-of-care testing (POCT) device gateway for a hospital with 400 handheld glucose
and blood-gas devices docked on wards. Max 450 words. Cover device connectivity, patient
identification, operator authorisation, QC lockout, offline buffering, result delivery to
the LIS/EHR, security and auditability.
TXT
        ,
        'rubric' => array(
            array('id' => 'connectivity', 'description' => 'Device protocol adapters (POCT1-A, ASTM, vendor) behind a normalising layer', 'patterns' => array('POCT1|ASTM|adapter|normali[sz]')),
            array('id' => 'patient-id', 'description' => 'Patient ID by barcode + ADT lookup; unidentified-patient workflow', 'points' => 2, 'patterns' => array('ADT|barcode|wristband|unidentified')),
            array('id' => 'operator-qc', 'description' => 'Operator certification and QC-based lockout enforced via device configuration', 'points' => 2, 'patterns' => array('operator.{0,80}(certif|authori[sz])|QC.{0,60}lock')),
            array('id' => 'offline', 'description' => 'Offline buffering and reconciliation when docks or network are unavailable', 'patterns' => array('offline|buffer|reconcil|store.and.forward')),
            array('id' => 'audit-sec', 'description' => 'Audit trail, device authentication, TLS and network segmentation', 'patterns' => array('audit|TLS|certificate|segment')),
            array('id' => 'delivery', 'description' => 'Reliable HL7 delivery to LIS/EHR with ACK and retries', 'patterns' => array('HL7.{0,200}(ACK|retr)|ACK')),
        ),
    ),
));
