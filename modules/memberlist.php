<?php

// most of the code in here goes towards keeping track of the list of members currently in the various channels we're in.

$eb_memberlist = array();
$userflags = array(
  'o' => '@',
  'v' => '+'
);

eb_hook('event_self_join', 'mlist_init_channel($this);');
eb_hook('event_raw_message', 'mlist_process_message($chan, $message);');
eb_hook('snippet_dynamic', 'if ( $snippet === "memberlist" ) return mlist_list_members($chan, $message);');
eb_hook('event_other', 'mlist_handle_other_event($message);');

function mlist_init_channel(&$chan)
{
  global $eb_memberlist, $userflags;
  
  $channel_name = $chan->get_channel_name();
  $eb_memberlist[$channel_name] = array();
  $prefixes_regexp = '/^([' . preg_quote(implode('', $userflags)) . '])+/';
  $prefixes_flipped = array_flip($userflags);
  $prefixes_regexp_notlist = '/[^' . preg_quote(implode('', $prefixes_flipped)) . ']/';
  
  // read list of members from channel
  @stream_set_timeout($chan->parent->sock, 3);
  while ( $msg = $chan->parent->get() )
  {
    if ( $ml = strstr($msg, ' 353 ') )
    {
      $memberlist = trim(substr(strstr($ml, ':'), 1));
      $eb_memberlist[$channel_name] = explode(' ', $memberlist);
      $eb_memberlist[$channel_name] = array_flip($eb_memberlist[$channel_name]);
      foreach ( $eb_memberlist[$channel_name] as $nick => $_ )
      {
        $eb_memberlist[$channel_name][$nick] = '';
        while ( preg_match($prefixes_regexp, $nick) )
        {
          $prefix = substr($nick, 0, 1);
          $add = preg_replace($prefixes_regexp_notlist, '', strval($eb_memberlist[$channel_name][$nick]));
          unset($eb_memberlist[$channel_name][$nick]);
          $nick = substr($nick, 1);
          $eb_memberlist[$channel_name][$nick] = $prefixes_flipped[$prefix] . $add;
        }
      }
      break;
    }
  }
}

function mlist_process_message(&$chan, $message)
{
  global $eb_memberlist;
  $channel_name = $chan->get_channel_name();
  if ( !isset($eb_memberlist[$channel_name]) )
  {
    return false;
  }
  
  $ml =& $eb_memberlist[$channel_name];
  
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
  }
  else if ( $message['action'] == 'MODE' )
  {
    // update member list (not sure why this would be useful, but export it anyway - display scripts might find it useful)
    list($mode, $target) = explode(' ', $message['message']);
    $action = substr($mode, 0, 1);
    
    global $userflags;
    $ml[$target] = str_replace(substr($mode, 1), '', $ml[$target]);
    if ( $action == '+' )
    {
      $ml[$target] .= substr($mode, 1);
    }
  }
}

function mlist_list_members(&$chan, &$message)
{
  global $eb_memberlist;
  $channel_name = $chan->get_channel_name();
  if ( !isset($eb_memberlist[$channel_name]) )
  {
    return false;
  }
  
  $ml =& $eb_memberlist[$channel_name];
  
  $mlt = implode("\n", str_split(str_replace("\n", ' ', print_r($ml, true)), 400));
  $chan->parent->privmsg($message['nick'], "memberlist:\n" . $mlt);
  
  return true;
}

function mlist_handle_other_event(&$message)
{
  global $eb_memberlist;
  
  if ( $message['action'] == 'NICK' )
  {
    // we have a nick change; go through all channels and replace the old nick with the new
    foreach ( $eb_memberlist as &$ml )
    {
      if ( isset($ml[$message['nick']]) )
      {
        $ml[$message['message']] = $ml[$message['nick']];
        unset($ml[$message['nick']]);
      }
    }
  }
}

