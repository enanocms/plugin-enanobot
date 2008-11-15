<?php

##
## Deletion requests
##

eb_hook('snippet_dynamic', 'if ( $snippet === "deluser" ) return stats_handle_delete_request($chan, $message);');

function stats_handle_delete_request($chan, $message)
{
  global $privileged_list, $irc, $stats_data;
  
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
  stats_del_user($chan->get_channel_name(), $targetuser);
  
  global $nick;
  $greeting = ( $targetuser == $message['nick'] ) ? "All of your statistics data" : "All of {$targetuser}'s statistic data";
  $irc->privmsg($message['nick'], "$greeting has been removed from the database for all channels. The changes will show up in the next commit to disk, which is usually no more than once every two minutes.");
  $irc->privmsg($message['nick'], "Want your stats to be anonymized in the future? Type /msg $nick anonymize to make me keep all your stats anonymous in the future. This only applies to your current nick though - for example if you change your nick to \"{$message['nick']}|sleep\" or similar your information will not be anonymous.");
  $irc->privmsg($message['nick'], "You can't clear your logs if you're anonymous. Type /msg $nick denonymize to remove yourself from the anonymization list. Anonymized logs can't be converted back to their original nicks.");
  
  return true;
}

##
## Anonymization
##

eb_hook('event_privmsg', 'stats_handle_privmsg($message);');

function stats_handle_privmsg($message)
{
  global $irc, $stats_data, $nick;
  static $poll_list = array();
  
  $message['message'] = strtolower($message['message']);
  
  if ( trim($message['message']) === 'anonymize' )
  {
    if ( stats_anonymize_user_now($message['nick']) )
    {
      $irc->privmsg($message['nick'], "Anonymization complete. Any further statistics recorded about you will be anonymous.");
      $irc->privmsg($message['nick'], "Do you want to also anonymize any past statistics about you? (type \"yes\" or \"no\")");
      $poll_list[$message['nick']] = true;
    }
    else
    {
      $irc->privmsg($message['nick'], "You're already marked as anonymous.");
    }
  }
  else if ( trim($message['message']) === 'denonymize' )
  {
    if ( stats_denonymize_user($message['nick']) )
    {
      $irc->privmsg($message['nick'], "Denonymization complete. Any further statistics recorded about you will bear your nick. Remember that you can always change this with /msg $nick anonymize.");
    }
    else
    {
      $irc->privmsg($message['nick'], "You're not marked as anonymous.");
    }
  }
  else if ( trim($message['message']) === 'yes' && isset($poll_list[$message['nick']]) )
  {
    // anonymize logs for this user
    stats_anonymize_user_past($message['nick']);
    $irc->privmsg($message['nick'], "Anonymization complete. All past statistics on your nick are now anonymous.");
    
    unset($poll_list[$message['nick']]);
  }
  else if ( isset($poll_list[$message['nick']]) )
  {
    unset($poll_list[$message['nick']]);
  }
}

