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

define('ENANOBOT_ROOT', dirname(__FILE__) . '/../');

// load Enano for auth
/*
require('includes/common.php');
if ( $session->user_level < USER_LEVEL_ADMIN )
{
  die_friendly('Access denied', '<p>Admin rights needed to use this script.</p>');
}

$db->close();
unset($db, $session, $paths, $template, $plugins);
*/

// We're authed.
// Load config
require(ENANOBOT_ROOT . 'config.php');

// check config
if ( empty($mysql_host) || empty($mysql_user) || empty($mysql_dbname) )
{
  die("Bad config file - have a look at config-sample.php.\n");
}

// connect to MySQL
$mysql_conn = @mysql_connect($mysql_host, $mysql_user, $mysql_pass);
if ( !$mysql_conn )
{
  $m_e = mysql_error();
  echo "Error connecting to MySQL: $m_e\n";
  exit(1);
}
$q = @mysql_query('USE `' . $mysql_dbname . '`;', $mysql_conn);
if ( !$q )
{
  $m_e = mysql_error();
  echo "Error selecting database: $m_e\n";
  exit(1);
}

function mysql_die()
{
  $m_e = mysql_error();
  die("MySQL error: $m_e");
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>EnanoBot snippet management</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
  </head>
  <body>
    <h1>EnanoBot snippet management</h1>
    <form action="snippets.php" method="post" enctype="multipart/form-data">
      <fieldset>
        <legend>Add a snippet</legend>
        <table border="1" cellspacing="0" cellpadding="4">
          <tr>
            <td>Snippet code<br />
                <small>all lowercase, no spaces; ex: mysnippet</small></td>
            <td><input type="text" name="snippet_add_code" size="100" tabindex="1" /></td>
          </tr>
          <tr>
            <td>Text<br />
                <small>anything you want, keep it relatively short.</small></td>
            <td><input type="text" name="snippet_add_text" size="100" tabindex="2" /></td>
          </tr>
          <tr>
            <td>Channels<br />
                <small>separate with pipe characters, ex: #enano|#enano-dev|#ubuntu</small></td>
            <td><input type="text" name="snippet_add_channels" size="100" tabindex="3" /></td>
          </tr>
        </table>
      </fieldset>
      <fieldset>
        <legend>Edit existing snippets</legend>
        <table border="1" cellspacing="0" cellpadding="4">
          <tr>
            <th>Code</th>
            <th>Snippet text</th>
            <th>Channels</th>
            <th>Delete</th>
          </tr>
        <?php
            if ( !empty($_POST['snippet_add_code']) && !empty($_POST['snippet_add_text']) && !empty($_POST['snippet_add_channels']) )
            {
              $code = mysql_real_escape_string($_POST['snippet_add_code']);
              $text = mysql_real_escape_string($_POST['snippet_add_text']);
              $channels = mysql_real_escape_string($_POST['snippet_add_channels']);
              $q2 = @mysql_query("INSERT INTO snippets(snippet_code, snippet_text, snippet_channels) VALUES
                                    ( '$code', '$text', '$channels' );", $mysql_conn);
              if ( !$q2 )
                mysql_die();
            }
            $q = @mysql_query('SELECT snippet_id, snippet_code, snippet_text, snippet_channels FROM snippets ORDER BY snippet_code ASC;');
            if ( !$q )
              mysql_die();
            while ( $row = @mysql_fetch_assoc($q) )
            {
              if ( isset($_POST['snippet']) && @is_array(@$_POST['snippet']) )
              {
                if ( isset($_POST['snippet'][$row['snippet_id']]) )
                {
                  // delete it?
                  if ( isset($_POST['snippet'][$row['snippet_id']]['delete']) )
                  {
                    $q2 = mysql_query("DELETE FROM snippets WHERE snippet_id = {$row['snippet_id']};", $mysql_conn);
                    if ( !$q2 )
                      mysql_die();
                    continue;
                  }
                  // has it changed?
                  else if ( $_POST['snippet'][$row['snippet_id']]['code'] != $row['snippet_code'] ||
                       $_POST['snippet'][$row['snippet_id']]['text'] != $row['snippet_text'] ||
                       $_POST['snippet'][$row['snippet_id']]['channels'] != $row['snippet_channels'] )
                  {
                    // yeah, update it.
                    $code = mysql_real_escape_string($_POST['snippet'][$row['snippet_id']]['code']);
                    $text = mysql_real_escape_string($_POST['snippet'][$row['snippet_id']]['text']);
                    $channels = mysql_real_escape_string($_POST['snippet'][$row['snippet_id']]['channels']);
                    $q2 = mysql_query("UPDATE snippets SET snippet_code = '$code', snippet_text = '$text', snippet_channels = '$channels' WHERE snippet_id = {$row['snippet_id']};", $mysql_conn);
                    if ( !$q2 )
                      mysql_die();
                    $row = array(
                        'snippet_id' => $row['snippet_id'],
                        'snippet_code' => $_POST['snippet'][$row['snippet_id']]['code'],
                        'snippet_text' => $_POST['snippet'][$row['snippet_id']]['text'],
                        'snippet_channels' => $_POST['snippet'][$row['snippet_id']]['channels']
                      );
                  }
                }
              }
              echo '  <tr>';
              echo '<td><input type="text" name="snippet[' . $row['snippet_id'] . '][code]" value="' . htmlspecialchars($row['snippet_code']) . '" /></td>';
              echo '<td><input type="text" size="100" name="snippet[' . $row['snippet_id'] . '][text]" value="' . htmlspecialchars($row['snippet_text']) . '" /></td>';
              echo '<td><input type="text" name="snippet[' . $row['snippet_id'] . '][channels]" value="' . htmlspecialchars($row['snippet_channels']) . '" /></td>';
              echo '<td style="text-align: center;"><input type="checkbox" name="snippet[' . $row['snippet_id'] . '][delete]" /></td>';
              echo '</tr>' . "\n        ";
            }
          ?></table>
      </fieldset>
      <div style="text-align: center; margin-top: 20px;">
        <input type="submit" value="Save changes" />
      </div>
    </form>
  </body>
</html><?php

mysql_close($mysql_conn);

