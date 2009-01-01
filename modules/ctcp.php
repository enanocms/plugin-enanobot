<?php

eb_hook('event_ctcp', 'handle_ctcp($ctcp, $params, $message);');

function handle_ctcp($ctcp, $params, $message)
{
  global $irc;
  global $alert_list;
  switch($ctcp)
  {
    case 'PING':
      $irc->notice($message['nick'], "\x01PING $params\x01");
      break;
    case 'VERSION':
      global $nick, $enanobot_version;
      $irc->notice($message['nick'], "\x01VERSION $nick-$enanobot_version on PHP/" . PHP_VERSION . " (" . PHP_OS . ")\x01");
      break;
    default:
      eval(eb_fetch_hook('event_custom_ctcp'));
      break;
  }
  $now = date('r');
  foreach ( $alert_list as $alertme )
  {
    $irc->privmsg($alertme, "Received CTCP \"$ctcp\" from {$message['nick']}, " . $now);
  }
}
