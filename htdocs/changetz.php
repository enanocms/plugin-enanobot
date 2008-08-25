<?php
require('../timezone.php');
$set_zone = false;
if ( isset($_POST['tz']) )
{
  if ( in_array($_POST['tz'], $zones) )
  {
    setcookie(COOKIE_NAME, $_POST['tz'], time() + ( 365 * 24 * 60 * 60 ));
    $tz = $_POST['tz'];
    date_default_timezone_set($_POST['tz']);
    $set_zone = str_replace('_', ' ', str_replace('/', ': ', $tz));
  }
}
?><html>
  <head>
    <title>Change time zone</title>
    <style type="text/css">
    select, option {
      background-color: white;
    }
    option.other {
      color: black;
      font-weight: normal;
    }
    option.region {
      color: black;
      font-weight: bold;
    }
    option.area {
      color: black;
      font-weight: normal;
      padding-left: 1em;
    }
    option.country {
      color: black;
      font-weight: bold;
      padding-left: 1em;
    }
    option.city {
      color: black;
      font-weight: normal;
      padding-left: 2em;
    }
    div.success {
      border: 1px solid #006300;
      background-color: #d3ffd3;
      padding: 10px;
      margin: 10px 0;
    }
    </style>
  </head>
  <body>
    <?php
    if ( $set_zone )
    {
      $target = dirname($_SERVER['PHP_SELF']) . '/';
      echo '<div class="success">' . "Successfully set time zone to <b>{$set_zone}</b>. <a href=\"$target\">Return to the stats page</a>." . '</div>';
    }
    ?>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    Select time zone:
    <select name="tz">
      <?php
      $zones = get_timezone_list();
      foreach ( $zones as $region => $areas )
      {
        if ( is_string($areas) )
        {
          echo '<option value="' . $areas . '" class="other">' . $areas . '</option>' . "\n      ";
          continue;
        }
        echo '<option disabled="disabled" class="region">' . $region . '</option>' . "\n      ";
        foreach ( $areas as $aid => $area )
        {
          if ( is_array($area) )
          {
            echo '  <option disabled="disabled" class="country">' . str_replace('_', ' ', $aid) . '</option>' . "\n      ";
            foreach ( $area as $city )
            {
              $zoneid = "$region/$aid/$city";
              $sel = ( $zoneid == $tz ) ? ' selected="selected"' : '';
              echo '    <option value="' . $zoneid . '" class="city"' . $sel . '>' . str_replace('_', ' ', $city) . '</option>' . "\n      ";
            }
          }
          else
          {
            $zoneid = "$region/$area";
            $sel = ( $zoneid == $tz ) ? ' selected="selected"' : '';
            echo '  <option value="' . $zoneid . '" class="area"' . $sel . '>' . str_replace('_', ' ', $area) . '</option>' . "\n      ";
          }
        }
      }
      ?>
    </select>
    <input type="submit" value="Save" /><br />
    <small>Make sure you have cookies enabled.</small>
    </form>
  </body>
</html>
