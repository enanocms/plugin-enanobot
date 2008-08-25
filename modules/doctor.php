<?php
require('eliza.php');

eb_hook('event_channel_msg', 'doctor_listen($chan, $message);');
eb_hook('snippet_dynamic', 'if ( $snippet === "doctor" ) return doctor_go($chan, $message, $snippet);');
eb_hook('event_greeting', 'doctor_greet($append);');

function doctor_go(&$chan, &$message, &$snippet)
{
  global $doctor;
  
  if ( $snippet == 'doctor' )
  {
    if ( isset($doctor[$message['nick']]) )
    {
      unset($doctor[$message['nick']]);
      $chan->msg(eb_censor_words("{$message['nick']}, thank you for visiting the psychotherapist. Come again soon!"), true);
    }
    else
    {
      $doctor[$message['nick']] = new Psychotherapist();
      $chan->msg(eb_censor_words("{$message['nick']}, I am the psychotherapist. Please explain your problems to me. When you are finished talking with me, type !doctor again."), true);
    }
    return true;
  }
}

function doctor_listen(&$chan, &$message)
{
  global $doctor;
  
  if ( isset($doctor[$message['nick']]) && $message['message'] != '!doctor' )
  {
    $chan->msg(eb_censor_words($doctor[$message['nick']]->listen($message['message'])));
  }
}

function doctor_greet(&$append)
{
  $append .= ' Type !doctor for the psychotherapist.';
}
