<?php
// If you cannot configure cron to run /includes/minutely.php every minute,
// run this script from the command line instead to keep this app up to date.
// Or visit /cron/minutely.php in your browser
set_time_limit(0);
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = @((bool) $_REQUEST['print_debug']);
	
	$cycle_sec = 55;
	
	do {
		$app->print_debug("Starting bg processes");
		$app->start_regular_background_processes($print_debug);
		$app->print_debug("Waiting ".$cycle_sec." seconds...");
		
		$ref_time = microtime(true);
		while (microtime(true) < $ref_time+$cycle_sec) {
			if ($print_debug) {
				echo ". ";
				$app->flush_buffers();
			}
			sleep(1);
		}
	}
	while (true);
}
else echo "Please run this script as administrator\n";
?>