<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug','game_id', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "integrity_checks";
	$process_locked = $app->check_process_running($process_lock_name);
	
	$next_integrity_check_time = $app->get_site_constant("next_integrity_check_time");
	
	if ((empty($next_integrity_check_time) || time() >= $next_integrity_check_time) || !empty($_REQUEST['force'])) {
		if (!$process_locked || !empty($_REQUEST['force'])) {
			$app->set_site_constant($process_lock_name, getmypid());
			
			$false_spent_ios = $app->run_query("SELECT * FROM transaction_ios WHERE spend_status='spent' AND spend_transaction_id IS NULL;")->fetchAll(PDO::FETCH_ASSOC);
			
			$correction_count = 0;
			
			if (count($false_spent_ios) > 0) {
				$app->log_message("Integrity check found ".count($false_spent_ios)." txos incorrectly marked as spent and attempted fix.");
				$app->run_query("UPDATE transaction_ios SET spend_status='unspent' WHERE spend_status='spent' AND spend_transaction_id IS NULL;");
				$correction_count++;
			}
			
			$next_integrity_check_time = strtotime(date("Y-m-d H:00:01")." +6 hours");
			$app->set_site_constant("next_integrity_check_time", $next_integrity_check_time);
			
			echo "Integrity check made ".$correction_count." corrections. Next run: ".date("Y-m-d H:i:s", $next_integrity_check_time)."\n";
		}
		else echo "Process is already running.\n";
	}
	else echo "Process will run next at ".date("Y-m-d H:i:s", $next_integrity_check_time)."\n";
}
else echo "Please run this script from the command line.\n";
