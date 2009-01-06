<?php
require('../stats-fe.php');
require('../timezone.php');

$channel_list = stats_channel_list();
$first_channel = $channel_list[0];
$channel = ( isset($_REQUEST['channel']) && in_array($_REQUEST['channel'], $channel_list) ) ? $_REQUEST['channel'] : $first_channel;

$title = "$nick - Statistics";
require("./themes/$webtheme/header.php");
?>
    <div style="float: right;">
      <p>
        <?php
        $tz_display = str_replace('_', ' ', str_replace('/', ': ', $tz));
        echo 'Time zone: ' . $tz_display . ' [<a href="changetz.php">change</a>]<br />';
        echo '<small>The time now is ' . date('H:i:s') . '.<br />Statistics now updated constantly (see <a href="news.php">news</a>)</small>';
        ?>
      </p>
      <p>
        <big><b>Channels:</b></big><br />
        <?php
          foreach ( $channels as $i => $c )
          {
            $bold = ( $c == $channel );
            echo $bold ? '<b>' : '';
            echo $bold ? '' : '<a href="index.php?channel=' . urlencode($c) . '">';
            echo $c;
            echo $bold ? '' : '</a>';
            echo $bold ? '</b>' : '';
            echo $i == count($channels) - 1 ? '' : ' | ';
          }
        ?>
      </p>
    </div>
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
    <h1>Last 60 minutes</h1>
    <img alt="Graph image" src="graph.php?mode=lasthour&amp;channel=<?php echo urlencode($channel); ?>" />
    <h1>Last 24 hours</h1>
    <img alt="Graph image" src="graph.php?mode=lastday&amp;channel=<?php echo urlencode($channel); ?>" />
    <h1>Last 2 weeks</h1>
    <img alt="Graph image" src="graph.php?mode=lastweek&amp;channel=<?php echo urlencode($channel); ?>" />
<?php
require("./themes/$webtheme/footer.php");

