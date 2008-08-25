<?php
require('../stats-data.php');
require('../libjson.php');

header('Content-type: text/plain');
echo eb_json_encode($stats_data);
