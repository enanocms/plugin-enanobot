<?php

eb_hook('event_join', 'autoop_event($chan, $message);');

function autoop_event(&$chan, &$message)
{
  global $privileged_list;
  
  $channelname = $chan->get_channel_name();
  
  // if a known op joins the channel, send mode +o
  if ( in_array($message['nick'], $privileged_list) )
  {
    $chan->parent->put("MODE $channelname +o {$message['nick']}\r\n");
  }
}
