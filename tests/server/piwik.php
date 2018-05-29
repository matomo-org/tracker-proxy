<?php

require_once __DIR__ . '/../../config.php';

if (!isset($_GET['send_image']) || $_GET['send_image'] == 1) {
    header('Content-Type: image/gif');
}

if (isset($_GET['status'])) {
    http_response_code($_GET['status']);
}

var_export($_GET);
if (!empty($_POST)) {
    echo "\n";
    var_export($_POST);
}

$headers = array();
foreach (array('DNT', 'X_DO_NOT_TRACK') as $headerName) {
    if (isset($_SERVER['HTTP_' . $headerName])) {
        $headers[$headerName] = $_SERVER['HTTP_' . $headerName];
    }
}
if (!empty($headers)) {
    echo "\n";
    var_export($headers);
}

if (!empty($_GET['debug'])) {
    $host = parse_url($PIWIK_URL, PHP_URL_HOST);
    echo "\nHOST: $host\n";
    echo "URL: $PIWIK_URL\n";
    echo "TOKEN_AUTH: $TOKEN_AUTH\n";
}

if (isset($_GET['module'], $_GET['action']) && $_GET['module'] == 'CoreAdminHome' && $_GET['action'] == 'optOut') {
    echo "\n" . '...some html here... <script src="plugins/CoreAdminHome/javascripts/optOut.js">...more html here...';
}

// For PHP 5.3 support
if (!function_exists('http_response_code')) {
    function http_response_code($code = null) {
        switch ($code) {
            case 100:
                $text = 'Continue';
                break;
            case 101:
                $text = 'Switching Protocols';
                break;
            case 200:
                $text = 'OK';
                break;
            case 201:
                $text = 'Created';
                break;
            case 202:
                $text = 'Accepted';
                break;
            case 203:
                $text = 'Non-Authoritative Information';
                break;
            case 204:
                $text = 'No Content';
                break;
            case 205:
                $text = 'Reset Content';
                break;
            case 206:
                $text = 'Partial Content';
                break;
            case 300:
                $text = 'Multiple Choices';
                break;
            case 301:
                $text = 'Moved Permanently';
                break;
            case 302:
                $text = 'Moved Temporarily';
                break;
            case 303:
                $text = 'See Other';
                break;
            case 304:
                $text = 'Not Modified';
                break;
            case 305:
                $text = 'Use Proxy';
                break;
            case 400:
                $text = 'Bad Request';
                break;
            case 401:
                $text = 'Unauthorized';
                break;
            case 402:
                $text = 'Payment Required';
                break;
            case 403:
                $text = 'Forbidden';
                break;
            case 404:
                $text = 'Not Found';
                break;
            case 405:
                $text = 'Method Not Allowed';
                break;
            case 406:
                $text = 'Not Acceptable';
                break;
            case 407:
                $text = 'Proxy Authentication Required';
                break;
            case 408:
                $text = 'Request Time-out';
                break;
            case 409:
                $text = 'Conflict';
                break;
            case 410:
                $text = 'Gone';
                break;
            case 411:
                $text = 'Length Required';
                break;
            case 412:
                $text = 'Precondition Failed';
                break;
            case 413:
                $text = 'Request Entity Too Large';
                break;
            case 414:
                $text = 'Request-URI Too Large';
                break;
            case 415:
                $text = 'Unsupported Media Type';
                break;
            case 500:
                $text = 'Internal Server Error';
                break;
            case 501:
                $text = 'Not Implemented';
                break;
            case 502:
                $text = 'Bad Gateway';
                break;
            case 503:
                $text = 'Service Unavailable';
                break;
            case 504:
                $text = 'Gateway Time-out';
                break;
            case 505:
                $text = 'HTTP Version not supported';
                break;
            default:
                exit('Unknown http status code "' . htmlentities($code) . '"');
                break;
        }

        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

        header($protocol . ' ' . $code . ' ' . $text);
    }
}
