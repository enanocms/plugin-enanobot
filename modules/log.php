<?php

eb_hook('event_raw_message', 'enanobot_log_message($chan, $message);');

function enanobot_log_message(&$chan, &$message)
{
  global $nick;
  
  // Log the message
  $chan_db = mysql_real_escape_string($chan->get_channel_name());
  $nick_db = mysql_real_escape_string($message['nick']);
  $line_db = mysql_real_escape_string($message['message']);
  $day     = date('Y-m-d');
  $time    = time();
  $m_et = false;
  $sql = false;
  switch($message['action'])
  {
    case 'PRIVMSG':
      if ( substr($line_db, 0, 5) != '[off]' )
      {
        $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                  ( '$chan_db', '$day', '$nick_db', '$time', '$line_db' );";
      }
      break;
    case 'JOIN':
      $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                ( '$chan_db', '$day', '', '$time', '$nick_db has joined $chan_db' );";
      break;
    case 'PART':
      $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                ( '$chan_db', '$day', '', '$time', '$nick_db has left $chan_db' );";
      break;
    case 'MODE':
      list($mode, $target_nick) = explode(' ', $line_db);
      if ( $message['nick'] != 'ChanServ' && $target_nick != $nick )
      {
        $sql = "INSERT INTO irclog(channel, day, nick, timestamp, line) VALUES
                  ( '$chan_db', '$day', '', '$time', '$nick_db set mode $mode on $target_nick' );";
      }
      break;
  }
  if ( $sql )
  {
    eb_mysql_query($sql);
  }
}

