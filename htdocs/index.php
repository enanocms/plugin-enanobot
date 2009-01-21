<?php
require_once('../stats-fe.php');
require_once('../timezone.php');

$channel_list = stats_channel_list();
$first_channel = $channel_list[0];
$channel = ( isset($_REQUEST['channel']) && in_array($_REQUEST['channel'], $channel_list) ) ? $_REQUEST['channel'] : $first_channel;

$title = "$nick - Statistics";
require("./themes/$webtheme/header.php");
?>
    <script type="text/javascript" src="ajax-update.js"></script>
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
      <p>Update every <input id="update_ivl" size="3" value="..." style="text-align: center;" /> seconds<br />
         <small>(Javascript required, min. 5 secs, default 30)</small><br />
         </p>
    </div>
    <div id="active-members">
      <?php
      require('ajax-active.php');
      ?>
    </div>
    <h1>Last 60 minutes</h1>
    <img class="graph" alt="Graph image" src="graph.php?mode=lasthour&amp;channel=<?php echo urlencode($channel); ?>" />
    <h1>Last 24 hours</h1>
    <img class="graph" alt="Graph image" src="graph.php?mode=lastday&amp;channel=<?php echo urlencode($channel); ?>" />
    <h1>Last 2 weeks</h1>
    <img class="graph" alt="Graph image" src="graph.php?mode=lastweek&amp;channel=<?php echo urlencode($channel); ?>" />
<?php
require("./themes/$webtheme/footer.php");

