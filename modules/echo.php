<?php

eb_hook('event_channel_msg', 'echo_event_channel_msg($chan, $message);');
eb_hook('event_privmsg', 'echo_event_privmsg($message);');

function echo_event_channel_msg(&$chan, &$message)
{
  global $privileged_list;
  
  if ( preg_match('/^\!echo /', $message['message']) && in_array($message['nick'], $privileged_list) )
  {
    $chan->msg(eb_censor_words(preg_replace('/^\!echo /', '', $message['message'])), true);
  }
}

function echo_event_privmsg($message)
{
  global $privileged_list;
  
  if ( in_array($message['nick'], $privileged_list) && preg_match("/^\!echo-([^\007, \r\n\a\t]+) /", $message['message'], $match) )
  {
    global $libirc_channels;
    $channel_name =& $match[1];
    if ( isset($libirc_channels[$channel_name]) && is_object($libirc_channels[$channel_name]) )
    {
      $libirc_channels[$channel_name]->msg(eb_censor_words(preg_replace("/^\!echo-([^\007, \r\n\a\t]+) /", '', $message['message'])), true);
    }
  }
}
