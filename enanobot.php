<?php
/**
 * EnanoBot - the Enano CMS IRC logging and help automation bot
 * GPL and no warranty, see the LICENSE file for more info
 */

// parse command line
if ( isset($argv[1]) )
{
  $arg =& $argv[1];
  if ( $arg == '--daemon' || $arg == '-d' )
  {
    // attempt to fork...
    if ( function_exists('pcntl_fork') )
    {
      $pid = pcntl_fork();
      if ( $pid == -1 )
      {
        echo "Forking process failed.\n";
        exit(1);
      }
      else if ( $pid )
      {
        echo "EnanoBot daemon started, pid $pid\n";
        exit(0);
      }
      else
      {
        // do nothing, just continue.
      }
    }
    else
    {
      echo "No pcntl support in PHP, continuing in foreground\n";
    }
  }
  else if ( $arg == '-v' || $arg == '--verbose' )
  {
    define('LIBIRC_DEBUG', '');
  }
  else
  {
    echo <<<EOF
Usage: {$argv[0]}
Options:
  -d, --daemon     Run in background (requires pcntl support)
  -v, --verbose    Log communication to stdout (ignored if -d specified)
  -h, --help       This help message

EOF;
    exit(1);
  }
}

$censored_words = array('cock', 'fuck', 'cuck', 'funt', 'cunt', 'bitch');
$_shutdown = false;

function eb_censor_words($text)
{
  // return $text;
  
  global $censored_words;
  foreach ( $censored_words as $word )
  {
    $replacement = substr($word, 0, 1) . preg_replace('/./', '*', substr($word, 1));
    while ( stristr($text, $word) )
    {
      $text = preg_replace("/$word/i", $replacement, $text);
    }
  }
  return $text;
}

require('libirc.php');
require('hooks.php');
require('config.php');

@ini_set('display_errors', 'on');
error_reporting(E_ALL);

// load modules
foreach ( $modules as $module )
{
  $modulefile = "modules/$module.php";
  if ( file_exists($modulefile) )
  {
    require($modulefile);
  }
}

$mysql_conn = false;
$doctor = array();

function mysql_reconnect()
{
  global $mysql_conn, $mysql_host, $mysql_user, $mysql_pass, $mysql_dbname;
  if ( $mysql_conn )
  {
    @mysql_close($mysql_conn);
    if ( defined('LIBIRC_DEBUG') )
    {
      echo "< > Reconnecting to MySQL\n";
    }
  }
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

$libirc_channels = array();

$irc = new Request_IRC($server);
$irc->connect($nick, $user, $name, $pass);
$irc->set_privmsg_handler('enanobot_privmsg_event');

foreach ( $channels as $channel )
{
  $libirc_channels[$channel] = $irc->join($channel, 'enanobot_channel_event');
  $channel_clean = preg_replace('/^[#&]/', '', $channel);
  $libirc_channels[$channel_clean] =& $libirc_channels[$channel];
  $irc->privmsg('ChanServ', "OP $channel $nick");
}

$irc->event_loop();
$irc->close();
mysql_close($mysql_conn);

function enanobot_channel_event($sockdata, $chan)
{
  global $irc, $nick, $mysql_conn, $privileged_list;
  $sockdata = trim($sockdata);
  $message = Request_IRC::parse_message($sockdata);
  $channelname = $chan->get_channel_name();
  
  eval(eb_fetch_hook('event_raw_message'));
  
  switch ( $message['action'] )
  {
    case 'JOIN':
      eval(eb_fetch_hook('event_join'));
      break;
    case 'PART':
      eval(eb_fetch_hook('event_part'));
      break;
    case 'PRIVMSG':
      enanobot_process_channel_message($sockdata, $chan, $message);
      break;
  }
}

function enanobot_process_channel_message($sockdata, $chan, $message)
{
  global $irc, $nick, $mysql_conn, $privileged_list;
  
  if ( strpos($message['message'], $nick) && !in_array($message['nick'], $privileged_list) && $message['nick'] != $nick )
  {
    $target_nick =& $message['nick'];
    // $chan->msg("{$target_nick}, I'm only a bot. :-) You should probably rely on the advice of humans if you need further assistance.", true);
  }
  else
  {
    eval(eb_fetch_hook('event_channel_msg'));
  }
}

function enanobot_privmsg_event($message)
{
  global $privileged_list, $irc, $nick;
  static $part_cache = array();
  if ( in_array($message['nick'], $privileged_list) && $message['message'] == 'Suspend' && $message['action'] == 'PRIVMSG' )
  {
    foreach ( $irc->channels as $channel )
    {
      $part_cache[] = array($channel->get_channel_name(), $channel->get_handler());
      $channel->msg("I've received a request from {$message['nick']} to stop responding to requests, messages, and activities. Don't forget to unsuspend me with /msg $nick Resume when finished.", true);
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
    $GLOBALS['_shutdown'] = true;
    $irc->close("Remote bot shutdown ordered by {$message['nick']}", true);
    return 'BREAK';
  }
  else if ( $message['action'] == 'PRIVMSG' )
  {
    eval(eb_fetch_hook('event_privmsg'));
  }
  else
  {
    eval(eb_fetch_hook('event_other'));
  }
}

if ( $_shutdown )
{
  exit(2);
}
