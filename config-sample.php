<?php

// Rename this to config.php and run php ./enanobot.php to start.

$server = 'irc.freenode.net';
$nick = 'EnanoBot';
$pass = '';
$name = 'Enano CMS logging/message bot';
$user = 'enano';
$permissions = array(
    'your' => array('admin', 'alert'),
    'nick' => array('admin', 'alert'),
    'list' => array('echo', 'suspend', 'pm', 'channel' => array('#enano', '#ubuntu')),
    'here' => array('echo', 'suspend', 'pm', 'shutdown', 'channel' => array('#enano')),
  );
$mysql_host = 'localhost';
$mysql_user = '';
$mysql_pass = '';
$mysql_dbname = '';
$channels = array('#mychan', '#yourchan', '#4chan');

?>
