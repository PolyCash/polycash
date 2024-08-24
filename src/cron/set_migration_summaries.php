<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);
$script_target_time = 175;

$allowed_params = ['game_id', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = true;

	$only_game_id = !empty($_REQUEST['game_id']) ? (int) $_REQUEST['game_id'] : null;

	$force = !empty($_REQUEST['force']);

	$process_lock_name = "set_migration_summaries";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if ($force || (!$process_locked && $app->lock_process($process_lock_name))) {
		$success_count = 0;
		$fail_count = 0;
		do {
			$ref_time = microtime(true);
			$set_migration_params = [];
			$set_migration_q = "SELECT * FROM game_definition_migrations WHERE ";
			if ($only_game_id) {
				$set_migration_params['game_id'] = $only_game_id;
				$set_migration_q .= "game_id=:game_id AND ";
			}
			$set_migration_q .= "cached_difference_summary IS NULL AND missing_game_defs_at IS NULL ORDER BY migration_id DESC LIMIT 1;";
			$migration = $app->run_query($set_migration_q, $set_migration_params)->fetch(PDO::FETCH_ASSOC);
			
			if ($migration) {
				if ($print_debug) $app->print_debug("Setting summary for migration #".$migration['migration_id']);

				$difference_summary = $app->set_migration_difference_summary($migration);
				if ($difference_summary) $success_count++;
				else $fail_count++;
				
				if ($print_debug) $app->print_debug(($difference_summary ? "Succeeded" : "Failed")." in ".round(microtime(true)-$ref_time, 6)." sec");
			}
		}
		while ($migration && microtime(true)-$script_start_time < $script_target_time);

		$sleep_sec = round($script_target_time - (microtime(true)-$script_start_time), 6);

		if ($print_debug) $app->print_debug("Set summary for ".$success_count." migrations, ".$fail_count." failed, in ".round(microtime(true)-$script_start_time, 6)." sec, sleeping ".$sleep_sec." sec.");

		usleep($sleep_sec*pow(10, 6));
	}
	else echo "Set migration summaries process is already running.\n";
}
else echo "Error: incorrect key supplied in cron/fetch_currency_prices.php\n";
?>
