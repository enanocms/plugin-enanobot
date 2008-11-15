<?php

eb_hook('event_raw_message', 'iplogger_log_join($message);');

function iplogger_log_join($message)
{
  if ( $message['action'] == 'JOIN' )
  {
    $nick = mysql_real_escape_string($message['nick']);
    $basenick = basenick($nick);
    $host = mysql_real_escape_string($message['host']);
    $ip = ( $_ = @resolve_ip($message['host'], $message['user']) ) ? mysql_real_escape_string($_) : '0.0.0.0';
    $channel = mysql_real_escape_string($message['message']);
    $time = time();
    $query = "DELETE FROM ip_log WHERE
                nick = '$nick' AND
                basenick = '$basenick' AND
                ip = '$ip' AND
                hostname = '$host' AND
                channel = '$channel';";
    eb_mysql_query($query);
    
    $query = "INSERT INTO ip_log ( nick, basenick, ip, hostname, channel, time ) VALUES(
                '$nick',
                '$basenick',
                '$ip',
                '$host',
                '$channel',
                $time
              );";
    eb_mysql_query($query);
  }
  else if ( $message['ACTION'] == 'NICK' )
  {
    // this is for cross-referencing purposes.
    $nick = mysql_real_escape_string($message['message']);
    $basenick = basenick($message['nick']);
    $host = mysql_real_escape_string($message['host']);
    $ip = ( $_ = @resolve_ip($message['host'], $message['user']) ) ? mysql_real_escape_string($_) : '0.0.0.0';
    $channel = '__nickchange';
    $time = time();
    $query = "DELETE FROM ip_log WHERE
                nick = '$nick' AND
                basenick = '$basenick' AND
                ip = '$ip' AND
                hostname = '$host' AND
                channel = '$channel';";
    eb_mysql_query($query);
    
    $query = "INSERT INTO ip_log ( nick, basenick, ip, hostname, channel, time ) VALUES(
                '$nick',
                '$basenick',
                '$ip',
                '$host',
                '$channel',
                $time
              );";
    eb_mysql_query($query);
  }
}

/**
 * Attempt to eliminate mini-statuses and such from nicknames.
 * @example
 <code>
 $basenick = basenick('enanobot|debug');
 // $basenick = 'enanobot'
 </code>
 * @param string Nickname
 * @return string
 */

function basenick($nick)
{
  if ( preg_match('/^`/', $nick) )
  {
    $nick = substr($nick, 1);
  }
  return preg_replace('/(`|\|)(.+?)$/', '', $nick);
}

/**
 * Resolve an IP address. First goes by checking if it's a mibbit or CGI-IRC IP/user, then performs lookups accordingly.
 * @param string Hostname
 * @param string Username
 * @return string IP address
 */

function resolve_ip($host, $user)
{
  if ( $host == 'webchat.mibbit.com' )
  {
    return hex2ipv4($user);
  }
  return gethostbyname($host);
}

function hex2ipv4($ip)
{
  $ip = preg_replace('/^0x/', '', $ip);
  $ip = str_split($ip, 2);
  foreach ( $ip as &$byte )
  {
    $byte = hexdec($byte);
  }
  return implode('.', $ip);
}
