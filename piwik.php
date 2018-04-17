<?php
/**
 * Piwik - free/libre analytics platform
 * Piwik Proxy Hide URL
 *
 * @link http://piwik.org/faq/how-to/#faq_132
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

define('MATOMO_PROXY_FROM_ENDPOINT', 1);

$path = "piwik.php";

include dirname(__FILE__) . '/proxy.php';
