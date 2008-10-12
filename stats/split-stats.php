<?php

@set_time_limit(0);
@ini_set('memory_limit', '256M');

if ( $dir = @opendir('.') )
{
  while ( $dh = @readdir($dir) )
  {
    if ( !preg_match('/^stats-data(-[0-9]+)\.php$/', $dh) )
      continue;
    
    split_stats_file($dh);
  }
  closedir($dir);
}

function split_stats_file($file)
{
  echo "loading $file";
  
  require($file);
  if ( !is_array($stats_data) )
  {
    return false;
  }
  
  unlink($file);
  
  echo "\rprocessing $file\n";
  
  $newdata = array();
  foreach ( $stats_data['messages'] as $channel => &$chandata )
  {
    echo "  processing channel $channel\n";
    foreach ( $chandata as $i => $message )
    {
      $message_day = gmdate('Ymd', $message['time']);
      if ( !isset($newdata[$message_day]) )
      {
        echo "\r    processing " . gmdate('Y-m-d', $message['time']);
        $newdata[$message_day] = array(
          'messages' => array()
        );
        if ( isset($stats_data['counts']) )
        {
          $newdata[$message_day]['counts'] = $stats_data['counts'];
        }
        if ( isset($stats_data['anonymous']) )
        {
          $newdata[$message_day]['anonymous'] = $stats_data['anonymous'];
        }
      }
      if ( !isset($newdata[$message_day]['messages'][$channel]) )
      {
        $newdata[$message_day]['messages'][$channel] = array();
      }
      $newdata[$message_day]['messages'][$channel][] = $message;
      unset($chandata[$i]);
    }
    echo "\n";
  }
  foreach ( $newdata as $date => &$data )
  {
    echo "\r  writing output for $date";
    write_stats_file("stats-data-$date.php", $data);
  }
  echo "\n";
}

function write_stats_file($file, $data)
{
  $fp = @fopen($file, 'w');
  if ( !$fp )
    return false;
  
  ob_start();
  var_export($data);
  $data = ob_get_contents();
  ob_end_clean();
  
  fwrite($fp, "<?php\n\$stats_data = $data;\n");
  fclose($fp);
  unset($data);
}
