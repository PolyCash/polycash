<?php
// If you cannot configure cron to run /includes/minutely.php every minute,
// run this script from the command line instead to keep empirecoin-web up to date.
// Or visit /cron/minutely.php in your browser
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	do {
		echo $app->start_regular_background_processes();
		sleep(60);
	}
	while (true);
}
else echo "Syntax is: main.php?key=<CRON_KEY_STRING>\n";
?>