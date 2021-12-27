<?php
$ANTECEDENTS_TASK_CODE = "BRCA_relativeregistration";
$GLOBALS["DEBUG"] = false;

$GLOBALS["USER"] = "brcaservice";
$GLOBALS["PWD"] = "linkcare";
$GLOBALS["ROLE"] = "47"; // service
$GLOBALS["LANG"] = "EN";
$GLOBALS["DEFAULT_TIMEZONE"] = "Europe/Madrid";
// $GLOBALS["WS_LINK"] = "https://demo-api.linkcare.es";
$GLOBALS["WS_LINK"] = "http://localhost";

// Load particular configuration
if (file_exists(__DIR__ . '/conf/configuration.php')) {
    include_once __DIR__ . '/conf/configuration.php';
}

date_default_timezone_set($GLOBALS["DEFAULT_TIMEZONE"]);
