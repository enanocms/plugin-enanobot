<?php

global $eb_hooks;
$eb_hooks = array();

function eb_hook($hook_name, $code)
{
  global $eb_hooks;
  if ( !isset($eb_hooks[$hook_name]) )
    $eb_hooks[$hook_name] = array();
  
  $eb_hooks[$hook_name][] = $code;
}

function eb_fetch_hook($hook_name)
{
  global $eb_hooks;
  return ( isset($eb_hooks[$hook_name]) ) ? implode("\n", $eb_hooks[$hook_name]) : 'eb_void();';
}

// null function for filling empty hooks
function eb_void()
{
}

