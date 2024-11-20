<?php

/**
 * Parses the XML returned by task_activity_list()
 * @param SoapClient $client
 * @param string $sessionToken
 * @param string $activityListXML
 * @return int[]
 */
function getFormIds($client, $sessionToken, $taskId) {
    $arrFormIds = [];

    // Get the list of FORMs included in the TASK. They will be the Scoring summaries
    $result = $client->task_activity_list($sessionToken, $taskId);
    if (!$result || $result["ErrorMsg"]) {
        // ERROR
        service_log("ERROR in task_activity_list($taskId) " . $result["ErrorMsg"]);
        return $result["ErrorMsg"];
    }

    $xml = simplexml_load_string($result["result"]);
    if (!$xml->activity) {
        service_log("TASK ($taskId) does not contain FORMS");
        return $arrFormIds;
    }

    foreach ($xml->activity as $form) {
        $arrFormIds["" . $form->form_code] = intval("" . $form->ref);
    }

    return $arrFormIds;
}

/**
 * Looks for the questionId for a specific $dataCode in an $xml returned by form_get_summary()
 *
 * @param SimpleXMLElement $xml
 * @param int $curRow
 * @return int
 */
function getQuestionId($xml, $row, $dataCode) {
    foreach ($xml->data->questions->question as $q) {
        if ($row && "" . $q->row) {
            if ($q->row != $row)
                continue;
        }
        if ($q->data_code == $dataCode) {
            return intval("" . $q->question_id);
        }
    }

    return null;
}

/**
 * Looks for the questionId for a specific $dataCode and returns the ARRAY reference to which it belongs.
 * If the found question does not belong to an array of questions, the function returns NULL even though
 * other question has the same data code
 *
 * @param SimpleXMLElement $xml
 * @param string $dataCode
 * @return string
 */
function getArrayRef($xml, $dataCode) {
    foreach ($xml->data->questions->question as $q) {
        if ($q->data_code == $dataCode && $q->row) {
            if ($q->array_ref) {
                return trim($q->array_ref);
            } else {
                return trim($q->order);
            }
        }
    }

    return null;
}

/**
 * Creates an empty XML document to send to form_set_all_answers()
 *
 * @return DomDocument[]|DOMNode[]
 */
function createAnswersXML() {
    $doc = new DomDocument("1.0", 'utf-8');
    $root = $doc->createElement("questions");
    $root = $doc->appendChild($root);
    return [$doc, $root];
}

/**
 * Creates an XML subnode
 *
 * @param DomDocument $docXML
 * @param DOMNode $parentNode
 * @param string $nodeName
 * @param value $value
 * @return DOMNode
 */
function createSubnode($docXML, $parentNode, $nodeName, $value = null) {
    $node = $docXML->createElement($nodeName);
    $node = $parentNode->appendChild($node);
    if ($value !== null) {
        $innerTextNode = $docXML->createTextNode($value);
        $node->appendChild($innerTextNode);
    }
    return $node;
}

/**
 *
 * @param DOMDocument $doc
 * @param DOMNode $root
 * @param string $dataCode
 * @param string $value
 */
function addNodeQuestion($doc, $root, $dataCode, $value) {
    $qNode = createSubnode($doc, $root, "question");
    createSubNode($doc, $qNode, "question_id", $dataCode);
    createSubNode($doc, $qNode, "value", $value);
    createSubNode($doc, $qNode, "option_id", $value);
}

/**
 *
 * @param SoapClient $client
 * @param string $sessionToken
 * @param SimpleXMLElement $xml
 * @param int $curRow
 * @param int $formId
 * @param string $dataCode
 * @param string $value
 * @return string
 */
function updateFormQuestion($client, $sessionToken, $xml, $curRow, $formId, $dataCode, $value) {
    $errMsg = "";
    $qId = getQuestionId($xml, $curRow, $dataCode);
    if (!$qId) {
        $errMsg = "Question $dataCode does not exist in the FORM";
    }
    $res = $client->form_set_answer($sessionToken, $formId, $qId, $value);
    if (!$res) {
        $errMsg = "Unknown error in call to form_set_answer";
    } elseif ($res["result"]) {
        $errMsg = $res["result"];
    }

    if ($errMsg)
        service_log($errMsg);
    return ($errMsg);
}

/**
 * Assigns role CASE MANAGER to a TASK
 *
 * @param SoapClient $client
 * @param string $sessionToken
 * @param int $taskId
 */
function setRoleService($client, $sessionToken, $taskId) {
    $result = $client->task_get($sessionToken, $taskId);
    if (!$result || $result["ErrorMsg"]) {
        // ERROR
        return $result["ErrorMsg"];
    }

    $xmlTaskInfo = simplexml_load_string($result["result"]);
    $xmlstr = "<task>
    <ref>$taskId</ref>
    <description></description>
    <assignments>
    <assignment>
    <team>
    <ref></ref>
    </team>
    <role>
    <ref>24</ref>
    </role>
    <user>
    <ref></ref>
    </user>
    </assignment>
    </assignments>
    <date>" . $xmlTaskInfo->date . "</date>
        <hour>" . $xmlTaskInfo->hour . "</hour>
        <duration>" . $xmlTaskInfo->duration . "</duration>
        <follow_report>" . $xmlTaskInfo->follow_report . "</follow_report>
        <recursive></recursive>
    </task>";
    $result = $client->task_set($sessionToken, $xmlstr);
}

/**
 * Deletes existing scoring TASKs in the ADMISSION (except the one identified by $scoringTaskId)
 * Returns true if the patient has Ashkenazi Jewish Heritage
 *
 * @param SoapClient $client
 * @param string $sessionToken
 * @param int $admissionId return bool
 */
function initializeScoring($client, $sessionToken, $admissionId, $scoringTaskId, &$assessmentFormId) {
    $ashkenazi["maternal"] = false;
    $ashkenazi["paternal"] = false;
    $assessmentFormId = null;

    $result = $client->admission_get_task_list($sessionToken, $admissionId);
    if (!$result || $result["ErrorMsg"]) {
        // ERROR
        return false;
    }

    $xmlTasks = simplexml_load_string($result["result"]);
    foreach ($xmlTasks->task as $t) {
        if ($t->code == SCORE_TASK_CODE && $t->ref != $scoringTaskId) {
            $client->task_delete($sessionToken, "" . $t->ref);
        } elseif ($t->code == "BRCA_CRITERIA_TASK") {
            // Check whether the patient has Ashkenazi Jewish Heritage or not (by maternal or paternal side)
            $ashkenazi = getAshkenaziEthnicity($client, $sessionToken, intval("" . $t->forms->form[0]->ref));
        } elseif ($t->code == ASSESSMENT_TASK_CODE && !$assessmentFormId) {
            $formIds = getFormIds($client, $sessionToken, "" . $t->ref);
            $assessmentFormId = array_pop($formIds);
        }
    }

    return $ashkenazi;
}

/**
 * Loads the antecedents from an XML obtained with form_get_summary()
 *
 * @param SimpleXMLElement $doc
 * @return BRCAAntecedent[]
 */
function loadAntecedents($doc) {
    $antecedents = [];

    if (!$doc || !$doc->data || !$doc->data->questions) {
        return $antecedents;
    }

    $rowData = [];
    /* @var SimpleXMLElement $q */
    foreach ($doc->data->questions->question as $q) {
        $row = "" . $q->row;
        if (!$row)
            continue; // We are only looking for ARRAY questions
        if ("" . $q->empty_row == "1")
            continue; // Ignore empty rows
        $dataCode = "" . $q->data_code;
        if (!$dataCode)
            continue; // Ignore questions with no data_code
        if (!array_key_exists($row, $rowData))
            $rowData[$row] = [];
        $r = $rowData[$row][$dataCode] = "" . $q->value;
    }

    foreach ($rowData as $r) {
        $a = new BRCAAntecedent();
        if (array_key_exists('DIAGNOSE.CODE', $r)) {
            $a->cancerCode = extractCode($r['DIAGNOSE.CODE']);
        } else {
            service_log("   Datacode DIAGNOSE.CODE does not exist in 'Family Antecedents form'");
        }
        if (array_key_exists('ONSET', $r)) {
            $a->onsetAge = $r['ONSET'];
        } else {
            service_log("   Datacode ONSET does not exist in 'Family Antecedents form'");
        }
        if (array_key_exists('RELATIVE.CODE', $r)) {
            $a->relativeCode = extractCode($r['RELATIVE.CODE']);
        } else {
            service_log("   Datacode RELATIVE.CODE does not exist in 'Family Antecedents form'");
        }
        if (array_key_exists('RELATIVE.NAME', $r)) {
            $a->relativeName = $r['RELATIVE.NAME'];
        }
        if (!$a->cancerCode || !$a->onsetAge || !$a->relativeCode) {
            // It is not possible to calculate a scoring without minimum data
            service_log("   NOT ENOUGH DATA TO PROCESS ANTECEDENT (Diagnose code: $a->cancerCode, Onset: $a->onsetAge, Relative: $a->relativeName");
            continue;
        }
        $antecedents[] = $a;
    }

    return $antecedents;
}

/**
 *
 * @param SoapClient $client
 * @param string $sessionToken
 * @param int $formId
 * @return bool[];
 */
function getAshkenaziEthnicity($client, $sessionToken, $formId) {
    $ashkenazi["maternal"] = false;
    $ashkenazi["paternal"] = false;

    $result = $client->form_get_summary($sessionToken, $formId);

    if (!$result || $result["ErrorMsg"]) {
        // ERROR
        service_log($result["ErrorMsg"]);
        return $ashkenazi;
    }

    $doc = simplexml_load_string($result["result"]);

    if (!$doc || !$doc->data || !$doc->data->questions) {
        return $ashkenazi;
    }

    $questionData = null;
    /* @var SimpleXMLElement $q */
    foreach ($doc->data->questions->question as $q) {
        $dataCode = "" . $q->data_code;
        if (!$dataCode)
            continue; // Ignore questions with no data_code
        $questionData[$dataCode] = "" . $q->value;
    }

    $exist_side = false;
    if (array_key_exists('BRCA.ASHKENAZI_PATERNAL_ETHNICITY', $questionData)) {
        $ashkenazi["paternal"] = ($questionData['BRCA.ASHKENAZI_PATERNAL_ETHNICITY'] == 1 ? true : false);
        $exist_side = true;
    }
    if (array_key_exists('BRCA.ASHKENAZI_MATERNAL_ETHNICITY', $questionData)) {
        $ashkenazi["maternal"] = ($questionData['BRCA.ASHKENAZI_MATERNAL_ETHNICITY'] == 1 ? true : false);
        $exist_side = true;
    }

    if (!$exist_side && array_key_exists('BRCA.ASHKENAZI_ETHNICITY', $questionData)) {
        $ashkenazi["paternal"] = ($questionData['BRCA.ASHKENAZI_ETHNICITY'] == 1 ? true : false);
        $ashkenazi["maternal"] = ($questionData['BRCA.ASHKENAZI_ETHNICITY'] == 1 ? true : false);
    }

    return $ashkenazi;
}

/**
 *
 * @param string $jsonCode
 * @return string
 */
function extractCode($jsonCode) {
    $code = json_decode($jsonCode);
    if ($code) {
        return $code->code;
    }

    service_log("It was not possible to extract the code for JSON: " . $jsonCode);
    return "";
}

/**
 * Generate a service log
 *
 * @param string $log_msg
 */
function service_log($log_msg) {
    if (!is_dir("logs/")) {
        mkdir("logs/");
    }

    $txt = "Date:" . date("d-m-Y H:i:s") . " $log_msg";
    error_log($txt);
    if (is_dir("logs/")) {
        file_put_contents("logs/" . date("Y-m-d"), "$txt\n", FILE_APPEND);
    }
}

/**
 *
 * @param unknown $error_msg
 * @param unknown $doc
 * @param unknown $endpoint
 */
function service_failure($error_msg, $doc = null, $endpoint = null) {
    service_log("Error: $error_msg");
    if ($doc && $_SESSION["session"]) {
        $uri = parse_url($endpoint)['scheme'] . '://' . parse_url($endpoint)['host'];
        $client = new SoapClient(null, ['location' => $endpoint, 'uri' => $uri]);
        $result = $client->form_set_answer($_SESSION["session"], (string) $doc->form, 1, 'N');
    }
}

/**
 * Sets the time zone based on the Operative System configuration
 */
function setSystemTimeZone() {
    $timezone = $GLOBALS["DEFAULT_TIMEZONE"];
    if (is_link('/etc/localtime')) {
        // Mac OS X (and older Linuxes)
        // /etc/localtime is a symlink to the
        // timezone in /usr/share/zoneinfo.
        $filename = readlink('/etc/localtime');
        if (strpos($filename, '/usr/share/zoneinfo/') === 0) {
            $timezone = substr($filename, 20);
        }
    } elseif (file_exists('/etc/timezone')) {
        // Ubuntu / Debian.
        $data = file_get_contents('/etc/timezone');
        if ($data) {
            $timezone = $data;
        }
    } elseif (file_exists('/etc/sysconfig/clock')) {
        // RHEL / CentOS
        $data = parse_ini_file('/etc/sysconfig/clock');
        if (!empty($data['ZONE'])) {
            $timezone = $data['ZONE'];
        }
    }
    date_default_timezone_set($timezone);
}

/**
 * Calculates the current date in the specified timezone
 *
 * @param number $timezone
 * @return string
 */
function currentDate($timezone = null) {
    $datetime = new DateTime('now', new DateTimeZone('UTC'));

    if (is_numeric($timezone)) {
        // Some timezones are not an integer number of hours
        $timezone = intval($timezone * 60);
        $dateInTimezone = date('Y-m-d H:i:s', strtotime("$timezone minutes", strtotime($datetime->format('Y\-m\-d\ H:i:s'))));
    } else {
        try {
            $tz_object = new DateTimeZone($timezone);
            $datetime->setTimezone($tz_object);
        } catch (Exception $e) {
            // If an invalid timezone has been provided, ignore it
        }
        $dateInTimezone = $datetime->format('Y-m-d H:i:s');
    }
    return $dateInTimezone;
}
