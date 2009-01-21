<?php

function check_permissions($nick, $params, $quiet = true)
{
  global $permissions, $irc;
  
  // I have all the power.
  if ( $nick === $GLOBALS['nick'] )
  {
    if ( defined('LIBIRC_DEBUG') )
      echo "[!!!] Granted action {$params['context']} to {$GLOBALS['nick']} (self)\n";
    return true;
  }
  
  // Is the user in the permissions table?
  if ( !isset($permissions[$nick]) )
  {
    if ( defined('LIBIRC_DEBUG') )
      echo "[!!!] Denied action {$params['context']} to {$nick} (not in table)\n";
    return false;
  }
  
  // Make sure the user is identified
  $whois = $irc->whois($nick);
  if ( !$whois || ( $whois && !$whois['identified']) )
  {
    if ( defined('LIBIRC_DEBUG') )
      echo "[!!!] Denied action {$params['context']} to {$nick} (whois check failed)\n";
    if ( !$quiet )
      $irc->privmsg($nick, "Please identify to services before you do that. (If you are already identified, wait 20 seconds for the whois cache to clear and try again)");
    return false;
  }
  
  // Is the user an admin?
  if ( in_array('admin', $permissions[$nick]) && $params['context'] !== 'alert' )
  {
    if ( defined('LIBIRC_DEBUG') )
      echo "[!!!] Granted action {$params['context']} to {$nick} (has admin rights)\n";
    return true;
  }
  
  switch($params['context']):
    case 'channel':
      if ( isset($permissions[$nick]['channel']) && is_array($permissions[$nick]['channel']) )
      {
        if ( in_array($params['channel'], $permissions[$nick]['channel']) )
        {
          if ( defined('LIBIRC_DEBUG') )
            echo "[!!!] Granted action {$params['context']} to {$nick} in channel {$params['channel']} (on channel whitelist)\n";
          return true;
        }
      }
      if ( defined('LIBIRC_DEBUG') )
        echo "[!!!] Denied action {$params['context']} to {$nick} in channel {$params['channel']} (not on channel whitelist)\n";
      return false;
    default:
      eval(eb_fetch_hook('permission_check'));
      if ( isset($result) )
      {
        $perm = $result ? 'Granted' : 'Denied';
        if ( defined('LIBIRC_DEBUG') )
          echo "[!!!] $perm action {$params['context']} to {$nick} (plugin overridden)\n";
        return $result;
      }
      $result = in_array($params['context'], $permissions[$nick]);
      $perm = $result ? 'Granted' : 'Denied';
      if ( defined('LIBIRC_DEBUG') )
        echo "[!!!] $perm action {$params['context']} to {$nick} (default handler)\n";
      return $result;
  endswitch;
}

