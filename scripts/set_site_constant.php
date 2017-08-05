<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	
	$_REQUEST['name'] = $cmd_vars['name'];
	$_REQUEST['value'] = $cmd_vars['value'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$app->set_site_constant($_REQUEST['name'], $_REQUEST['value']);
	echo "Done!";
}
else echo "Incorrect key.\n";
?>