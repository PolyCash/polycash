<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$app->delete_unconfirmable_transactions();
	echo "Done!\n";
}
else echo "Wrong key.\n";
?>