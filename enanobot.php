<?php
// define('LIBIRC_DEBUG', '');
require('libirc.php');
require('config.php');

@ini_set('display_errors', 'on');

$mysql_conn = false;

function mysql_reconnect()
{
  global $mysql_conn, $mysql_host, $mysql_user, $mysql_pass, $mysql_dbname;
  if ( $mysql_conn )
    @mysql_close($mysql_conn);
  // connect to MySQL
  $mysql_conn = @mysql_connect($mysql_host, $mysql_user, $mysql_pass);
  if ( !$mysql_conn )
  {
    $m_e = mysql_error();
    echo "Error connecting to MySQL: $m_e\n";
    exit(1);
  }
  $q = @mysql_query("USE `$mysql_dbname`;", $mysql_conn);
  if ( !$q )
  {
    $m_e = mysql_error();
    echo "Error selecting database: $m_e\n";
    exit(1);
  }
}

function eb_mysql_query($sql, $conn = false)
{
  global $mysql_conn, $irc;
  $m_et = false;
  while ( true )
  {
    $q = mysql_query($sql, $mysql_conn);
    if ( !$q )
    {
      $m_e = mysql_error();
      if ( strpos($m_e, 'gone away') && !$m_et )
      {
        mysql_reconnect();
        continue;
      }
      $m_et = true;
      $irc->close("MySQL query error: $m_e");
      exit(1);
    }
    break;
  }
  return $q;
}

mysql_reconnect();

$irc = new Request_IRC('irc.freenode.net');
$irc->connect($nick, $user, $name, $pass);
$irc->set_privmsg_handler('enanobot_privmsg_event');
$enano = $irc->join('#enano', 'enanobot_channel_event_enano');
$enano_dev = $irc->join('#enano-dev', 'enanobot_channel_event_enanodev');
$irc->privmsg('ChanServ', 'OP #enano EnanoBot');
$irc->privmsg('ChanServ', 'OP #enano-dev EnanoBot');

$irc->event_loop();
$irc->close();
mysql_close($mysql_conn);

function enanobot_channel_event_enano($sockdata, $chan)
{
  global $irc, $nick, $mysql_conn, $privileged_list;
  $sockdata = trim($sockdata);
  $message = Request_IRC::parse_message($sockdata);
  enanobot_log_message($chan, $message);
  switch ( $message['action'] )
  {
    case 'JOIN':
      // if a known op joins the channel, send mode +o
      if ( in_array($message['nick'], $privileged_list) )
      {
        $chan->parent->put("MODE #enano +o {$message['nick']}\r\n");
      }
      break;
    case 'PRIVMSG':
      enanobot_process_channel_message($sockdata, $chan, $message);
      break;
  }
}

function enanobot_channel_event_enanodev($sockdata, $chan)
{
  global $irc, $privileged_list;
  $sockdata = trim($sockdata);
  $message = Request_IRC::parse_message($sockdata);
  enanobot_log_message($chan, $message);
  switch ( $message['action'] )
  {
    case 'JOIN':
      // if dandaman32 joins the channel, use mode +o
      if ( in_array($message['nick'], $privileged_list) )
        $chan->parent->put("MODE #enano-dev +o {$message['nick']}\r\n");
      break;
    case 'PRIVMSG':
      enanobot_process_channel_message($sockdata, $chan, $message);
      break;
  }
}

function enanobot_process_channel_message($sockdata, $chan, $message)
{
  global $irc, $nick, $mysql_conn, $privileged_list;
  
  if ( preg_match('/^\!echo /', $message['message']) && in_array($message['nick'], $privileged_list) )
  {
    $chan->msg(preg_replace('/^\!echo /', '', $message['message']), true);
  }
  else if ( preg_match('/^\![\s]*([a-z0-9_-]+)([\s]*\|[\s]*([^ ]+))?$/', $message['message'], $match) )
  {
    $snippet =& $match[1];
    if ( @$match[3] === 'me' )
      $match[3] = $message['nick'];
    $target_nick = ( !empty($match[3]) ) ? "{$match[3]}, " : "{$message['nick']}, ";
    if ( $snippet == 'snippets' )
    {
      // list available snippets
      $m_et = false;
      $q = eb_mysql_query('SELECT snippet_code, snippet_channels FROM snippets;');
      if ( mysql_num_rows($q) < 1 )
      {
        $chan->msg("{$message['nick']}, I couldn't find that snippet (\"$snippet\") in the database.", true);
      }
      else
      {
        $snippets = array();
        while ( $row = mysql_fetch_assoc($q) )
        {
          $channels = explode('|', $row['snippet_channels']);
          if ( in_array($chan->get_channel_name(), $channels) )
          {
            $snippets[] = $row['snippet_code'];
          }
        }
        $snippets = implode(', ', $snippets);
        $chan->msg("{$message['nick']}, the following snippets are available: $snippets", true);
      }
      @mysql_free_result($q);
    }
    else
    {
      // Look for the snippet...
      $q = eb_mysql_query('SELECT snippet_text, snippet_channels FROM snippets WHERE snippet_code = \'' . mysql_real_escape_string($snippet) . '\';');
      if ( mysql_num_rows($q) < 1 )
      {
        $chan->msg("{$message['nick']}, I couldn't find that snippet (\"$snippet\") in the database.", true);
      }
      else
      {
        $row = mysql_fetch_assoc($q);
        $channels = explode('|', $row['snippet_channels']);
        if ( in_array($chan->get_channel_name(), $channels) )
        {
          $chan->msg("{$target_nick}{$row['snippet_text']}", true);
        }
        else
        {
          $chan->msg("{$message['nick']}, I couldn't find that snippet (\"$snippet\") in the database.", true);
        }
      }
      @mysql_free_result($q);
    }
  }
  else if ( strpos($message['message'], $nick) && !in_array($message['nick'], $privileged_list) && $message['nick'] != $nick )
  {
    $target_nick =& $message['nick'];
    $chan->msg("{$target_nick}, I'm only a bot. :-) You should probably rely on the advice of humans if you need further assistance.", true);
  }
}

function enanobot_log_message($chan, $message)
{
  global $nick;
  
  // Log the message
  $chan_db = mysql_real_escape_string($chan->get_channel_name());
  $nick_db = mysql_real_escape_string($message['nick']);
  $line_db = mysql_real_escape_string($message['message']);
  $day     = date('Y-m-d');
  $time    = time();
  $m_et = false;
  $sql = false;
  switch($message['action'])
  {
    case 'PRIVMSG':
      if ( substr($line_db, 0, 5) != '[off]' )
      {
        $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                  ( '$chan_db', '$day', '$nick_db', '$time', '$line_db' );";
      }
      break;
    case 'JOIN':
      $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                ( '$chan_db', '$day', '', '$time', '$nick_db has joined $chan_db' );";
      break;
    case 'PART':
      $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                ( '$chan_db', '$day', '', '$time', '$nick_db has left $chan_db' );";
      break;
    case 'MODE':
      list($mode, $target_nick) = explode(' ', $line_db);
      if ( $message['nick'] != 'ChanServ' && $target_nick != $nick )
      {
        $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                  ( '$chan_db', '$day', '', '$time', '$nick_db set mode $mode on $target_nick' );";
      }
      break;
  }
  if ( $sql )
  {
    eb_mysql_query($sql);
  }
}

function enanobot_privmsg_event($message)
{
  global $privileged_list, $irc;
  static $part_cache = array();
  if ( in_array($message['nick'], $privileged_list) && $message['message'] == 'Suspend' && $message['action'] == 'PRIVMSG' )
  {
    foreach ( $irc->channels as $channel )
    {
      $part_cache[] = array($channel->get_channel_name(), $channel->get_handler());
      $channel->msg("I've received a request to stop logging messages and responding to requests from {$message['nick']}. Don't forget to unsuspend me with /msg EnanoBot Resume when finished.", true);
      $channel->part("Logging and presence suspended by {$message['nick']}", true);
    }
  }
  else if ( in_array($message['nick'], $privileged_list) && $message['message'] == 'Resume' && $message['action'] == 'PRIVMSG' )
  {
    global $nick;
    foreach ( $part_cache as $chan_data )
    {
      $chan_name = substr($chan_data[0], 1);
      $GLOBALS[$chan_name] = $irc->join($chan_data[0], $chan_data[1]);
      $GLOBALS[$chan_name]->msg("Bot resumed by {$message['nick']}.", true);
      $irc->privmsg('ChanServ', "OP {$chan_data[0]} $nick");
    }
    $part_cache = array();
  }
  else if ( in_array($message['nick'], $privileged_list) && $message['message'] == 'Shutdown' && $message['action'] == 'PRIVMSG' )
  {
    $irc->close("Remote bot shutdown ordered by {$message['nick']}", true);
    return 'BREAK';
  }
  else if ( in_array($message['nick'], $privileged_list) && preg_match('/^\!echo-enano /', $message['message']) )
  {
    global $enano;
    $enano->msg(preg_replace('/^\!echo-enano /', '', $message['message']), true);
  }
}

