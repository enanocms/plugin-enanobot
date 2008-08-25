<?php

/**
 * Frontend for statistics data. Handles fetching and calculating data from raw statistics stored in stats-data.php.
 * @package EnanoBot
 * @subpackage stats
 * @author Dan Fuhry <dan@enanocms.org>
 */

if ( !isset($GLOBALS['stats_data']) )
{
  require(dirname(__FILE__) . '/stats-data.php');
  $data =& $stats_data;
}

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
  global $data;
  
  $time_min = $base - ( $mins * 60 );
  $time_max = $base;
  
  if ( !isset($data['messages'][$channel]) )
  {
    return 0;
  }
  
  $count = 0;
  foreach ( $data['messages'][$channel] as $message )
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
  global $data;
  if ( !($total = stats_message_count($channel, $mins, $base)) )
  {
    return array();
  }
  $results = array();
  $usercounts = array();
  $time_min = $base - ( $mins * 60 );
  $time_max = $base;
  foreach ( $data['messages'][$channel] as $message )
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
