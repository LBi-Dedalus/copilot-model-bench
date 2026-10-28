<?php
return array('tasks' => array(
    array(
        'id' => 'jargon-hl7-basics',
        'category' => 'healthcare_jargon',
        'prompt' => <<<'TXT'
Answer concisely (max 250 words). (1) What are the first fields of an HL7 v2 MSH segment and
what do MSH-1 and MSH-2 contain? (2) Differences between ADT, ORM and ORU message types.
(3) What do the ACK codes AA, AE and AR mean?
TXT
        ,
        'rubric' => array(
            array('id' => 'msh-delims', 'description' => 'MSH-1 is the field separator (|) and MSH-2 the encoding characters (^~\\&)', 'points' => 2,
                'patterns' => array('MSH-?1.{0,80}(field separator|\|)', '\^~\\\\&')),
            array('id' => 'adt', 'description' => 'ADT = admission/discharge/transfer (patient administration)', 'patterns' => array('ADT.{0,60}(admit|admission|discharge|transfer|patient administration)')),
            array('id' => 'orm', 'description' => 'ORM = order message (v2.x order entry)', 'patterns' => array('ORM.{0,60}order')),
            array('id' => 'oru', 'description' => 'ORU = observation result (unsolicited)', 'patterns' => array('ORU.{0,80}(result|observation)')),
            array('id' => 'aa', 'description' => 'AA = application accept', 'patterns' => array('\bAA\b.{0,40}accept')),
            array('id' => 'ae-ar', 'description' => 'AE = application error, AR = application reject', 'points' => 2,
                'patterns' => array('\bAE\b.{0,40}error', '\bAR\b.{0,40}reject')),
        ),
    ),
    array(
        'id' => 'jargon-bacteriology',
        'category' => 'healthcare_jargon',
        'prompt' => <<<'TXT'
In clinical bacteriology software, explain (max 250 words): antibiogram, MIC, the difference
between EUCAST and CLSI breakpoints, what ESBL means and how it affects reporting, and the
meaning of S, I and R (including EUCAST's "I" redefinition).
TXT
        ,
        'rubric' => array(
            array('id' => 'antibiogram', 'description' => 'Antibiogram: antimicrobial susceptibility profile of an isolate (or cumulative resistance statistics of a unit)', 'patterns' => array('antibiogram.{0,200}(suscept|sensib|resist)')),
            array('id' => 'mic', 'description' => 'MIC: minimum inhibitory concentration, lowest concentration that inhibits visible growth', 'patterns' => array('minimum inhibitory concentration', 'lowest concentration')),
            array('id' => 'breakpoints', 'description' => 'EUCAST and CLSI are different standards/committees with different clinical breakpoints', 'points' => 2, 'patterns' => array('EUCAST.{0,300}CLSI|CLSI.{0,300}EUCAST')),
            array('id' => 'esbl', 'description' => 'ESBL: extended-spectrum beta-lactamase, resistance to penicillins/cephalosporins; affects interpretation and reporting', 'patterns' => array('extended[- ]spectrum.{0,20}beta[- ]lactamase')),
            array('id' => 'eucast-i', 'description' => "EUCAST: I = susceptible, increased exposure (not 'intermediate')", 'points' => 2, 'patterns' => array('increased exposure')),
        ),
    ),
    array(
        'id' => 'jargon-trap-false-premise',
        'category' => 'healthcare_jargon',
        'prompt' => <<<'TXT'
Explain how the HL7 v2.9 "PQR^Z99" message is used to transmit antibiogram results from a
bacteriology analyser to the LIS.
TXT
        ,
        'rubric' => array(
            array('id' => 'challenge', 'description' => 'Recognises that PQR^Z99 is not a standard antibiogram result message (results use ORU^R01; Z-messages are local custom) and says so', 'points' => 4,
                'patterns' => array('(not|n.t) (a )?(standard|defined|official|real|exist)', 'does not exist', 'no such', 'ORU\^R01', 'custom|Z-(message|segment)')),
        ),
        'forbidden' => array('PQR\^Z99 (message )?(is|carries|transmits) the antibiogram'),
    ),
    array(
        'id' => 'jargon-poct',
        'category' => 'healthcare_jargon',
        'prompt' => <<<'TXT'
A hospital wants to connect point-of-care glucose meters to its LIS/HIS. Describe the
connectivity standards involved (max 250 words): name the relevant standard(s), how a device
docking station/middleware fits, and the patient identification and QC risks specific to POCT.
TXT
        ,
        'rubric' => array(
            array('id' => 'poct1', 'description' => 'Mentions POCT1-A (CLSI) and/or ASTM E1394/LIS2, IEEE 11073 or Continua', 'points' => 2, 'patterns' => array('POCT1', 'LIS2', 'E1394', '11073')),
            array('id' => 'middleware', 'description' => 'POCT middleware / connectivity server between devices and LIS/HIS, forwarding results via HL7', 'patterns' => array('middleware|connectivity (server|manager)|data manager')),
            array('id' => 'patient-id', 'description' => 'Patient identification risks: barcode/wristband scanning, ADT lookup, unidentified results, manual entry errors', 'patterns' => array('wristband|barcode|manual entry|patient id')),
            array('id' => 'qc', 'description' => 'Operator certification/lockout, QC lockout, calibration, result flagging', 'patterns' => array('operator.{0,40}(certif|lockout|competen)|QC.{0,40}lock|quality control')),
        ),
    ),
));
