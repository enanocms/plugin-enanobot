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
require('database.php');
require('permissions.php');

if ( !isset($permissions) )
{
  foreach ( $privileged_list as $user )
  {
    $permissions[$user] = array('admin');
  }
  if ( isset($alert_list) )
  {
    foreach ( $alert_list as $user )
    {
      $permissions[$user] = array('admin', 'alert');
    }
  }
}

$enanobot_version = '0.5-unstable';

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

mysql_reconnect();

eval(eb_fetch_hook('startup_early'));

$libirc_channels = array();

$irc = new Request_IRC($server);
$irc->connect($nick, $user, $name, $pass);
$irc->set_privmsg_handler('enanobot_privmsg_event');
$irc->set_timeout_handlers(false, 'enanobot_timeout_event');

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
  
  if ( strpos($message['message'], $nick) && !check_permissions($message['nick'], array('context' => 'channel', 'channel' => $chan->get_channel_name())) && $message['nick'] != $nick )
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
  if ( $message['message'] == 'Suspend' && $message['action'] == 'PRIVMSG' && check_permissions($message['nick'], array('context' => 'suspend'), false) )
  {
    foreach ( $irc->channels as $channel )
    {
      $part_cache[] = array($channel->get_channel_name(), $channel->get_handler());
      $channel->part("suspended by {$message['nick']}", true);
    }
  }
  else if ( $message['message'] == 'Resume' && $message['action'] == 'PRIVMSG' && check_permissions($message['nick'], array('context' => 'suspend'), false) )
  {
    global $nick;
    foreach ( $part_cache as $chan_data )
    {
      $chan_name = substr($chan_data[0], 1);
      $GLOBALS[$chan_name] = $irc->join($chan_data[0], $chan_data[1]);
      $GLOBALS[$chan_name]->msg("(resumed by {$message['nick']})", true);
      $irc->privmsg('ChanServ', "OP {$chan_data[0]} $nick");
    }
    $part_cache = array();
  }
  else if ( preg_match('/^Shutdown(?: (.+))?$/i', $message['message'], $match) && $message['action'] == 'PRIVMSG' && check_permissions($message['nick'], array('context' => 'shutdown'), false) )
  {
    $GLOBALS['_shutdown'] = true;
    $quitmessage = empty($match[1]) ? "Remote bot shutdown requested by {$message['nick']}" : $match[1];
    $irc->close($quitmessage, true);
    return 'BREAK';
  }
  else if ( preg_match('/^re(?:hash|load)?(?:config)?(?: |$)/', $message['message']) && check_permissions($message['nick'], array('context' => 'rehash'), false) )
  {
    $oldnick = $GLOBALS['nick'];
    require('config.php');
    $GLOBALS['privileged_list'] = $privileged_list;
    $GLOBALS['alert_list'] = $alert_list;
    $GLOBALS['channels'] = $channels;
    $GLOBALS['permissions'] = $permissions;
    if ( $nick != $oldnick )
    {
      $irc->change_nick($nick, $pass);
      $GLOBALS['nick'] = $nick;
      $GLOBALS['pass'] = $pass;
    }
    $in = array();
    foreach ( $irc->channels as $channel )
    {
      $channame = $channel->get_channel_name();
      if ( !in_array($channame, $channels) )
      {
        $channel->part("Leaving");
      }
      else
      {
        $in[] = $channame;
      }
    }
    unset($channel);
    foreach ( $channels as $channel )
    {
      if ( !in_array($channel, $in) )
      {
        $GLOBALS[ preg_replace('/^(#|&)/', '', $channel) ] = $irc->join($channel, 'enanobot_channel_event');
      }
    }
    $irc->privmsg($message['nick'], "Config has been reloaded.");
  }
  else if ( substr($message['message'], 0, 1) == "\x01" && substr($message['message'], -1) == "\x01" )
  {
    $msg = trim($message['message'], "\x01");
    list($ctcp) = explode(' ', $msg);
    $params = substr($msg, strlen($ctcp)+1);
    eval(eb_fetch_hook('event_ctcp'));
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

function enanobot_timeout_event($irc)
{
  // uh-oh.
  $irc->close('client ping timeout (restarting connection)');
  if ( defined('LIBIRC_DEBUG') )
  {
    $now = date('r');
    echo "!!! [$now] Connection timed out; waiting 10 seconds and reconnecting\n";
  }
  
  // re-init
  global $server, $nick, $user, $name, $pass, $channels, $libirc_channels;
  
  // wait until we can get into the server
  while ( true )
  {
    sleep(10);
    if ( defined('LIBIRC_DEBUG') )
    {
      $now = date('r');
      echo "... [$now] Attempting reconnect\n";
    }
    $conn = @fsockopen($server, 6667, $errno, $errstr, 5);
    if ( $conn )
    {
      if ( defined('LIBIRC_DEBUG') )
      {
        $now = date('r');
        echo "!!! [$now] Reconnection successful, ghosting old login (waiting 5 seconds to avoid throttling)\n";
      }
      fputs($conn, "QUIT :This bot needs better exception handling. But until then I'm going to need to make repeated TCP connection attempts when my ISP craps out. Sorry :-/\r\n");
      fclose($conn);
      sleep(5);
      break;
    }
    else
    {
      if ( defined('LIBIRC_DEBUG') )
      {
        $now = date('r');
        echo "!!! [$now] Still waiting for connection to come back up\n";
      }
    }
  }
  
  $libirc_channels = array();
  
  // we were able to get back in; ask NickServ to GHOST the old nick
  $irc->connect($nick, $user, $name, false);
  
  foreach ( $channels as $channel )
  {
    $libirc_channels[$channel] = $irc->join($channel, 'enanobot_channel_event');
    $channel_clean = preg_replace('/^[#&]/', '', $channel);
    $libirc_channels[$channel_clean] =& $libirc_channels[$channel];
    $irc->privmsg('ChanServ', "OP $channel $nick");
  }
}

if ( $_shutdown )
{
  exit(2);
}
