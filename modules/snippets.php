<?php

eb_hook('event_channel_msg', 'snippets_event_privmsg($chan, $message);');

function snippets_event_privmsg(&$chan, &$message)
{
  if ( preg_match('/^\![\s]*([a-z0-9_-]+)([\s]*\|[\s]*([^ ]+))?$/', $message['message'], $match) )
  {
    $snippet =& $match[1];
    if ( @$match[3] === 'me' )
      $match[3] = $message['nick'];
    $target_nick = ( !empty($match[3]) ) ? "{$match[3]}, " : "{$message['nick']}, ";
    if ( $snippet == 'snippets' )
    {
      // list available snippets
      $m_et = false;
      $q = eb_mysql_query('SELECT snippet_code, snippet_channels FROM snippets;');
      if ( mysql_num_rows($q) < 1 )
      {
        $chan->msg(eb_censor_words("{$message['nick']}, I couldn't find that snippet (\"$snippet\") in the database."), true);
      }
      else
      {
        $snippets = array();
        while ( $row = mysql_fetch_assoc($q) )
        {
          $channels = explode('|', $row['snippet_channels']);
          if ( in_array($chan->get_channel_name(), $channels) )
          {
            $snippets[] = $row['snippet_code'];
          }
        }
        $snippets = implode(', ', $snippets);
        $chan->msg(eb_censor_words("{$message['nick']}, the following snippets are available: $snippets"), true);
      }
      @mysql_free_result($q);
    }
    else
    {
      if ( eval(eb_fetch_hook('snippet_dynamic')) )
      {
        return true;
      }
      
      // Look for the snippet...
      $q = eb_mysql_query('SELECT snippet_text, snippet_channels FROM snippets WHERE snippet_code = \'' . mysql_real_escape_string($snippet) . '\';');
      if ( mysql_num_rows($q) < 1 )
      {
        $chan->msg(eb_censor_words("{$message['nick']}, I couldn't find that snippet (\"$snippet\") in the database."), true);
      }
      else
      {
        $row = mysql_fetch_assoc($q);
        $channels = explode('|', $row['snippet_channels']);
        if ( in_array($chan->get_channel_name(), $channels) )
        {
          $chan->msg(eb_censor_words("{$target_nick}{$row['snippet_text']}"), true);
        }
        else
        {
          $chan->msg(eb_censor_words("{$message['nick']}, I couldn't find that snippet (\"$snippet\") in the database."), true);
        }
      }
      @mysql_free_result($q);
    }
  }
}
