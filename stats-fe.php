<?php

/**
 * Frontend for statistics data. Handles fetching and calculating data from raw statistics stored in stats-data.php.
 * @package EnanoBot
 * @subpackage stats
 * @author Dan Fuhry <dan@enanocms.org>
 */

define('ENANOBOT_ROOT', dirname(__FILE__));
define('NOW', time());

require(ENANOBOT_ROOT . '/config.php');
require(ENANOBOT_ROOT . '/hooks.php');
require(ENANOBOT_ROOT . '/database.php');

mysql_reconnect();

/**
 * Gets ths list of channels.
 * @return array
 */

function stats_channel_list()
{
  return $GLOBALS['channels'];
}

/**
 * Gets the number of messages posted in IRC in the last X minutes.
 * @param string Channel
 * @param int Optional - time period for message count. Defaults to 10 minutes.
 * @param int Optional - Base time, defaults to right now
 * @return int
 */

function stats_message_count($channel, $mins = 10, $base = NOW)
{
  $channel = db_escape($channel);
  $time_min = $base - ( $mins * 60 );
  $time_max =& $base;
  if ( $q = eb_mysql_query("SELECT message_count FROM stats_count_cache WHERE time_min = $time_min AND time_max = $time_max AND channel = '$channel';") )
  {
    if ( mysql_num_rows($q) > 0 )
    {
      $row = mysql_fetch_assoc($q);
      mysql_free_result($q);
      return intval($row['message_count']);
    }
    mysql_free_result($q);
  }
  if ( $q = eb_mysql_query("SELECT COUNT(message_id) FROM stats_messages WHERE channel = '$channel' AND time >= $time_min AND time <= $time_max;") )
  {
    $row = mysql_fetch_row($q);
    $count = $row[0];
    mysql_free_result($q);
    // avoid caching future queries
    if ( $base <= NOW )
    {
      eb_mysql_query("INSERT INTO stats_count_cache(channel, time_min, time_max, message_count) VALUES('$channel', $time_min, $time_max, $count);");
    }
    return $count;
  }
  return false;
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
  $channel = db_escape($channel);
  $time_min = $base - ( $mins * 60 );
  $time_max =& $base;
  
  if ( $q = eb_mysql_query("SELECT nick FROM stats_messages WHERE channel = '$channel' AND time >= $time_min AND time <= $time_max;") )
  {
    $userdata = array();
    while ( $row = @mysql_fetch_assoc($q) )
    {
      $total++;
      if ( isset($userdata[ $row['nick'] ]) )
      {
        $userdata[ $row['nick'] ]++;
      }
      else
      {
        $userdata[ $row['nick'] ] = 1;
      }
    }
    foreach ( $userdata as &$val )
    {
      $val = $val / $total;
    }
    mysql_free_result($q);
    arsort($userdata);
    return $userdata;
  }
  return false;
}

/**
 * Return the time that the stats DB was last updated.
 * @return int
 */

function stats_last_updated()
{
  // :-D
  return NOW;
}


