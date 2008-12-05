<?php

// auth if possible
if ( file_exists('./includes/common.php') )
{
  require('includes/common.php');
  if ( !$session->user_logged_in )
  {
    // error out
    $paths->main_page();
    die('Not authorized');
  }
  $db->close();
  // unload Enano, we don't need it anymore
  unset($db, $session, $paths, $template, $plugins);
}

function parse_wildcard($str)
{
  $append = isset($_POST['match_whole']) ? '' : '%';
  return $append . mysql_real_escape_string(strtr(str_replace(array('%', '_'), array('\%', '\_'), $str), '*?', '%_')) . $append;
}

function basenick($nick)
{
  if ( preg_match('/^`/', $nick) )
  {
    $nick = substr($nick, 1);
  }
  return preg_replace('/(`|\|)(.+?)$/', '', $nick);
}

function dbdie()
{
  die('MySQL query error: ' . mysql_error());
}

function tableize_mysql_result($result)
{
  $col_strings = array(
      'nick' => 'Nickname',
      'basenick' => 'Basenick',
      'ip' => 'IP',
      'hostname' => 'Hostname',
      'time' => 'Last join',
      'channel' => 'Channel'
    );
  if ( mysql_num_rows($result) < 1 )
  {
    echo '<p>No results.</p>';
    return true;
  }
  $row = @mysql_fetch_assoc($result);
  echo '<table border="1" cellpadding="3"><tr>';
  foreach ( $row as $col => $_ )
  {
    echo "<th>{$col_strings[$col]}</th>";
  }
  echo '</tr>';
  do
  {
    echo "<tr>";
    foreach ( $row as $col => $val )
    {
      if ( $col == 'nick' )
        echo "<td><a href=\"iplogs.php?query_user=" . urlencode($val) . "\">$val</a></td>";
      else if ( $col == 'ip' )
        echo "<td><a href=\"iplogs.php?query_ip=" . urlencode($val) . "\">$val</a></td>";
      else if ( $col == 'time' )
        echo "<td>" . date('r', intval($val)) . "</td>";
      else
        echo "<td>$val</td>";
    }
    echo "</tr>";
  }
  while ( $row = mysql_fetch_assoc($result) );
  echo '</table>';
  return true;
}

require('../../stats-fe.php');
require('../../timezone.php');

echo '<h2>' . $nick . ' IP logs</h2>';

if ( isset($_POST['submit']) )
{
  $query = 'SELECT nick, basenick, ip, hostname, channel, time FROM ip_log';
  $where = 'WHERE';
  if ( !empty($_POST['nick']) )
  {
    $query .= " $where ( nick LIKE '" . parse_wildcard($_POST['nick']) . "'";
    $query .= " OR basenick LIKE '" . parse_wildcard($_POST['nick']) . "' )";
    $where = 'OR';
  }
  if ( !empty($_POST['ip']) )
  {
    $query .= " $where ip LIKE '" . parse_wildcard($_POST['ip']) . "'";
    $where = 'OR';
  }
  if ( !empty($_POST['host']) )
  {
    $query .= " $where hostname LIKE '" . parse_wildcard($_POST['host']) . "'";
    $where = 'OR';
  }
  if ( !empty($_POST['channel']) && $_POST['channel'] != '#' )
  {
    $query .= " $where channel LIKE '" . parse_wildcard($_POST['channel']) . "'";
    $where = 'OR';
  }
  
  $query .= ';';
  
  if ( $result = eb_mysql_query($query) )
  {
    $num_results = mysql_num_rows($result);
    $str = ( $num_results == 1 ) ? "1 result" : "$num_results results";
    tableize_mysql_result($result);
  }
}

if ( isset($_GET['query_user']) )
{
  $nick =& $_GET['query_user'];
  echo '<h3>' . htmlspecialchars($nick) . '</h3>';
  echo '<p>Basenick: ' . htmlspecialchars(basenick($nick)) . '</p>';
  
  echo '<h4>IP addresses this user has been seen from</h4>';
  $nick = mysql_real_escape_string($nick);
  $basenick = mysql_real_escape_string(basenick($nick));
  $q = eb_mysql_query("SELECT DISTINCT ip, hostname FROM ip_log WHERE nick = '$nick' OR basenick = '$basenick';");
  if ( !$q )
    dbdie();
  tableize_mysql_result($q);
  
  echo '<h4>Channels this user has been seen in</h4>';
  $q = eb_mysql_query("SELECT DISTINCT nick, channel, time FROM ip_log WHERE nick = '$nick' OR basenick = '$basenick';");
  if ( !$q )
    dbdie();
  tableize_mysql_result($q);
}

if ( isset($_GET['query_ip']) )
{
  $ip =& $_GET['query_ip'];
  echo '<h3>' . htmlspecialchars($ip) . '</h3>';
  $ip = mysql_real_escape_string($ip);
  
  echo '<h4>Users seen from this IP address</h4>';
  $q = eb_mysql_query("SELECT DISTINCT nick, channel, time FROM ip_log WHERE ip = '$ip';");
  if ( !$q )
    dbdie();
  tableize_mysql_result($q);
}

// FORM
?>
<form action="iplogs.php" method="post">
  <h3>Search database</h3>
  <p><small>Enter data in one or more fields. You can use an asterisk (*) anywhere to match multiple characters or a question mark (?) to match a single character.</small></p>
  <table border="0">
    <tr>
      <td>Nickname</td>
      <td><input type="text" name="nick" size="30" /></td>
    </tr>
    <tr>
      <td>IP address</td>
      <td><input type="text" name="ip" size="30" /></td>
    </tr>
    <tr>
      <td>Hostname</td>
      <td><input type="text" name="host" size="30" /></td>
    </tr>
    <tr>
      <td>Channel</td>
      <td><input type="host" name="channel" size="30" value="#" /></td>
    </tr>
    <tr>
      <td colspan="2">
        <label><input type="checkbox" name="match_whole" /> Exact matches</label>
      </td>
    </tr>
    <tr>
      <td colspan="2" style="text-align: center;">
        <input type="submit" name="submit" />
      </td>
    </tr>
  </table>
</form>
