<?php
require('../config.php');
require('../stats-fe.php');

$title = "$nick - privacy info";
require("./themes/$webtheme/header.php");

?>  <style type="text/css">
    p.code {
      font-family: monospace;
      margin-left: 1.5em;
    }
    </style>
    <h1>Privacy information</h1>
    <p><?php echo $nick; ?> is designed to collect IRC statistics. It does this by recording raw data and then letting the frontend (index.php and the
       backend access abstraction in stats-fe.php) look at the data and draw graphs and measurements based on it.</p>
    <p>The only information <?php echo $nick; ?> collects is</p>
    <ul>
      <li>The time of each message</li>
      <li>The nick that posted that message</li>
    </ul>
    <p>In addition, <?php echo $nick; ?> knows whether users currently have permissions such as operator and voice, but this information isn't logged (it's used to determine who can do what). This means that the web interface never knows for sure who is in the channel.</p>
    <p><?php echo $nick; ?> also gives you the ability to disable recording statistics about you. To clear all your past statistics, type in any channel:</p>
    <p class="code">!deluser</p>
    <p>(Moderators can also type:</p>
    <p class="code">!deluser | SomeNick</p>
    <p>to remove statistics for a flooder or spammer)</p>
    <p>You can prevent yourself from being logged in the future with:</p>
    <p class="code">/msg <?php echo $nick; ?> anonymize</p>
    <p>You'll be asked if you want to anonymize your past statistics as well.</p>
    <p>Remove yourself from the anonymization list with:</p>
    <p class="code">/msg <?php echo $nick; ?> denonymize</p>
    <p>Want to know more about the numbers <?php echo $nick; ?> collects? <a href="datafile.php">Download a dump of <?php echo $nick; ?>'s database yourself</a>.</p>
<?php
require("./themes/$webtheme/footer.php");
