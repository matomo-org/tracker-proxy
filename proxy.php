<?php

/**
 * Matomo - free/libre analytics platform
 * Matomo Proxy Hide URL
 *
 * @link https://matomo.org/faq/how-to/faq_132
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

if (!defined('MATOMO_PROXY_FROM_ENDPOINT')) {
    exit; // this file is not supposed to be accessed directly
}

// if set to true, will print out more information about request errors so said errors can be more easily debugged.
$DEBUG_PROXY = false;

if( $DEBUG_PROXY ){
    error_reporting( E_ALL );
    ini_set( 'log_errors', 1 );
    ini_set( 'display_errors', 1 );
    ini_set( 'error_log', __DIR__ . '/debug-proxy.log' );
}

// set to true if the target matomo server has a ssl certificate that will fail verification, like when testing.
$NO_VERIFY_SSL = false;

if (file_exists(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
}

// -----
// Important: read the instructions in README.md or at:
// https://github.com/matomo-org/tracker-proxy#matomo-tracker-proxy
// -----
if (! isset($MATOMO_URL) && isset($PIWIK_URL)) {
    // FOR BC
    $MATOMO_URL = $PIWIK_URL;
}

// Edit the line below, and replace http://your-matomo-domain.example.org/matomo/
// with your Matomo URL ending with a slash.
// This URL will never be revealed to visitors or search engines.
if (! isset($MATOMO_URL)) {
    $MATOMO_URL = 'http://your-matomo-domain.example.org/matomo/';
}

// Edit the line below, and replace xyz by the token_auth for the user "UserTrackingAPI"
// which you created when you followed instructions above.
if (! isset($TOKEN_AUTH)) {
    $TOKEN_AUTH = 'xyz';
}

// Maximum time, in seconds, to wait for the Matomo server to return the 1*1 GIF
if (! isset($timeout)) {
    $timeout = 5;
}

// The HTTP User-Agent to set in the request sent to Matomo Tracking API
if (empty($user_agent)) {
    $user_agent = arrayValue($_SERVER, 'HTTP_USER_AGENT', '');
}

// -----------------------------
// DO NOT MODIFY BELOW THIS LINE
// -----------------------------

// the HTTP response headers captured via fopen or curl
$httpResponseHeaders = array();

// 1) MATOMO.JS PROXY: No _GET parameter, we serve the JS file; or we serve a requested js file
if ((empty($_GET) && empty($_POST)) || (isset($filerequest) && substr($filerequest, -3) === '.js')) {
    $modifiedSince = false;
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $modifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
        // strip any trailing data appended to header
        if (false !== ($semicolon = strpos($modifiedSince, ';'))) {
            $modifiedSince = substr($modifiedSince, 0, $semicolon);
        }
        $modifiedSince = strtotime($modifiedSince);
    }
    // Re-download the matomo.js once a day maximum
    $lastModified = time() - 86400;

    // set HTTP response headers
    sendHeader('Vary: Accept-Encoding');

    // Returns 304 if not modified since
    if (!empty($modifiedSince) && $modifiedSince > $lastModified) {
        sendHeader(sprintf("%s 304 Not Modified", $_SERVER['SERVER_PROTOCOL']));
    } else {
        sendHeader('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        sendHeader('Content-Type: application/javascript; charset=UTF-8');

        // Silent fail: hide Warning in 'matomo.js' response
        if (empty($_GET) && empty($_POST)) {
            if ($path !== 'matomo.php') {
                $jsPath = 'piwik.js'; // for BC eg in case user uses an older version of Matomo
            } else {
                $jsPath = 'matomo.js';
            }
            list($content, $httpStatus) = getHttpContentAndStatus($MATOMO_URL . $jsPath, $timeout, $user_agent);
        } else {
            list($content, $httpStatus) = getHttpContentAndStatus($MATOMO_URL . $filerequest, $timeout, $user_agent);
        }
        if ($matomoJs = $content) {
            echo $matomoJs;
        } else {
            echo '/* there was an error loading matomo.js */';
        }
    }
    exit;
}
@ini_set('magic_quotes_runtime', 0);

// Read the request body once; php://input may not be re-readable on older PHP versions.
$rawPostBody = file_get_contents("php://input");
$forwardPostBody = $rawPostBody;

// 2) MATOMO.PHP PROXY: GET parameters found, this is a tracking request, we redirect it to Piwik
if (strpos($path, '?') === false) {
    $path = $path . '?';
}

$extraQueryParams = array();
if (strpos($path, 'piwik.php') === 0 || strpos($path, 'matomo.php') === 0) {
    // Without an IP-forward header, send the visitor IP as `cip` authorized by our token_auth - but
    // only when the client sent no token_auth or auth-protected param, so we never authorize its override.
    if (empty($http_ip_forward_header)) {
        // Same bulk detection as Matomo's Requests::isUsingBulkRequest (both quote variants).
        $isBulk = $rawPostBody !== ''
            && (strpos($rawPostBody, '"requests"') !== false || strpos($rawPostBody, "'requests'") !== false);

        if ($isBulk) {
            // Matomo reads the bulk token only from the JSON body, so pass any URL token_auth down to
            // be relocated there. Only $_GET matters here - for a bulk POST, $_POST is the mangled body.
            $clientUrlToken = (isset($_GET['token_auth']) && is_string($_GET['token_auth']) && $_GET['token_auth'] !== '')
                ? $_GET['token_auth']
                : null;
            $forwardPostBody = injectVisitIpIntoBulkRequest($rawPostBody, getVisitIp(), $TOKEN_AUTH, $clientUrlToken);
            // The batch token now lives in the JSON body; never also send one in the forwarded query.
            unset($_GET['token_auth']);
        } else {
            if (!isset($_GET['cip']) && !isset($_POST['cip'])) {
                $extraQueryParams['cip'] = getVisitIp();
            }
            if (!clientProvidesAuthParams($_GET) && !clientProvidesAuthParams($_POST)) {
                // Drop any empty/array token_auth the client sent so it can't clobber ours when
                // $_GET is merged below (array_merge lets $_GET win on key collision).
                unset($_GET['token_auth']);
                $extraQueryParams['token_auth'] = $TOKEN_AUTH;
            }

            // Rebuild the body from parsed $_POST, not the raw bytes, so it matches the params our token
            // decision saw: a param dropped by max_input_vars is absent from both and can't slip past us.
            if (!empty($_POST)) {
                $forwardPostBody = http_build_query($_POST);
            }
        }
    }
    // With an IP-forward header set, the visitor IP goes in that header (see getHttpContentAndStatus);
    // we inject no cip/token and let Matomo apply its own auth rules.
}

$url = $MATOMO_URL . $path;
$url .= http_build_query(array_merge($extraQueryParams, $_GET));

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    // PHP 5.2 breaks with the new 204 status code so we force returning the image every time
    list($content, $httpStatus) = getHttpContentAndStatus($url . '&send_image=1', $timeout, $user_agent, $forwardPostBody);
    $content = sanitizeContent($content);

    forwardHeaders($content);

    echo $content;
} else {
    // PHP 5.3 and above
    list($content, $httpStatus) = getHttpContentAndStatus($url, $timeout, $user_agent, $forwardPostBody);
    $content = sanitizeContent($content);

    forwardHeaders($content);

    // Forward the HTTP response code
    if (!headers_sent() && !empty($httpStatus)) {
        header($httpStatus);
    }

    echo $content;
}

function sanitizeContent($content)
{
    global $TOKEN_AUTH;
    global $MATOMO_URL;
    global $PROXY_URL;
    global $VALID_FILES;

    $matomoHost = parse_url($MATOMO_URL, PHP_URL_HOST);
    $proxyHost = parse_url($PROXY_URL, PHP_URL_HOST);

    // Scrub the token in raw and URL-encoded form (we inject it through http_build_query, which
    // percent-encodes it, so a non-hex token could otherwise be reflected back unscrubbed).
    $tokenForms = array_unique(array($TOKEN_AUTH, rawurlencode($TOKEN_AUTH), urlencode($TOKEN_AUTH)));
    foreach ($tokenForms as $tokenForm) {
        $content = str_replace($tokenForm, '<token>', $content);
    }

    $content = str_replace($MATOMO_URL, $PROXY_URL, $content);
    $content = str_replace($matomoHost, $proxyHost, $content);

    if (isset($VALID_FILES)) {
        foreach ($VALID_FILES as $filepath) {
            // replace file paths to match the proxy and discard cb
            $content = preg_replace('^' . $filepath . '(\?cb\=[a-z0-9]*)?^', $PROXY_URL . 'matomo-proxy.php?file=' . $filepath, $content);
        }
    }

    return $content;
}

function forwardHeaders($content)
{
    global $httpResponseHeaders;

    $headersToForward = array(
        'content-type',
        'access-control-allow-origin',
        'access-control-allow-methods',
        'set-cookie',
    );

    foreach ($httpResponseHeaders as $header) {
        $parts = explode(':', $header);
        if (empty($parts[0])) {
            continue;
        }

        $name = trim(strtolower($parts[0]));
        if (in_array($name, $headersToForward)) {
            sendHeader($header);
        }
    }

    sendHeader('content-length: ' . strlen($content));
}

function getVisitIp()
{
    $ipKeys = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'HTTP_CF_CONNECTING_IP',
    );
    foreach ($ipKeys as $ipKey) {
        if (
            isset($_SERVER[$ipKey])
            && filter_var($_SERVER[$ipKey], FILTER_VALIDATE_IP) !== false
        ) {
            return $_SERVER[$ipKey];
        }
    }
    return arrayValue($_SERVER, 'REMOTE_ADDR');
}

function transformHeaderLine($headerLine)
{
    // if we're not on an https protocol, make sure cookies do not have 'secure;'
    if (empty($_SERVER['HTTPS']) && preg_match('/^set-cookie:/i', $headerLine)) {
        $headerLine = str_replace('secure;', '', $headerLine);
    }
    return $headerLine;
}

// captures a header line when using a curl request. would be better to use an anonymous function, but that would break
// PHP 5.2 support.
function handleHeaderLine($curl, $headerLine)
{
    global $httpResponseHeaders;

    $originalByteCount = strlen($headerLine);

    $headerLine = transformHeaderLine($headerLine);
    $httpResponseHeaders[] = trim($headerLine);

    return $originalByteCount;
}

function cookieNameIsAllowed($name, $allowlist)
{
    foreach ($allowlist as $entry) {
        $entry = trim($entry);
        if ($entry === '' || $entry === '*') {
            // No-op: guards against a typo silently allowing every cookie through.
            continue;
        }

        // Trailing '*' = prefix match; otherwise exact match only.
        if (substr($entry, -1) === '*') {
            $prefix = substr($entry, 0, -1);
            if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        } elseif ($name === $entry) {
            return true;
        }
    }

    return false;
}

function filterCookieHeader($cookieHeaderValue, $allowlist)
{
    $keptPairs = array();

    foreach (explode(';', $cookieHeaderValue) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        $parts = explode('=', $segment, 2);
        $name = trim($parts[0]);
        $value = isset($parts[1]) ? trim($parts[1]) : '';

        if (cookieNameIsAllowed($name, $allowlist)) {
            $keptPairs[] = $name . '=' . $value;
        }
    }

    return implode('; ', $keptPairs);
}

function getHttpContentAndStatus($url, $timeout, $user_agent, $postBody = '')
{
    global $httpResponseHeaders;
    global $DEBUG_PROXY;
    global $NO_VERIFY_SSL;
    global $http_ip_forward_header;
    global $COOKIE_ALLOWLIST;

    $useFopen = @ini_get('allow_url_fopen') == '1';

    $header = array();
    $header[] = sprintf("Accept-Language: %s", str_replace(array("\n", "\t", "\r"), "", arrayValue($_SERVER, 'HTTP_ACCEPT_LANGUAGE', '')));

    // NOTE: any changes made to Piwik\Plugins\PrivacyManager\DoNotTrackHeaderChecker must be made here as well
    if ((isset($_SERVER['HTTP_X_DO_NOT_TRACK']) && $_SERVER['HTTP_X_DO_NOT_TRACK'] === '1')) {
        $header[] = "X-Do-Not-Track: 1";
    }

    if ((isset($_SERVER['HTTP_DNT']) && substr($_SERVER['HTTP_DNT'], 0, 1) === '1')) {
        $header[] = "DNT: 1";
    }

    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookieHeaderValue = $_SERVER['HTTP_COOKIE'];
        if (isset($COOKIE_ALLOWLIST)) {
            if (!is_array($COOKIE_ALLOWLIST)) {
                // Fail closed: a misconfigured allowlist must not silently forward everything
                // unfiltered. error_log(), not trigger_error(), so this can never leak into the
                // response body when display_errors is on.
                error_log('$COOKIE_ALLOWLIST must be an array; treating it as empty (no cookies forwarded) until fixed.');
                $COOKIE_ALLOWLIST = array();
            }
            $cookieHeaderValue = filterCookieHeader($cookieHeaderValue, $COOKIE_ALLOWLIST);
        }
        if ($cookieHeaderValue !== '') {
            $header[] = "Cookie: " . $cookieHeaderValue;
        }
    }

    $stream_options = array(
        'http' => array(
            'user_agent' => $user_agent,
            'header'     => $header,
            'timeout'    => $timeout,
        ),
    );

    if ($DEBUG_PROXY) {
        $stream_options['http']['ignore_errors'] = true;
    }

    if ($NO_VERIFY_SSL) {
        $stream_options['ssl'] = array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        );
    }

    // Forward the visitor IP via the configured header, for every request method.
    if (!empty($http_ip_forward_header)) {
        $visitIp = getVisitIp();
        $stream_options['http']['header'][] = "$http_ip_forward_header: $visitIp";
    }

    // if there's POST data, send our proxy request as a POST
    if (!empty($_POST)) {
        $stream_options['http']['method'] = 'POST';
        $stream_options['http']['header'][] = "Content-type: application/x-www-form-urlencoded";
        $stream_options['http']['header'][] = "Content-Length: " . strlen($postBody);
        $stream_options['http']['content'] = $postBody;
    }

    if ($useFopen) {
        $ctx = stream_context_create($stream_options);

        if ($DEBUG_PROXY) {
            $content = file_get_contents($url, 0, $ctx);
        } else {
            $content = @file_get_contents($url, 0, $ctx);
        }

        $httpStatus = '';
        if (isset($http_response_header[0])) {
            $httpStatus = $http_response_header[0];
            $httpResponseHeaders = array_slice($http_response_header, 1);
            $httpResponseHeaders = array_map('transformHeaderLine', $httpResponseHeaders);
        }
    } else {
        if (!function_exists('curl_init')) {
            throw new Exception("You must either set allow_url_fopen=1 in your PHP configuration, or enable the PHP Curl extension.");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $stream_options['http']['user_agent']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $stream_options['http']['header']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $stream_options['http']['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $stream_options['http']['timeout']);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'handleHeaderLine');

        if (
            !empty($stream_options['http']['method'])
            && $stream_options['http']['method'] == 'POST'
        ) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $stream_options['http']['content']);
        }

        if (isset($stream_options['ssl']['verify_peer']) && $stream_options['ssl']['verify_peer'] == false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        if (isset($stream_options['ssl']['verify_peer_name']) && $stream_options['ssl']['verify_peer'] == false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $content = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!empty($httpStatus)) {
            $httpStatus = 'HTTP/1.1 ' . $httpStatus;
        }
        curl_close($ch);
    }

    return array(
        $content,
        $httpStatus,
    );
}

function sendHeader($header, $replace = true)
{
    headers_sent() || header($header, $replace);
}

function arrayValue($array, $key, $value = null)
{
    if (!empty($array[$key])) {
        $value = $array[$key];
    }
    return $value;
}

function clientProvidesAuthParams($params)
{
    if (!is_array($params)) {
        return false;
    }

    // A non-empty string token_auth counts as client auth, exactly as Matomo reads it; an empty or
    // array token is ignored by Matomo, so we must not treat it as one either.
    if (isset($params['token_auth']) && is_string($params['token_auth']) && $params['token_auth'] !== '') {
        return true;
    }

    // Params Matomo only honors for an authenticated request. Checked by key presence
    // (type-agnostic) so it cannot be evaded with array/empty values.
    $overrideParams = array('cdt', 'cdo', 'country', 'region', 'city', 'lat', 'long', 'cip');

    foreach ($overrideParams as $param) {
        if (array_key_exists($param, $params)) {
            return true;
        }
    }

    return false;
}

function withProxyTracking(
    $params,
    $visitIp,
    #[\SensitiveParameter]
    $tokenAuth,
    $includeProxyToken
) {
    // The entry is clean (no cip of its own), so set the real visitor IP.
    $params['cip'] = $visitIp;

    // Lend our token only when the caller decided to; otherwise a client token authorizes the cip.
    if ($includeProxyToken) {
        $params['token_auth'] = $tokenAuth;
    }

    return $params;
}

function bulkEntryProvidesAuthParams($request)
{
    // Parse an entry exactly like rewriteBulkEntry(), so the batch scan and the rewrite agree.
    if (is_string($request)) {
        $parsedUrl = @parse_url($request);
        if (empty($parsedUrl['query'])) {
            return false; // no query: Matomo ignores this entry
        }

        $params = array();
        @parse_str($parsedUrl['query'], $params);

        return clientProvidesAuthParams($params);
    }

    if (is_array($request)) {
        return clientProvidesAuthParams($request);
    }

    return false;
}

function rewriteBulkEntry(
    $request,
    $visitIp,
    #[\SensitiveParameter]
    $tokenAuth,
    $includeProxyToken
) {
    // Clean entries get our cip (plus our token when $includeProxyToken); an entry with its own auth
    // params is left untouched for Matomo to reject. Parsing mirrors Matomo's BulkTracking plugin.
    if (is_string($request)) {
        $parsedUrl = @parse_url($request);
        if (empty($parsedUrl['query'])) {
            return $request; // no query: Matomo ignores this entry
        }

        $params = array();
        @parse_str($parsedUrl['query'], $params);

        if (clientProvidesAuthParams($params)) {
            return $request;
        }

        return '?' . http_build_query(withProxyTracking($params, $visitIp, $tokenAuth, $includeProxyToken));
    }

    if (is_array($request)) {
        if (clientProvidesAuthParams($request)) {
            return $request;
        }

        return withProxyTracking($request, $visitIp, $tokenAuth, $includeProxyToken);
    }

    return $request;
}

function injectVisitIpIntoBulkRequest(
    $rawPostBody,
    $visitIp,
    #[\SensitiveParameter]
    $tokenAuth,
    #[\SensitiveParameter]
    $clientUrlToken = null
) {
    // Strip line breaks before decoding, as Matomo does (Common::sanitizeLineBreaks).
    $data = json_decode(str_replace(array("\n", "\r"), '', trim($rawPostBody)), true);

    // Not a decodable bulk request: forward unchanged and let Matomo deal with it.
    if (!is_array($data) || !isset($data['requests']) || !is_array($data['requests'])) {
        return $rawPostBody;
    }

    // Read the body token string-only, exactly as Matomo does.
    $clientHasBodyToken = isset($data['token_auth']) && is_string($data['token_auth']) && $data['token_auth'] !== '';
    $clientHasUrlToken = $clientUrlToken !== null;

    // Matomo reads the bulk token only from the body. Relocate a URL token there (when the body has
    // none) so our injected cip is authorized deterministically, not via Matomo's global $_GET fallback.
    if ($clientHasUrlToken && !$clientHasBodyToken) {
        $data['token_auth'] = $clientUrlToken;
        $clientHasBodyToken = true;
    }

    $clientAuthenticates = $clientHasUrlToken || $clientHasBodyToken;

    // Does any entry carry an auth-protected override param or its own token_auth?
    $anyOffendingEntry = false;
    foreach ($data['requests'] as $request) {
        if (bulkEntryProvidesAuthParams($request)) {
            $anyOffendingEntry = true;
            break;
        }
    }

    // A top-level token authorizes EVERY entry, so lend ours there only for a fully clean batch with
    // no client token - then it authorizes nothing but our injected cip, and it also satisfies Matomo's
    // bulk auth gate (bulk_requests_require_authentication checks each request's top-level token).
    $useTopLevelProxyToken = !$clientAuthenticates && !$anyOffendingEntry;

    if ($useTopLevelProxyToken) {
        $data['token_auth'] = $tokenAuth;
    }

    // Otherwise (mixed batch, no client token) fall back to per-entry tokens on the clean entries;
    // with a top-level token or client auth, entries get cip only. Per-entry tokens do NOT satisfy
    // Matomo's bulk auth gate (it checks each request's top-level token), so on a server with
    // bulk_requests_require_authentication=1 a mixed batch is rejected in full.
    $includeProxyTokenPerEntry = !$clientAuthenticates && !$useTopLevelProxyToken;

    foreach ($data['requests'] as $index => $request) {
        $data['requests'][$index] = rewriteBulkEntry($request, $visitIp, $tokenAuth, $includeProxyTokenPerEntry);
    }

    $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Fall back to the original body if re-encoding fails (e.g. invalid UTF-8 from parse_str),
    // so a malformed entry can never drop the whole batch.
    return $encoded === false ? $rawPostBody : $encoded;
}
