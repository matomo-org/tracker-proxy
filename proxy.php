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

// 2) MATOMO.PHP PROXY: GET parameters found, this is a tracking request, we redirect it to Piwik
if (strpos($path, '?') === false) {
    $path = $path . '?';
}

$extraQueryParams = array();
if (strpos($path, 'piwik.php') === 0 || strpos($path, 'matomo.php') === 0) {
    $extraQueryParams = array(
        'cip' => getVisitIp(),
        'token_auth' => $TOKEN_AUTH,
    );
}

$url = $MATOMO_URL . $path;
$url .= http_build_query(array_merge($extraQueryParams, $_GET));

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    // PHP 5.2 breaks with the new 204 status code so we force returning the image every time
    list($content, $httpStatus) = getHttpContentAndStatus($url . '&send_image=1', $timeout, $user_agent);
    $content = sanitizeContent($content);

    forwardHeaders($content);

    echo $content;
} else {
    // PHP 5.3 and above
    list($content, $httpStatus) = getHttpContentAndStatus($url, $timeout, $user_agent);
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

    $content = str_replace($TOKEN_AUTH, '<token>', $content);
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

function getHttpContentAndStatus($url, $timeout, $user_agent)
{
    global $httpResponseHeaders;
    global $DEBUG_PROXY;
    global $NO_VERIFY_SSL;
    global $http_ip_forward_header;

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
        $header[] = "Cookie: " . $_SERVER['HTTP_COOKIE'];
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

    // if there's POST data, send our proxy request as a POST
    if (!empty($_POST)) {
        $postBody = file_get_contents("php://input");

        $stream_options['http']['method'] = 'POST';
        $stream_options['http']['header'][] = "Content-type: application/x-www-form-urlencoded";
        $stream_options['http']['header'][] = "Content-Length: " . strlen($postBody);
        $stream_options['http']['content'] = $postBody;

        if (!empty($http_ip_forward_header)) {
            $visitIp = getVisitIp();
            $stream_options['http']['header'][] = "$http_ip_forward_header: $visitIp";
        }
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
