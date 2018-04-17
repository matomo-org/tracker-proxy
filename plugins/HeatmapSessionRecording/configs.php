<?php

# proxy endpoint to support HeatmapSessionRecording tracker which sends requests to this file

define('MATOMO_PROXY_FROM_ENDPOINT', 1);

$path = 'plugins/HeatmapSessionRecording/configs.php';

include dirname(__FILE__) . '/../../proxy.php';