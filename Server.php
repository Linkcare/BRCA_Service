<?php
ini_set("soap.wsdl_cache_enabled", 0);
const SCORE_TASK_CODE = "BRCA_SCORINGS";
const ASSESSMENT_TASK_CODE = "BRCA.ASSESSMENT";

// Link the config params
include_once ("Config.php");
include_once ("utils.php");
include_once ("BRCACancerTable.php");
include_once ("BRCAAntecedent.php");
include_once ("BRCARelative.php");
include_once ("ScoringFHS7.php");
include_once ("ScoringManchester.php");
include_once ("ScoringOntario.php");
include_once ("ScoringPedigreeAssessment.php");
include_once ("ScoringReferralScreen.php");

setSystemTimeZone();

/**
 *
 * @param int $form_id
 * @param int $scoring_task_id
 * @param string $endpoint
 * @param string $timezone
 * @return string[]
 */
function service_brca_scoring($form_id, $scoring_task_id = null, $endpoint = "", $timezone = null) {
    service_log(__FUNCTION__ . "($form_id, $scoring_task_id, $endpoint)");

    $errorMsg = "";
    if (!$timezone) {
        $timezone = 0;
    }

    if (!$endpoint) {
        $endpoint = $GLOBALS["WS_LINK"] . "/ServerWSDL.php";
    }

    // Obtenemos el TOKEN si ya existe o iniciamos sesión si no existe
    // Cuando de error hay que borrar el TOKEN
    $uri = parse_url($endpoint)['scheme'] . '://' . parse_url($endpoint)['host'];
    $client = new SoapClient(null, ['location' => $endpoint, 'uri' => $uri, "connection_timeout" => 10]);
    try {
        $date = currentDate($timezone);
        $result = $client->session_init($GLOBALS["USER"], $GLOBALS["PWD"], null, null, null, '2.7.32', null, $date);
        if (!$result["token"]) {
            service_log("session_init error " . $result["ErrorMsg"]);
            return ["result" => "", "ErrorMsg" => $result["ErrorMsg"]];
        } else {
            $_SESSION["session"] = $result["token"];
        }

        $result = $client->session_language($_SESSION["session"], $GLOBALS["LANG"]);
        if ($result["ErrorMsg"]) {
            service_log("session_language error " . $result["ErrorMsg"]);
            return ["result" => "", "ErrorMsg" => $result["ErrorMsg"]];
        }

        $result = $client->session_role($_SESSION["session"], $GLOBALS["ROLE"]);
        if ($result["ErrorMsg"]) {
            service_log("session_role error " . $result["ErrorMsg"]);
            return ["result" => "", "ErrorMsg" => $result["ErrorMsg"]];
        }
    } catch (SoapFault $fault) {
        service_log("Error registering session! (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring}");
        return ["result" => "", "ErrorMsg" => "ERROR: SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})"];
    }

    // SET TIMEZONE FOR EACH CALL:
    $service_result = "OK";
    try {
        $errorMsg = calculateScoring($client, $_SESSION["session"], $form_id, $scoring_task_id);
        if ($errorMsg) {
            service_log($errorMsg);
            $service_result = "KO";
        }
    } catch (SoapFault $fault) {
        $errorMsg = "SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})";
        service_log($errorMsg);
        $service_result = "KO";
    }

    return ["result" => $service_result, "ErrorMsg" => $errorMsg];
}

/**
 *
 * @param SoapClient $client
 * @param string $sessionToken
 * @param int $formId // form containing FAMILY ANTECEDENTS
 * @param int $scoringTaskId // SCORING Task. If not provided, a new TASK with task_code='BRCA_SCORINGS' will be inserted. Otherwise, the provided
 *        task will be used
 * @return string
 */
function calculateScoring($client, $sessionToken, $formId, $scoringTaskId = null) {
    $result = $client->form_get_summary($sessionToken, $formId);
    if (!$result || $result["ErrorMsg"]) {
        // ERROR
        return $result["ErrorMsg"];
    }

    $formSummary = simplexml_load_string($result["result"]);
    $admissionId = intval("" . $formSummary->data->admission);

    /* @var BRCAAntecedent[] $antecedents */
    /* @var BRCARelative[] $relatives */
    $antecedents = loadAntecedents($formSummary);
    // Create the list of relatives from the list of antecedents. A single relative may have multiple antecedents
    $relatives = BRCARelative::generateRelativeList($antecedents);

    // delete previous Scoring TASKs
    $assessmentFormId = null;
    $ashkenazi = initializeScoring($client, $sessionToken, $admissionId, $scoringTaskId, $assessmentFormId);

    if (!$scoringTaskId) {
        // Insert Scoring TASK if necessary
        $result = $client->task_insert_by_task_code($sessionToken, $admissionId, SCORE_TASK_CODE);
        if (!$result || $result["ErrorMsg"]) {
            $errmsg = "ERROR inserting TASK 'BRCA_MANCHESTER_SCORE')";
            service_log($errmsg);
            return $errmsg;
        }

        $scoringTaskId = intval($result);
    }
    $result = $client->task_get($sessionToken, $scoringTaskId);
    $scoringFormIds = getFormIds($client, $sessionToken, $scoringTaskId);

    // Update various SCORING FORMS
    if (array_key_exists("MANCHESTER_SCORE_FORM", $scoringFormIds)) {
        $manchester = new ScoringManchester($relatives, $ashkenazi);
        $manchesterFormId = $scoringFormIds["MANCHESTER_SCORE_FORM"];
        $manchester->updateScoringForm($client, $sessionToken, $manchesterFormId);
    }

    if (array_key_exists("FHS-7_SCORINGS", $scoringFormIds)) {
        $fh7 = new ScoringFHS7($relatives, $ashkenazi);
        $fh7FormId = $scoringFormIds["FHS-7_SCORINGS"];
        $fh7->updateScoringForm($client, $sessionToken, $fh7FormId);
    }

    if (array_key_exists("ONTARIO_SCORING", $scoringFormIds)) {
        $ontario = new ScoringOntario($relatives, $ashkenazi);
        $ontarioFormId = $scoringFormIds["ONTARIO_SCORING"];
        $ontario->updateScoringForm($client, $sessionToken, $ontarioFormId);
    }

    if (array_key_exists("PAT_SCORE_FORM", $scoringFormIds)) {
        $pedigree = new ScoringPedigreeAssessment($relatives, $ashkenazi);
        $pedigreeFormId = $scoringFormIds["PAT_SCORE_FORM"];
        $pedigree->updateScoringForm($client, $sessionToken, $pedigreeFormId);
    }

    if (array_key_exists("RST_SCORE", $scoringFormIds)) {
        $rst = new ScoringReferralScreen($relatives, $ashkenazi);
        $rstFormId = $scoringFormIds["RST_SCORE"];
        $rst->updateScoringForm($client, $sessionToken, $rstFormId);
    }

    // Assign the ROLE "Case Manager" to the TASK
    setRoleService($client, $sessionToken, $scoringTaskId);

    // Finally, force the ASSESSMENT task recalculation calling form_set_all_answers() with no data, and indicating that the FORM should not be closed
    if ($assessmentFormId) {
        list($xmlSetAnswers, $root) = createAnswersXML();
        $xmlStr = $xmlSetAnswers->SaveXML();
        $result = $client->form_set_all_answers($sessionToken, $assessmentFormId, $xmlStr, 0);
        if ($result["ErrorMsg"]) {
            return 'Error updating ASSESSMENT TASK: ' . $result["ErrorMsg"];
        }
    } else {
        return 'Could not update ASSESSMENT TASK because it was not found';
    }

    return '';
}

error_reporting(0);
try {
    $server = new SoapServer("Server.wsdl");
    $server->addFunction("service_brca_scoring");
    $server->handle();
} catch (Exception $e) {
    service_log($e->getMessage());
}
