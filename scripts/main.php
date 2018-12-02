<?php
// If you cannot configure cron to run /includes/minutely.php every minute,
// run this script from the command line instead to keep this app up to date.
// Or visit /cron/minutely.php in your browser
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	do {
		echo $app->start_regular_background_processes();
		echo "Waiting 60 seconds...\n";
		if (!$app->running_from_commandline()) $app->flush_buffers();
		sleep(60);
	}
	while (true);
}
else echo "Syntax is: main.php?key=<CRON_KEY_STRING>\n";
?>
