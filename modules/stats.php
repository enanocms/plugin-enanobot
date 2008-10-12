<?php

// most of the code in here goes towards keeping track of the list of members currently in the various channels we're in.

$stats_memberlist = array();
$stats_prefixes = array(
  'o' => '@',
  'v' => '+'
);
$stats_data = array('anonymous' => array(), 'messages' => array());
$stats_day = gmdate('Ymd');
@include("./stats/stats-data-$stats_day.php");
unset($stats_data['members']);
$stats_data['members'] =& $stats_memberlist;

eb_hook('event_self_join', 'stats_init_channel($this);');
eb_hook('event_raw_message', 'stats_process_message($chan, $message);');
eb_hook('snippet_dynamic', 'if ( $snippet === "memberlist" ) return stats_list_members($chan, $message); if ( $snippet === "deluser" ) return stats_del_user($chan, $message);');
eb_hook('event_other', 'stats_handle_other_event($message);');
eb_hook('event_privmsg', 'stats_handle_privmsg($message);');

function stats_init_channel(&$chan)
{
  global $stats_memberlist, $stats_prefixes, $stats_data;
  
  $channel_name = $chan->get_channel_name();
  $stats_memberlist[$channel_name] = array();
  $prefixes_regexp = '/^([' . preg_quote(implode('', $stats_prefixes)) . '])+/';
  $prefixes_flipped = array_flip($stats_prefixes);
  $prefixes_regexp_notlist = '/[^' . preg_quote(implode('', $prefixes_flipped)) . ']/';
  
  if ( !isset($stats_data['messages'][$channel_name]) )
  {
    $stats_data['messages'][$channel_name] = array();
  }
  
  // read list of members from channel
  @stream_set_timeout($chan->parent->sock, 3);
  while ( $msg = $chan->parent->get() )
  {
    if ( $ml = strstr($msg, ' 353 ') )
    {
      $memberlist = trim(substr(strstr($ml, ':'), 1));
      $stats_memberlist[$channel_name] = explode(' ', $memberlist);
      $stats_memberlist[$channel_name] = array_flip($stats_memberlist[$channel_name]);
      foreach ( $stats_memberlist[$channel_name] as $nick => $_ )
      {
        $stats_memberlist[$channel_name][$nick] = '';
        while ( preg_match($prefixes_regexp, $nick) )
        {
          $prefix = substr($nick, 0, 1);
          $add = preg_replace($prefixes_regexp_notlist, '', strval($stats_memberlist[$channel_name][$nick]));
          unset($stats_memberlist[$channel_name][$nick]);
          $nick = substr($nick, 1);
          $stats_memberlist[$channel_name][$nick] = $prefixes_flipped[$prefix] . $add;
        }
      }
      break;
    }
  }
}

function stats_process_message(&$chan, $message)
{
  global $stats_memberlist, $stats_data;
  $channel_name = $chan->get_channel_name();
  if ( !isset($stats_memberlist[$channel_name]) )
  {
    return false;
  }
  
  $ml =& $stats_memberlist[$channel_name];
  
  // we need to change statistics accordingly depending on the event
  if ( $message['action'] == 'JOIN' )
  {
    // member joined - init their flags and up the member count by one
    $ml[$message['nick']] = '';
  }
  else if ( $message['action'] == 'PART' )
  {
    // member left - clear flags and decrement the total member count
    unset($ml[$message['nick']]);
    $ml = array_values($ml);
  }
  else if ( $message['action'] == 'MODE' )
  {
    // update member list (not sure why this would be useful, but export it anyway - display scripts might find it useful)
    list($mode, $target) = explode(' ', $message['message']);
    $action = substr($mode, 0, 1);
    
    global $stats_prefixes;
    $ml[$target] = str_replace(substr($mode, 1), '', $ml[$target]);
    if ( $action == '+' )
    {
      $ml[$target] .= substr($mode, 1);
    }
  }
  else if ( $message['action'] == 'PRIVMSG' )
  {
    // private message into $channel_name - mark the user active and log the message time
    if ( isset($stats_data['anonymous'][$message['nick']]) )
      $message['nick'] = 'Anonymous';
    
    $messages =& $stats_data['messages'][$channel_name];
    
    $messages[] = array(
        'time' => time(),
        'nick' => $message['nick']
      );
  }
  
  stats_cron();
}

function stats_list_members(&$chan, &$message)
{
  global $stats_memberlist;
  $channel_name = $chan->get_channel_name();
  if ( !isset($stats_memberlist[$channel_name]) )
  {
    return false;
  }
  
  $ml =& $stats_memberlist[$channel_name];
  
  $chan->parent->privmsg($message['nick'], "memberlist:\n" . str_replace("\n", ' ', print_r($ml, true)));
  
  return true;
}

function stats_del_user(&$chan, &$message)
{
  global $stats_memberlist, $privileged_list, $irc, $stats_data;
  
  // remove a user from the DB
  $targetuser = trim(substr(strstr($message['message'], '|'), 1));
  if ( empty($targetuser) )
    $targetuser = $message['nick'];
  
  if ( $targetuser != $message['nick'] && !in_array($message['nick'], $privileged_list) )
  {
    $irc->privmsg($message['nick'], "Sorry, you need to be a moderator to delete statistics for users other than yourself.");
    return true;
  }
  
  // we should be good - delete the user
  foreach ( $stats_data['messages'] as $channel => &$messages )
  {
    foreach ( $messages as $i => &$currentmessage )
    {
      if ( $currentmessage['nick'] == $targetuser )
      {
        unset($messages[$i]);
      }
    }
    $messages = array_values($messages);
  }
  unset($users, $currentmessage, $messages);
  
  global $nick;
  $greeting = ( $targetuser == $message['nick'] ) ? "All of your statistics data" : "All of {$targetuser}'s statistic data";
  $irc->privmsg($message['nick'], "$greeting has been removed from the database for all channels. The changes will show up in the next commit to disk, which is usually no more than once every two minutes.");
  $irc->privmsg($message['nick'], "Want your stats to be anonymized in the future? Type /msg $nick anonymize to make me keep all your stats anonymous in the future. This only applies to your current nick though - for example if you change your nick to \"{$message['nick']}|sleep\" or similar your information will not be anonymous.");
  $irc->privmsg($message['nick'], "You can't clear your logs if you're anonymous. Type /msg $nick denonymize to remove yourself from the anonymization list. Anonymized logs can't be converted back to their original nicks.");
  
  return true;
}

function stats_handle_privmsg(&$message)
{
  global $irc, $stats_data, $nick;
  static $poll_list = array();
  
  $message['message'] = strtolower($message['message']);
  
  if ( trim($message['message']) === 'anonymize' )
  {
    $stats_data['anonymous'][$message['nick']] = true;
    $poll_list[$message['nick']] = true;
    $irc->privmsg($message['nick'], "Anonymization complete. Any further statistics recorded about you will be anonymous.");
    $irc->privmsg($message['nick'], "Do you want to also anonymize any past statistics about you? (type \"yes\" or \"no\")");
  }
  else if ( trim($message['message']) === 'denonymize' )
  {
    $stats_data['anonymous'][$message['nick']] = false;
    unset($stats_data['anonymous'][$message['nick']]);
    $irc->privmsg($message['nick'], "Denonymization complete. Any further statistics recorded about you will bear your nick. Remember that you can always change this with /msg $nick anonymize.");
  }
  else if ( trim($message['message']) === 'yes' && isset($poll_list[$message['nick']]) )
  {
    // anonymize logs for this user
    // we should be good - delete the user
    $targetuser = $message['nick'];
    
    foreach ( $stats_data['messages'] as $channel => &$messages )
    {
      foreach ( $messages as $i => &$currentmessage )
      {
        if ( $currentmessage['nick'] == $targetuser )
        {
          $currentmessage['nick'] = 'Anonymous';
        }
      }
      $messages = array_values($messages);
    }
    unset($users, $currentmessage, $messages);
    $irc->privmsg($message['nick'], "Anonymization complete. All past statistics on your nick are now anonymous.");
    
    unset($poll_list[$message['nick']]);
  }
  stats_cron();
}

function stats_handle_other_event(&$message)
{
  global $stats_memberlist;
  
  if ( $message['action'] == 'NICK' )
  {
    // we have a nick change; go through all channels and replace the old nick with the new
    foreach ( $stats_memberlist as &$ml )
    {
      if ( isset($ml[$message['nick']]) )
      {
        $ml[$message['message']] = $ml[$message['nick']];
        unset($ml[$message['nick']]);
      }
    }
  }
  stats_cron();
}

function stats_cron()
{
  static $commit_time = 0;
  $now = time();
  // commit to disk every 1 minute
  if ( $commit_time + 60 < $now )
  {
    $commit_time = $now;
    stats_commit();
  }
}

function stats_commit()
{
  global $stats_data, $stats_day;
  
  ob_start();
  var_export($stats_data);
  $stats_data_exported = ob_get_contents();
  ob_end_clean();
  
  $fp = @fopen("./stats/stats-data-$stats_day.php", 'w');
  if ( !$fp )
    return false;
  fwrite($fp, "<?php\n\$stats_data = $stats_data_exported;\n");
  fclose($fp);
  
  if ( $stats_day != gmdate('Ymd') )
  {
    // it's a new day! flush all our logs
    foreach ( $stats_data['messages'] as &$data )
    {
      $data = array();
    }
  }
  
  $stats_day = gmdate('Ymd');
}

