<?php

eb_hook('event_channel_msg', 'echo_event_channel_msg($chan, $message);');
eb_hook('event_privmsg', 'echo_event_privmsg($message);');

function echo_event_channel_msg(&$chan, &$message)
{
  global $privileged_list;
  
  if ( preg_match('/^\!echo /', $message['message']) && check_permissions($message['nick'], array('context' => 'echo')) )
  {
    $chan->msg(eb_censor_words(preg_replace('/^\!echo /', '', $message['message'])), true);
  }
}

function echo_event_privmsg($message)
{
  global $privileged_list;
  
  if ( preg_match("/^(?:\!echo-|\/msg )([#&][^\007, \r\n\a\t]+) (.+)/", $message['message'], $match) && check_permissions($message['nick'], array('context' => 'echo')) )
  {
    global $libirc_channels;
    $channel_name =& $match[1];
    if ( isset($libirc_channels[$channel_name]) && is_object($libirc_channels[$channel_name]) )
    {
      $libirc_channels[$channel_name]->msg(eb_censor_words($match[2]), true);
    }
  }
  else if ( preg_match("/^(?:\!pm|\/msg) ([^\007, \r\n\a\t]+) (.+)/", $message['message'], $match) && check_permissions($message['nick'], array('context' => 'pm'), false) )
  {
    global $irc;
    $irc->privmsg($match[1], eb_censor_words($match[2]));
  }
}
