<?php
set_time_limit(0);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 290;
$loop_target_time = 1;
$script_start_time = microtime(true);

$allowed_params = ['print_debug', 'game_id', 'never_stop'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$never_stop = false;
	if (!empty($_REQUEST['never_stop'])) $never_stop = true;
	
	$only_game_id = empty($_REQUEST['game_id']) ? false : (int) $_REQUEST['game_id'];
	
	$process_lock_name = "load_game".($only_game_id ? "_".$only_game_id : "");
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = [];
		
		do {
			$loop_start_time = microtime(true);
			
			$db_running_games = $app->fetch_running_games()->fetchAll();
			
			foreach ($db_running_games as $db_running_game) {
				if (!$only_game_id || $db_running_game['game_id'] == $only_game_id) {
					if (empty($blockchains[$db_running_game['blockchain_id']])) $blockchains[$db_running_game['blockchain_id']] = new Blockchain($app, $db_running_game['blockchain_id']);
					$running_game = new Game($blockchains[$db_running_game['blockchain_id']], $db_running_game['game_id']);
					$running_game->sync($print_debug, 30);
				}
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$sleep_usec = max(0, round(pow(10,6)*($loop_target_time - $loop_time)));
			
			if ($print_debug) $app->print_debug("Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n");
			
			usleep($sleep_usec);
		}
		while ($never_stop || microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
	}
	else echo "Game load script is already running...\n";
}
else echo "Please supply the correct key.\n";
?>