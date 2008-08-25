<?php

eb_hook('event_raw_message', 'greeting_event($chan, $message);');

function greeting_event(&$chan, &$message)
{
  static $part_list = array();
  
  switch($message['action'])
  {
    case 'JOIN':
      $wb = false;
      if ( isset($part_list[$message['nick']]) )
      {
        if ( $part_list[$message['nick']] + 1800 >= time() )
        {
          $chan->msg("Welcome back.");
          $wb = true;
        }
      }
      if ( !$wb )
      {
        $append = '';
        eval(eb_fetch_hook('event_greeting'));
        $chan->msg(eb_censor_words("Hi, {$message['nick']}.$append"));
      }
      break;
    case 'PART':
      $part_list[$message['nick']] = time();
      break;
  }
}
