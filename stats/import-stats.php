<?php

require('../config.php');
require('../database.php');
require('../hooks.php');
require('../libprogress.php');
require('../statsincludes/stats_core.php');
mysql_reconnect();

// PASS 1 - build file list
$filelist = array();
if ( $dr = @opendir('.') )
{
  while ( $dh = @readdir($dr) )
  {
    if ( preg_match('/^stats-data-[0-9]{8}\.php$/', $dh) )
    {
      $filelist[] = $dh;
    }
  }
  closedir($dr);
}

asort($filelist);

// PASS 2 - import
$pbar = new ProgressBar('Importing: [', ']', '');
$pbar->start();
$pbar->set(0);
$i = 0;
foreach ( $filelist as $dh )
{
  $pbar->update_text_quiet($dh);
  $pbar->set(++$i, count($filelist));
  require($dh);
  foreach ( $stats_data['messages'] as $channel => &$data )
  {
    foreach ( $data as &$message )
    {
      stats_log_message($channel, $message['nick'], $message['time']);
    }
  }
  if ( isset($stats_data['anonymous']) )
  {
    foreach ( $stats_data['anonymous'] as $user => $_ )
    {
      stats_anonymize_user_now($user);
    }
  }
}

$pbar->end();

