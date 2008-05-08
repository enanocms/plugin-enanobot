<?php

/**
 * EnanoBot - copyright (C) 2008 Dan Fuhry
 * All rights reserved.
 */

/*****************************************************************
 * YOU NEED TO SET THE PATH TO THE REST OF THE EnanoBot FILES HERE.
 * Include a trailing slash.
 * This script MUST be placed in an Enano installation directory.
 *****************************************************************/

define('ENANOBOT_ROOT', './');

// We're authed.
// Load config
require(ENANOBOT_ROOT . 'config.php');

// check config
if ( empty($mysql_host) || empty($mysql_user) || empty($mysql_dbname) )
{
  die("Bad config file - have a look at config-sample.php.\n");
}

$title_append = '';
if ( isset($_GET['channel']) )
  $title_append .= ' - #' . $_GET['channel'];

$title = "Enano IRC logs$title_append";
require('includes/common.php');

// unset($template);
// $template = new template();
// $template->load_theme('oxygen', 'bleu');

$template->header();

$q = $db->sql_query('USE `' . $mysql_dbname . '`;');
if ( !$q )
  $db->_die();

$days_in_month = array(
    1 => 31,
    2 => ( intval(date('Y')) % 4 == 0 ? 29 : 28 ),
    3 => 31,
    4 => 30,
    5 => 31,
    6 => 30,
    7 => 31,
    8 => 31,
    9 => 30,
    10 => 31,
    11 => 30,
    12 => 31
  );

function irc_make_calendar($year = 0, $month = 0, $days_to_link = array())
{
  global $session;
  global $days_in_month, $channel;
  
  if ( $year < 1970 || $month < 1 || $month > 12 )
  {
    $year = intval(date('Y'));
    $month = intval(date('n'));
  }
  
  $month = intval($month);
  $year = intval($year);
  
  $this_month = mktime(0, 0, 1, $month, 1, $year);
  
  $next_month = mktime(0, 0, 1, ( $month + 1 ), 1, $year);
  $next_year = mktime(0, 0, 1, $month, 1, ( $year + 1 ) );
  $prev_month = mktime(0, 0, 1, ( $month - 1 ), 1, $year);
  $prev_year = mktime(0, 0, 1, $month, 1, ( $year - 1 ) );
  
  $a_next_month = '<a href="' . htmlspecialchars($session->append_sid(scriptPath . '/irclogs.php?year=' . date('Y', $next_month) . '&month=' . date('n', $next_month) . '&channel=' . $channel)) . '">&gt;</a>';
  $a_next_year = '<a href="' . htmlspecialchars($session->append_sid(scriptPath . '/irclogs.php?year=' . date('Y', $next_year) . '&month=' . $month . '&channel=' . $channel)) . '">&gt;&gt;</a>';
  $a_prev_month = ( $year == 1970 && $month == 1 ) ? '&lt;' : '<a href="' . htmlspecialchars($session->append_sid(scriptPath . '/irclogs.php?year=' . date('Y', $prev_month) . '&month=' . date('n', $prev_month) . '&channel=' . $channel)) . '">&lt;</a>';
  $a_prev_year  = ( $year == 1970 ) ? '&lt;&lt;' : '<a href="' . htmlspecialchars($session->append_sid(scriptPath . '/irclogs.php?year=' . date('Y', $prev_year) . '&month=' . $month . '&channel=' . $channel)) . '">&lt;&lt;</a>';
  
  $dow = intval(date('w', $this_month));
  
  $return = '';
  
  $return .= '<div class="tblholder" style="display: table; text-align: center;">
          <table border="0" cellspacing="1" cellpadding="6">
            <tr>
              <th colspan="7">' . "$a_prev_year $a_prev_month " . date('F', $this_month) . ' ' . $year . " $a_next_month $a_next_year" . '</th>
            </tr>';
  
  $return .= '<tr>';
  $class = 'row1';
  for ( $i = 0; $i < $dow; $i++ )
  {
    $class = ( $class == 'row1' ) ? 'row3' : 'row1';
    $return .= '<td class="' . $class . '"></td>';
  }
  
  if ( $month == 2 )
  {
    $days_in_this_month = ( $year % 4 == 0 ) ? 29 : 28;
  }
  else
  {
    $days_in_this_month = $days_in_month[$month];
  }
  
  for ( $i = 1; $i <= $days_in_this_month; $i++ )
  {
    if ( $dow == 7 )
    {
      $return .= '</tr>';
      if ( $i < $days_in_this_month )
        $return .= '<tr>';
      $dow = 0;
    }
    $dow++;
    $class = ( $class == 'row1' ) ? 'row3' : 'row1';
    $a = "<span style=\"color: #808080;\">$i</span>";
    if ( in_array($i, $days_to_link) )
    {
      $a = '<a class="wikilink-nonexistent" href="' . htmlspecialchars($session->append_sid(scriptPath . '/irclogs.php?year=' . $year . '&month=' . $month . '&day=' . $i . '&channel=' . $channel)) . '"><b>' . $i . '</b></a>';
    }
    $return .= "<td class=\"$class\">$a</td>";
  }
  
  while ( $dow < 7 )
  {
    $class = ( $class == 'row1' ) ? 'row3' : 'row1';
    $return .= "<td class=\"$class\"></td>";
    $dow++;
  }

  $return .= '</table></div>';
  return $return;
}

$get_valid_year = isset($_GET['year']);
$get_valid_month = ( isset($_GET['month']) && intval($_GET['month']) > 0 && intval($_GET['month']) < 13 );

$year = ( $get_valid_year ) ? intval($_GET['year']) : intval(date('Y'));
$month = ( $get_valid_month ) ? intval($_GET['month']) : intval(date('n'));

function make_nick_color($username)
{
  if ( $username == '' )
    return '';
  $hash = substr(sha1($username), 0, 6);
  $hash = enano_str_split($hash);
  for ( $i = 0; $i < count($hash); $i++ )
  {
    if ( $i % 2 == 1 )
      continue;
    
    // call this a cheap hack or whatever, but intval() doesn't accept 0x????
    $digit = eval("return 0x{$hash[$i]};");
    if ( $digit > 0x9 )
      $digit = "9";
    else
      $digit = strval($digit);
    $hash[$i] = $digit;
  }
  $color = implode('', $hash);
  $span = "<span style=\"color: #$color;\">" . htmlspecialchars($username) . "</span>";
  return $span;
}

function irclog_autoparse_links($text)
{
  $sid = md5(microtime());
  preg_match_all('/((https?|ftp|irc):\/\/([^@\s\]"\':]+)?((([a-z0-9-]+\.)*)[a-z0-9-]+)(\/[A-z0-9_%\|~`!\!@#\$\^&\*\(\):;\.,\/-]*(\?(([a-' 
               . 'z0-9_-]+)(=[A-z0-9_%\|~`\!@#\$\^&\*\(\):;\.,\/-\[\]]*)?((&([a-z0-9_-]+)(=[A-z0-9_%\|~`!\!@#\$\^&\*\(\):;\.,\/-]*)?)*))'
               . '?)?)?)/', $text, $matches);
  foreach ( $matches[0] as $i => $match )
  {
    $text = str_replace_once($match, "{AUTOLINK:$sid:$i}", $text);
  }
  $text = htmlspecialchars($text);
  foreach ( $matches[0] as $i => $match )
  {
    $match_short = $match;
    if ( strlen($match) > 75 )
    {
      $match_short = htmlspecialchars(substr($match, 0, 25)) . '...' . htmlspecialchars(substr($match, -25));
    }
    $match = htmlspecialchars($match);
    $text = str_replace_once("{AUTOLINK:$sid:$i}", "<a href=\"$match\">$match_short</a>", $text);
  }
  return $text;
}

function irclog_protect_emails($text)
{
  global $email;
  preg_match_all('/([a-z0-9_-]+@(([a-z0-9-]+\.)*)[a-z0-9-]+)/', $text, $matches);
  foreach ( $matches[0] as $match )
  {
    $text = str_replace_once($match, $email->encryptEmail($match), $text);
  }
  return $text;
}

function irclog_format_row($_, $row)
{
  static $class = 'row1';
  $class = ( $class == 'row1' ) ? 'row3' : 'row1';
  
  $time = date('H:i', $row['timestamp']);
  $nick = make_nick_color($row['nick']);
  
  $message = irclog_autoparse_links($row['line']);
  $message = irclog_protect_emails($message);
  $message = RenderMan::smilieyize($message);
  if ( $row['nick'] == '' )
    $message = "<span style=\"color: #808080;\">" . $message . "</span>";
  return "              <tr>
            <td class=\"$class\">$time</td>
            <td class=\"$class\">$nick</td>
            <td class=\"$class\">$message</td>
          </tr>\n";
}

if ( $get_valid_year && $get_valid_month && isset($_GET['day']) && isset($_GET['channel']) )
{
  $days_in_this_month = $days_in_month[$month];
  if ( $month == 2 && $year !== intval(date('n')) )
    $days_in_this_month = ( $year % 4 == 0 ) ? 29 : 28;
  $day = intval($_GET['day']);
  if ( $day < 1 || $day > $days_in_this_month )
  {
    $day = intval(date('j'));
  }
  // mode is view logs, and we have the date on which to display them
  $channel = $db->escape($_GET['channel']);
  if ( !preg_match('/^[a-z0-9_-]+$/i', $channel) )
    die('Channel contains XSS attempt');
  
  $datekey = $year . '-' . 
      ( $month < 10 ? "0$month" : $month ) . '-' . 
      ( $day < 10 ? "0$day" : $day );
  
  $q = $db->sql_query("SELECT * FROM irclog WHERE day='$datekey' AND channel = '#$channel' ORDER BY timestamp ASC;");
  if ( !$q )
    $db->_die();
  
  echo '<p><a href="' . htmlspecialchars($session->append_sid(scriptPath . "/irclogs.php?year=$year&month=$month&channel=$channel")) . '">&lt; Back to date listings</a></p>';
  
  if ( $db->numrows() < 1 )
  {
    echo '<p>No chat logs for today.</p>';
  }
  else
  {
    $count = $db->numrows();
    
    $start = ( isset($_GET['start']) ) ? intval($_GET['start']) : 0;
    
    // ($q, $tpl_text, $num_results, $result_url, $start = 0, $perpage = 10, $callers = Array(), $header = '', $footer = '')
    $html = paginate($q, '{id}', $count, $session->append_sid(scriptPath . "/irclogs.php?year=$year&month=$month&day=$day&channel=$channel&start=%s"), $start, 100, array('id' => 'irclog_format_row'), '<p>All times are UTC.</p><div class="tblholder"><table border="0" cellspacing="1" cellpadding="4">', '</table></div>');
    
    echo $html;
  }
}
else if ( isset($_GET['channel']) )
{
  // show log calendar
  $channel = $db->escape($_GET['channel']);
  if ( !preg_match('/^[a-z0-9_-]+$/i', $channel) )
    die('Channel contains XSS attempt');
  
  echo "<h3>Chat logs for #$channel</h3>";
  
  echo '<p><a href="' . htmlspecialchars($session->append_sid(scriptPath . "/irclogs.php")) . '">&lt; Back to channel list</a></p>';

  $year = strval($year);
  $month = strval($month);
  
  if ( $month < 10 )
    $month = "0" . $month;
  
  $q = $db->sql_query("SELECT day FROM irclog WHERE day LIKE '$year-$month-__' AND channel = '#$channel' GROUP BY day ORDER BY timestamp ASC;");
  if ( !$q )
    $db->_die();
  
  $days = array();
  while ( $row = $db->fetchrow() )
  {
    if ( !preg_match('/^[0-9]+-[0-9]+-([0-9]+)$/', $row['day'], $match) )
    {
      continue;
    }
    $days[] = intval($match[1]);
  }
  
  echo irc_make_calendar($year, $month, $days);
}
else
{
  // list channels
  $q = $db->sql_query("SELECT channel FROM irclog GROUP BY channel;");
  if ( !$q )
    $db->_die();
  echo '<h3>List of available channels</h3>';
  if ( $row = $db->fetchrow() )
  {
    echo '<p>';
    do
    {
      $channel = preg_replace('/^#/', '', $row['channel']);
      echo '<a href="' . htmlspecialchars($session->append_sid(scriptPath . "/irclogs.php?channel={$channel}")) . '">' . $row['channel'] . '</a><br />';
    }
    while ( $row = $db->fetchrow() );
    echo '</p>';
  }
  else
  {
    echo '<p>No channels logged.</p>';
  }
}

$q = $db->sql_query('USE enano_www;');
if ( !$q )
  $db->_die();

$template->footer();

