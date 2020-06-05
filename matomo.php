<?php
/**
 * Matomo - free/libre analytics platform
 * Matomo Proxy Hide URL
 *
 * @link https://matomo.org/faq/how-to/#faq_132
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

define('MATOMO_PROXY_FROM_ENDPOINT', 1);

$path = "matomo.php";

include __DIR__ . '/proxy.php';
