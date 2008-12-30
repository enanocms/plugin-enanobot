<?php
require('../stats-fe.php');
require('../graphs.php');
require('../timezone.php');

$channel_list = stats_channel_list();
$first_channel = $channel_list[0];
$channel = ( isset($_REQUEST['channel']) && in_array($_REQUEST['channel'], $channel_list) ) ? $_REQUEST['channel'] : $first_channel;

$g = new GraphMaker(); // _Compat();

$g->SetGraphPadding(20, 30, 20, 15);
$g->SetGraphAreaHeight(200);
$g->SetBarDimensions(26, 0);
$g->SetBarPadding(7);
$g->SetGraphBackgroundTransparent(240, 250, 255, 0);
$g->SetGraphTransparency(25);

// generate the data
// we're doing this by absolute hours, not by strictly "24 hours ago", e.g. on-the-hour stats
$mode = ( isset($_GET['mode']) ) ? $_GET['mode'] : 'lastday';
switch ( $mode )
{
  case 'lastday':
  default:
    $graph_title = $channel . ' message count - last 24 hours';
    $this_hour = gmmktime(gmdate('H'), 0, 0);
    $graphdata = array();
    
    for ( $i = 23; $i >= 0; $i-- )
    {
      $basetime = $this_hour - ( $i * 3600 );
      $ts = date('H:i', $basetime);
      $basetime += 3600;
      $graphdata[$ts] = stats_message_count($channel, 60, $basetime);
    }
    break;
  case 'lastweek':
    $graph_title = $channel . ' activity - last 14 days';
    $this_day = gmmktime(0, 0, 0);
    $graphdata = array();
    
    for ( $i = 13; $i >= 0; $i-- )
    {
      $basetime = $this_day - ( $i * 86400 );
      $ts = date('D n/j', $basetime);
      $basetime += 86400;
      $graphdata[$ts] = stats_message_count($channel, 1440, $basetime);
    }
    $g->SetBarPadding(12);
    break;
  case 'lastmonth':
    $graph_title = $channel . ' activity - last 30 days';
    $this_day = gmmktime(0, 0, 0);
    $graphdata = array();
    
    for ( $i = 29; $i >= 0; $i-- )
    {
      $basetime = $this_day - ( $i * 86400 );
      $ts = date('Y-m-d', $basetime);
      $basetime += 86400;
      $graphdata[$ts] = stats_message_count($channel, 1440, $basetime);
    }
    $g->SetBarPadding(15);
    break;
}

$max = max($graphdata);

// Determine axis interval
$interval = 2;
if ( $max > 20 )
  $interval = 4;
if ( $max > 25 )
  $interval = 5;
if ( $max > 50 )
  $interval = 10;
if ( $max > 200 )
  $interval = 40;
if ( $max > 500 )
  $interval = 80;
if ( $max > 1000 )
  $interval = 100;
if ( $max > 2000 )
  $interval = 200;
if ( $max > 3200 )
  $interval = 300;
if ( $max > 4000 )
  $interval = 500;
if ( $max > 5000 )
  $interval = 1000;
if ( $max > 15000 )
  $interval = 1500;
if ( $max > 30000 )
  $interval = round($max / 10);

$g->SetBarData($graphdata);
$g->SetAxisStep($interval);
$g->SetGraphTitle($graph_title);

$g->DrawGraph();

