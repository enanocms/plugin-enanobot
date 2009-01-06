<?php
require('../timezone.php');
require('../stats-fe.php');
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

$title = "$nick - set time zone";
require("./themes/$webtheme/header.php");

echo '<br />';

    if ( $set_zone )
    {
      $target = rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';
      echo '<div class="success">' . "Successfully set time zone to <b>{$set_zone}</b>. <a href=\"$target\">Return to the stats page</a>." . '</div>';
    }
    ?>
    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="post">
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
  
<?php
require("./themes/$webtheme/footer.php");
?>
