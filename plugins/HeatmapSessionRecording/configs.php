<?php

# proxy endpoint to support HeatmapSessionRecording tracker which sends requests to this file

define('DISABLE_SEND_IMAGE', 1);

$path = 'plugins/HeatmapSessionRecording/configs.php';

include dirname(__FILE__) . '/../../piwik.php';