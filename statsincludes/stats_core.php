<?php

$stats_anonymize_list = array();

eb_hook('startup_early', 'stats_core_cache_anons();');

function stats_core_cache_anons()
{
  global $stats_anonymize_list;
  if ( $q = eb_mysql_query('SELECT nick FROM stats_anon;') )
  {
    while ( $row = mysql_fetch_assoc($q) )
    {
      $stats_anonymize_list[] = $row['nick'];
    }
  }
}

function stats_log_message($channel, $nick, $timestamp)
{
  // anonymize message?
  global $stats_anonymize_list;
  if ( in_array($nick, $stats_anonymize_list) )
  {
    $nick = 'Anonymous';
  }
  
  $channel = db_escape($channel);
  $nick = db_escape($nick);
  $sql = 'INSERT INTO stats_messages(channel, nick, time) ' . "VALUES('$channel', '$nick', " . intval($timestamp) . ");";
  eb_mysql_query($sql);
}

function stats_anonymize_user_now($nick)
{
  global $stats_anonymize_list;
  // anonymize list is cached in RAM
  if ( in_array($nick, $stats_anonymize_list) )
  {
    return false;
  }
  
  $stats_anonymize_list[] = $nick;
  
  $nick = db_escape($nick);
  eb_mysql_query("INSERT INTO stats_anon(nick) VALUES('$nick');");
  
  return true;
}

function stats_anonymize_user_past($nick)
{
  global $stats_anonymize_list;
  if ( !in_array($nick, $stats_anonymize_list) )
  {
    return false;
  }
  
  $nick = db_escape($nick);
  eb_mysql_query("UPDATE stats_messages SET nick = 'Anonymous' WHERE nick = '$nick';");
  return true;
}

function stats_denonymize_user($nick)
{
  global $stats_anonymize_list;
  if ( !in_array($nick, $stats_anonymize_list) )
  {
    return false;
  }
  
  $nick = db_escape($nick);
  eb_mysql_query("DELETE FROM stats_anon WHERE nick = '$nick';");
  
  unset($stats_anonymize_list[ array_search($nick, $stats_anonymize_list) ]);
  return true;
}

function stats_del_user($chan, $nick)
{
  $chan = db_escape($chan);
  $nick = db_escape($nick);
  eb_mysql_query("DELETE FROM stats_messages WHERE channel = '$chan' AND nick = '$nick';");
}

