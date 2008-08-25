<?php
header('Content-type: application/force-download');
header('Content-disposition: attachment; filename=stats-data.php');

echo file_get_contents('../stats-data.php');

