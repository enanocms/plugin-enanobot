<?php
require('../config.php');

?><html>
  <head>
    <title><?php echo $nick; ?> - privacy info</title>
    <style type="text/css">
    p.code {
      font-family: monospace;
      margin-left: 1.5em;
    }
    </style>
  </head>
  <body>
    <h1>Privacy information</h1>
    <p><?php echo $nick; ?> is designed to collect IRC statistics. It does this by recording raw data and then letting the frontend (index.php and a
       few backend functions in stats-fe.php) look at the data and draw graphs and measurements based on it.</p>
    <p>The only information <?php echo $nick; ?> collects is</p>
    <ul>
      <li>The time of each message</li>
      <li>The nick that posted that message</li>
      <li>Whether that nick has certain flags, like operator/voice</li>
    </ul>
    <p><?php echo $nick; ?> also gives you the ability to disable recording statistics about you. To clear all your past statistics, type in any channel:</p>
    <p class="code">!deluser</p>
    <p>You can also prevent yourself from being logged in the future with:</p>
    <p class="code">/msg <?php echo $nick; ?> anonymize</p>
    <p>Remove yourself from the anonymization list with:</p>
    <p class="code">/msg <?php echo $nick; ?> denonymize</p>
    <p>Want to know more about the numbers <?php echo $nick; ?> collects? <a href="datafile.php">Download <?php echo $nick; ?>'s data file yourself</a> (<a href="json.php">in JSON format</a>).</p>
  </body>
</head>
