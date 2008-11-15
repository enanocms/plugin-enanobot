<?php
header('Content-type: application/force-download');
header('Content-disposition: attachment; filename=stats-data.csv');

require('../stats-fe.php');

echo "channel,nick,timestamp\n";

$q = eb_mysql_query('SELECT channel, nick, time FROM stats_messages ORDER BY message_id ASC;');

while ( $row = mysql_fetch_assoc($q) )
{
  echo "{$row['channel']},{$row['nick']},{$row['time']}\n";
}

mysql_free_result($q);
