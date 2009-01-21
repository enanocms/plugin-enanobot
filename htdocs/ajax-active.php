<?php

require_once('../stats-fe.php');
require_once('../timezone.php');

if ( !isset($channel) )
{
  $channel_list = stats_channel_list();
  $first_channel = $channel_list[0];
  $channel = ( isset($_REQUEST['channel']) && in_array($_REQUEST['channel'], $channel_list) ) ? $_REQUEST['channel'] : $first_channel;
}

?>
    <h1>Active members</h1>
    <p>For the last 1, 5, and 15 minutes:
        <?php echo count(stats_activity_percent($channel, 1)) . ', ' .
                   count(stats_activity_percent($channel, 5)) . ', ' .
                   count(stats_activity_percent($channel, 15)) . ' (respectively)';
        ?>
        </p>
    <h1>Currently active members:</h1>
    <p>These people have posted in the last 3 minutes:</p>
    <ul>
      <?php
      $datum = stats_activity_percent($channel, 3);
      $count = stats_message_count($channel, 3);
      if ( empty($datum) )
        echo '<li>No recent posts.</li>';
      foreach ( $datum as $usernick => $pct )
      {
        $total = round($pct * $count);
        $pct = round(100 * $pct, 1);
        echo "<li>$usernick - $pct% ($total)</li>\n";
      }
      ?>
    </ul>
    <p>Last 20 minutes:</p>
    <ul>
      <?php
      $datum = stats_activity_percent($channel, 20);
      $count = stats_message_count($channel, 20);
      if ( empty($datum) )
        echo '<li>No recent posts.</li>';
      foreach ( $datum as $usernick => $pct )
      {
        $total = round($pct * $count);
        $pct = round(100 * $pct, 1);
        echo "<li>$usernick - $pct% ($total)</li>\n";
      }
      ?>
    </ul>

