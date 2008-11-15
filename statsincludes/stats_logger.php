<?php

eb_hook('event_channel_msg', 'stats_event_privmsg($chan, $message);');

function stats_event_privmsg($chan, $message)
{
  $channel = $chan->get_channel_name();
  stats_log_message($channel, $message['nick'], time());
}

