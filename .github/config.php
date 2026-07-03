<?php

$MATOMO_URL = 'http://localhost:8080/tests/server/';

$PROXY_URL = 'http://proxy:8080/';

$TOKEN_AUTH = 'xyz';

$timeout = 5;

// Test-only request headers (below) let the suite exercise config variations per-request.
// Gated on the local test-server URL so a stray copy to production is inert.
$isTestServer = strpos($MATOMO_URL, '/tests/server/') !== false;

// Exercise IP-forward-header handling.
if ($isTestServer && !empty($_SERVER['HTTP_X_TEST_IP_FORWARD_HEADER'])) {
    $http_ip_forward_header = $_SERVER['HTTP_X_TEST_IP_FORWARD_HEADER'];
}

// Exercise cookie-allowlist filtering (comma-separated entries; empty value = explicit empty allowlist).
if ($isTestServer && isset($_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST'])) {
    $COOKIE_ALLOWLIST = $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST'] === ''
        ? array()
        : explode(',', $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST']);
}

// Exercise misconfiguration handling (a non-array $COOKIE_ALLOWLIST).
if ($isTestServer && isset($_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST_INVALID'])) {
    $COOKIE_ALLOWLIST = $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST_INVALID'];
}
