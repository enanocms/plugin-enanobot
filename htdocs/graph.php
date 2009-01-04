<?php
require('../stats-fe.php');
require('../graphs.php');
require('../timezone.php');

$channel_list = stats_channel_list();
$first_channel = $channel_list[0];
$channel = ( isset($_REQUEST['channel']) && in_array($_REQUEST['channel'], $channel_list) ) ? $_REQUEST['channel'] : $first_channel;

function makeGraph($type = 'bar')
{
  $class = ( $type == 'line' ) ? 'LineGraph' : 'BarGraph';
  $g = new $class(); // _Compat();
  
  $g->SetGraphAreaHeight(200);
  $g->SetGraphPadding(30, 30, 20, 15);
  if ( get_class($g) == 'BarGraph' )
  {
    $g->SetBarPadding(7);
    $g->SetBarFont(1);
    $g->SetBarDimensions(25, 7);
  }
  else if ( get_class($g) == 'LineGraph' )
  {
    $g->SetGraphAreaWidth(800);
  }
  $g->SetAxisDeepness(7);
  $g->SetGraphTitleFont(2);
  $g->SetGraphBackgroundTransparent(false, 240, 250, 255);
  $g->SetGraphTransparency(25);
  $g->SetAxisScaleColor(90, 90, 90);
  $g->SetAxisScaleFont(1);
  $g->SetScaleRoundY(0);
  $g->SetScaleRoundX(0);
  $g->SetAxisStepSize(7);
  return $g;
}

// generate the data
// we're doing this by absolute hours, not by strictly "24 hours ago", e.g. on-the-hour stats
$mode = ( isset($_GET['mode']) ) ? $_GET['mode'] : 'lastday';
switch ( $mode )
{
  case 'lastday':
  default:
    $g = makeGraph();
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
    $g = makeGraph();
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
    $g = makeGraph();
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
  case 'lasthour':
    $g = makeGraph('line');
    $g->SetAxisStepX(5);
    $g->SetScaleFunctionX('lasthour_scaler');
    function lasthour_scaler($v, $g)
    {
      $k = array_keys($g->data);
      return ( isset($k[$v]) ) ? $k[$v] : 'now';
    }
    $graph_title = $channel . ' activity - last hour';
    $data = stats_raw_data($channel, 59);
    $agg = array();
    foreach ( $data as $message )
    {
      $tid = intval(ltrim(date('i', $message['time']), '0'));
      if ( !isset($agg[$tid]) )
        $agg[$tid] = 0;
      $agg[$tid]++;
    }
    $graphdata = array();
    $minutenow = intval(ltrim(date('i', NOW), '0'));
    $hournow = intval(ltrim(date('H', NOW), '0'));
    $hourthen = intval(ltrim(date('H', NOW-3600), '0'));
    for ( $i = $minutenow + 1; $i < 60; $i++ )
    {
      $istr = ( $i < 10 ) ? "0$i" : "$i";
      $graphdata["$hourthen:$istr"] = ( isset($agg[$i]) ) ? $agg[$i] : 0;
    }
    for ( $i = 0; $i <= $minutenow; $i++ )
    {
      $istr = ( $i < 10 ) ? "0$i" : "$i";
      $graphdata["$hournow:$istr"] = ( isset($agg[$i]) ) ? $agg[$i] : 0;
    }
    
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

$g->data = $graphdata;

$g->SetGraphTitle($graph_title);
$g->SetAxisStepY($interval);

$g->DrawGraph();

