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
    <p>I've been updating <?php echo $nick; ?> recently with some really cool enhancements to the back-end. This is more technical stuff
       so you might want to read on only if you're a geek.</p>
    <p><?php echo $nick; ?> only stores info with MySQL now. All the stats go into a MySQL table that had an initial size of over 360,000
       records after the import of the existing flat file database. This means that while querying can be slower (things aren't split up
       like they were with the flatfile DB) it's a more portable programming technique and it means it can be easily expanded in the
       future to include more data. The table's indexed so it should be decently fast.</p>
    <p>In addition, smarter functionality is being included, plus a few bugs here and there have been fixed.</p>
  </body>
</head>
