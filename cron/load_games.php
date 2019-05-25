<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_target_time = 59;
$script_start_time = microtime(true);

$allowed_params = ['print_debug', 'game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "load_game";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = array();
		
		$loop_target_time = $app->get_site_constant("loop_target_time");
		do {
			$loop_start_time = microtime(true);
			
			$real_game_q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_status IN ('published','running')";
			if (!empty($_REQUEST['game_id'])) $real_game_q .= " AND g.game_id='".(int)$_REQUEST['game_id']."'";
			$real_game_q .= " AND b.online=1;";
			$real_game_r = $app->run_query($real_game_q);
			if ($print_debug) echo "Looping through ".$real_game_r->rowCount()." games.\n";
			
			while ($db_real_game = $real_game_r->fetch()) {
				if (empty($blockchains[$db_real_game['blockchain_id']])) $blockchains[$db_real_game['blockchain_id']] = new Blockchain($app, $db_real_game['blockchain_id']);
				$real_game = new Game($blockchains[$db_real_game['blockchain_id']], $db_real_game['game_id']);
				$real_game->sync($print_debug);
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max(1, $loop_time);
			$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			if ($print_debug) echo "script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n";
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
	}
	else echo "Game load script is already running...\n";
}
else echo "Please supply the correct key.\n";
?>