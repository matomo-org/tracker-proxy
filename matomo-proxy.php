<?php

define('MATOMO_PROXY_FROM_ENDPOINT', 1);

$path = 'index.php';

$SUPPORTED_METHODS = [
    'CoreAdminHome.optOut',
    'CoreAdminHome.optOutJS'
];
$VALID_FILES = [
    'plugins/CoreAdminHome/javascripts/optOut.js'
];

$module = isset($_GET['module']) ? $_GET['module'] : null;
if (empty($module)) {
    $module = isset($_POST['module']) ? $_POST['module'] : null;
}

$action = isset($_GET['action']) ? $_GET['action'] : null;
if (empty($action)) {
    $action = isset($_POST['action']) ? $_POST['action'] : null;
}

$filerequest = isset($_GET['file']) ? $_GET['file'] : null;
if (empty($filerequest)) {
    $filerequest = isset($_POST['file']) ? $_POST['file'] : null;
}

$hasFileRequest = !empty($filerequest);
$hasSupportedMethod = !empty($module) && !empty($action) && in_array("$module.$action", $SUPPORTED_METHODS, true);

if ($hasFileRequest) {
    if (!in_array($filerequest, $VALID_FILES, true)) {
        http_response_code(404);
        exit;
    }
} elseif (!$hasSupportedMethod) {
    http_response_code(404);
    exit;
}

include __DIR__ . '/proxy.php';
