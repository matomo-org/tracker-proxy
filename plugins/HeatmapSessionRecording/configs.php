<?php

# proxy endpoint to support HeatmapSessionRecording tracker which sends requests to this file

$path = 'plugins/HeatmapSessionRecording/configs.php';

include dirname(__FILE__) . '/../../proxy.php';