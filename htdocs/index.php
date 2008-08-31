<?php
require('../stats-fe.php');
require('../timezone.php');
require('../config.php');

$channels = array_keys($stats_data['messages']);
$first_channel = $channels[0];
$channel = ( isset($_REQUEST['channel']) && isset($stats_data['messages'][$_REQUEST['channel']]) ) ? $_REQUEST['channel'] : $first_channel;
?>

<html>
  <head>
    <title><?php echo $nick; ?> - Statistics</title>
    <style type="text/css">
    div.footer {
      font-size: smaller;
      padding-top: 10px;
      margin-top: 10px;
      border-top: 1px solid #aaa;
    }
    </style>
  </head>
  <body>
    <div style="float: right;">
      <p>
        <?php
        $tz_display = str_replace('_', ' ', str_replace('/', ': ', $tz));
        echo 'Time zone: ' . $tz_display . ' [<a href="changetz.php">change</a>]<br />';
        echo '<small>The time now is ' . date('H:i:s') . '.<br />Statistics last written to disk at ' . date('H:i:s', filemtime('../stats-data.php')) . '.</small>';
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
    <h1>Last 24 hours</h1>
    <img alt="Graph image" src="24hours.php?channel=<?php echo urlencode($channel); ?>" />
    
    <div class="footer">
    <b><?php echo $nick; ?> is a privacy-respecting bot.</b> <a href="privacy.php">Read about what information <?php echo $nick; ?> collects</a>
    </div>
  </body>
</head>
