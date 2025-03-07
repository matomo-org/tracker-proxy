<?php
/**
 * Matomo - free/libre analytics platform
 * Matomo Proxy Hide URL
 *
 * @link https://matomo.org/faq/how-to/#faq_132
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$configFile = __DIR__ . '/../config.php';
if (file_exists($configFile)) {
    include $configFile;
}

$calledJsFile = $_SERVER['REQUEST_URI'];

if (strpos($calledJsFile, 'js') === false) {
    exit();
}

// -----
// Important: read the instructions in README.md or at:
// https://github.com/matomo-org/tracker-proxy#matomo-tracker-proxy
// -----
//// FALLBACK CONFIG IF config.php IS NOT USED

// Edit the line below, and replace http://your-matomo-domain.example.org/matomo/
// with your Matomo URL ending with a slash.
// This URL will never be revealed to visitors or search engines.
if (! isset($MATOMO_URL)) {
    $MATOMO_URL = 'http://your-matomo-domain.example.org/matomo/';
}

if (! isset($MATOMO_URL)) {
    $MATOMO_TRUSTED_URLS = [
        // Edit the lines below, and replace http://your-matomo-domain.example.org/matomo/
        // with your Matomo URL ending with a slash.
        // Also add any other trusted_hosts[] - config you have in config/config.ini.php
        // These URLs will never be revealed to visitors or search engines using the tag manager.
        'http://your-matomo-domain.example.org/matomo/'
    ];
}

// Edit the line below and replace http://your-tracker-proxy.org/ with the URL to your tracker-proxy
// setup. This URL will be used in Matomo output that contains the Matomo URL, so your Matomo is effectively
// hidden.
if (! isset($PROXY_URL)) {
    $PROXY_URL = 'http://your-tracker-proxy.org/';
}

$fileName = $MATOMO_URL . $calledJsFile;
$fileContent = file_get_contents($fileName);

echo str_replace($MATOMO_TRUSTED_URLS, $PROXY_URL, $fileContent);
