<?php

define('MATOMO_PROXY_FROM_ENDPOINT', 1);

$path = 'index.php';

$SUPPORTED_METHODS = [
    'CoreAdminHome.optOut',
];

$allParams = $_GET + $_POST;

if (!in_array("{$allParams['module']}.{$allParams['action']}", $SUPPORTED_METHODS)) {
    http_response_code(404);
    exit;
}

include dirname(__FILE__) . '/proxy.php';
