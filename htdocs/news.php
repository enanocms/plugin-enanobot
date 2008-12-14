<?php
require('../config.php');

?><html>
  <head>
    <title><?php echo $nick; ?> - updates</title>
    <style type="text/css">
    p.code {
      font-family: monospace;
      margin-left: 1.5em;
    }
    </style>
  </head>
  <body>
    <h1>Updates and changes</h1>
    <h3>2008-12-24</h3>
    <p><?php echo $nick; ?> now has modular graph support and thus is able to show different graphs. Included now are options for the last
       24 hours as before, plus the last two weeks and the last 30 days. More, of course, can be added if needed.</p>
    <h3>2008-11-15</h3>
    <p>I've been updating <?php echo $nick; ?> recently with some really cool enhancements to the back-end. This is more technical stuff
       so you might want to read on only if you're a geek.</p>
    <p><?php echo $nick; ?> only stores info with MySQL now. All the stats go into a MySQL table that had an initial size of over 360,000
       records after the import of the existing flat file database. It makes for a more portable programming technique and it means it can
       be easily expanded in the future to include more data. The table's indexed so it should be decently fast.</p>
    <p>In addition, smarter functionality is being included, plus a few bugs here and there have been fixed.</p>
  </body>
</head>
