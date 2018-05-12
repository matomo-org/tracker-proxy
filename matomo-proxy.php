<?php

define('MATOMO_PROXY_FROM_ENDPOINT', 1);

$path = 'index.php';

$SUPPORTED_METHODS = [
    'CoreAdminHome.optOut',
];

$module = isset($_GET['module']) ? $_GET['module'] : null;
if (empty($module)) {
    $module = isset($_POST['module']) ? $_POST['module'] : null;
}

$action = isset($_GET['action']) ? $_GET['action'] : null;
if (empty($action)) {
    $action = isset($_POST['action']) ? $_POST['action'] : null;
}

if (!isset($module)
    || !isset($action)
    || !in_array("$module.$action", $SUPPORTED_METHODS)
) {
    http_response_code(404);
    exit;
}

include dirname(__FILE__) . '/proxy.php';
