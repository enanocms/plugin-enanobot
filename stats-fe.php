<?php

/**
 * Frontend for statistics data. Handles fetching and calculating data from raw statistics stored in stats-data.php.
 * @package EnanoBot
 * @subpackage stats
 * @author Dan Fuhry <dan@enanocms.org>
 */

$stats_merged_data = array('counts' => array(), 'messages' => array());
$stats_data =& $stats_merged_data;

define('ENANOBOT_ROOT', dirname(__FILE__));
define('NOW', time());

/**
 * Gets the number of messages posted in IRC in the last X minutes.
 * @param string Channel
 * @param int Optional - time period for message count. Defaults to 10 minutes.
 * @param int Optional - Base time, defaults to right now
 * @return int
 */

function stats_message_count($channel, $mins = 10, $base = NOW)
{
  global $stats_merged_data;
  
  $time_min = $base - ( $mins * 60 );
  $time_max = $base;
  
  if ( !isset($stats_merged_data['messages'][$channel]) )
  {
    return 0;
  }
  
  $count = 0;
  foreach ( $stats_merged_data['messages'][$channel] as $message )
  {
    if ( $message['time'] >= $time_min && $message['time'] <= $time_max )
    {
      $count++;
    }
  }
  
  return $count;
}

/**
 * Gets the percentages as to who's posted the most messages in the last X minutes.
 * @param string Channel name
 * @param int Optional - How many minutes, defaults to 10
 * @param int Optional - Base time, defaults to right now
 * @return array Associative, with floats.
 */

function stats_activity_percent($channel, $mins = 10, $base = NOW)
{
  global $stats_merged_data;
  if ( !($total = stats_message_count($channel, $mins, $base)) )
  {
    return array();
  }
  $results = array();
  $usercounts = array();
  $time_min = $base - ( $mins * 60 );
  $time_max = $base;
  foreach ( $stats_merged_data['messages'][$channel] as $message )
  {
    if ( $message['time'] >= $time_min && $message['time'] <= $time_max )
    {
      if ( !isset($usercounts[$message['nick']]) )
        $usercounts[$message['nick']] = 0;
      $usercounts[$message['nick']]++;
    }
  }
  foreach ( $usercounts as $nick => $count )
  {
    $results[$nick] = $count / $total;
  }
  arsort($results);
  return $results;
}

/**
 * Loads X days of statistics, minimum.
 * @param int Days to load, default is 1
 */
 
function load_stats_data($days = 1)
{
  $days++;
  for ( $i = 0; $i < $days; $i++ )
  {
    $day = NOW - ( $i * 86400 );
    $day = gmdate('Ymd', $day);
    if ( file_exists(ENANOBOT_ROOT . "/stats/stats-data-$day.php") )
    {
      require(ENANOBOT_ROOT . "/stats/stats-data-$day.php");
      stats_merge($stats_data);
    }
  }
}

/**
 * Return the time that the stats DB was last updated.
 * @return int
 */

function stats_last_updated()
{
  $day = gmdate('Ymd');
  $file = ENANOBOT_ROOT . "/stats/stats-data-$day.php";
  return ( file_exists($file) ) ? filemtime($file) : 0;
}

/**
 * Merges a newly loaded stats array with the current cache in RAM.
 * @param array Data to merge
 * @access private
 */

function stats_merge($data)
{
  global $stats_merged_data;
  if ( isset($data['counts']) )
  {
    foreach ( $data['counts'] as $channel => $chaninfo )
    {
      if ( isset($stats_merged_data['counts'][$channel]) )
      {
        foreach ( $stats_merged_data['counts'][$channel] as $key => &$value )
        {
          if ( is_int($value) )
          {
            $value = max($value, $chaninfo[$key]);
          }
          else if ( is_array($value) )
          {
            $value = array_merge($value, $chaninfo[$key]);
          }
        }
      }
      else
      {
        $stats_merged_data['counts'][$channel] = $chaninfo;
      }
    }
  }
  foreach ( $data['messages'] as $channel => $chandata )
  {
    if ( isset($stats_merged_data['messages'][$channel]) )
    {
      foreach ( $chandata as $message )
      {
        $stats_merged_data['messages'][$channel][] = $message;
      }
    }
    else
    {
      $stats_merged_data['messages'][$channel] = $chandata;
    }
  }
}

load_stats_data();

