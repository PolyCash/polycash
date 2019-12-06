<?php
set_time_limit(0);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 290;
$loop_target_time = 5;
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
		
		$blockchains = [];
		
		do {
			$loop_start_time = microtime(true);
			
			$running_game_params = [];
			$running_game_q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_status IN ('published','running')";
			if (!empty($_REQUEST['game_id'])) {
				$running_game_q .= " AND g.game_id=:filter_game_id";
				$running_game_params['filter_game_id'] = $_REQUEST['game_id'];
			}
			$running_game_q .= " AND b.online=1;";
			$db_running_games = $app->run_query($running_game_q, $running_game_params);
			if ($print_debug) echo "Looping through ".$db_running_games->rowCount()." games.\n";
			
			while ($db_running_game = $db_running_games->fetch()) {
				if (empty($blockchains[$db_running_game['blockchain_id']])) $blockchains[$db_running_game['blockchain_id']] = new Blockchain($app, $db_running_game['blockchain_id']);
				$running_game = new Game($blockchains[$db_running_game['blockchain_id']], $db_running_game['game_id']);
				$running_game->sync($print_debug, 30);
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$sleep_usec = max(0, round(pow(10,6)*($loop_target_time - $loop_time)));
			
			if ($print_debug) echo "script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n\n";
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
	}
	else echo "Game load script is already running...\n";
}
else echo "Please supply the correct key.\n";
?>