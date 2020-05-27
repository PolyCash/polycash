<?php
// If you cannot configure cron to run /includes/minutely.php every minute,
// run this script from the command line instead to keep this app up to date.
// Or visit /cron/minutely.php in your browser
set_time_limit(0);
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	do {
		echo $app->start_regular_background_processes();
		$app->print_debug("Waiting 60 seconds...");
		sleep(60);
	}
	while (true);
}
else echo "Please run this script as administrator\n";
?>