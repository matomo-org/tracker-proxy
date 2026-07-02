<?php

$MATOMO_URL = 'http://localhost:8080/tests/server/';

$PROXY_URL = 'http://proxy:8080/';

$TOKEN_AUTH = 'xyz';

$timeout = 5;

// Test-only: lets the suite exercise IP-forward-header handling via the X-Test-Ip-Forward-Header
// request header. Gated on the local test-server URL so a stray copy to production is inert.
if (strpos($MATOMO_URL, '/tests/server/') !== false && !empty($_SERVER['HTTP_X_TEST_IP_FORWARD_HEADER'])) {
    $http_ip_forward_header = $_SERVER['HTTP_X_TEST_IP_FORWARD_HEADER'];
}

// Test-only: lets the suite exercise cookie-allowlist filtering via the X-Test-Cookie-Allowlist
// request header (comma-separated allowlist entries; empty value means an explicit empty
// allowlist). Gated on the local test-server URL so a stray copy to production is inert.
if (strpos($MATOMO_URL, '/tests/server/') !== false && isset($_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST'])) {
    $COOKIE_ALLOWLIST = $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST'] === ''
        ? array()
        : explode(',', $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST']);
}
