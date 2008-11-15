<?php

$mysql_conn = false;

function mysql_reconnect()
{
  global $mysql_conn, $mysql_host, $mysql_user, $mysql_pass, $mysql_dbname;
  if ( $mysql_conn )
  {
    @mysql_close($mysql_conn);
    if ( defined('LIBIRC_DEBUG') )
    {
      echo "< > Reconnecting to MySQL\n";
    }
  }
  // connect to MySQL
  $mysql_conn = @mysql_connect($mysql_host, $mysql_user, $mysql_pass);
  if ( !$mysql_conn )
  {
    $m_e = mysql_error();
    echo "Error connecting to MySQL: $m_e\n";
    exit(1);
  }
  $q = @mysql_query("USE `$mysql_dbname`;", $mysql_conn);
  if ( !$q )
  {
    $m_e = mysql_error();
    echo "Error selecting database: $m_e\n";
    exit(1);
  }
}

function eb_mysql_query($sql, $conn = false)
{
  global $mysql_conn, $irc;
  $m_et = false;
  while ( true )
  {
    $q = mysql_query($sql, $mysql_conn);
    if ( !$q )
    {
      $m_e = mysql_error();
      if ( strpos($m_e, 'gone away') && !$m_et )
      {
        mysql_reconnect();
        continue;
      }
      $m_et = true;
      // alert everyone on the bot's alert list
      if ( is_object($irc) )
      {
        global $alert_list;
        foreach ( $alert_list as $nick )
        {
          $irc->privmsg($nick, "MySQL query error: $m_e");
        }
      }
      else
      {
        echo "\nQUERY ERROR: $m_e\nQuery: $sql\n";
        exit(1);
      }
      return false;
    }
    break;
  }
  return $q;
}

function db_escape($str)
{
  return mysql_real_escape_string($str);
}
