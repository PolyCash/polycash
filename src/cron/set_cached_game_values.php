<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug','game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$only_game_id = false;
	if (!empty($_REQUEST['game_id'])) $only_game_id = (int) $_REQUEST['game_id'];
	
	$process_lock_name = "set_cached_game_values";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if ($only_game_id || (!$process_locked && $app->lock_process($process_lock_name))) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$script_target_time = 565;
		$loop_target_time = 30;
		$blockchains = [];
		
		do {
			$loop_start_time = microtime(true);
			
			$game_q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE ";
			if ($only_game_id) $game_q .= "g.game_id=".$only_game_id;
			else $game_q .= "b.online=1 AND g.game_status IN('published','running')";
			$db_running_games = $app->run_query($game_q);
			
			while ($db_running_game = $db_running_games->fetch()) {
				$ref_time = microtime(true);
				if (empty($blockchains[$db_running_game['blockchain_id']])) $blockchains[$db_running_game['blockchain_id']] = new Blockchain($app, $db_running_game['blockchain_id']);
				$running_game = new Game($blockchains[$db_running_game['blockchain_id']], $db_running_game['game_id']);
				
				if ($running_game->last_block_id() == $running_game->blockchain->last_block_id()) {
					$running_game->set_cached_fields();
					if ($print_debug) $app->print_debug("Set cached values for ".$running_game->db_game['name']." in ".round(microtime(true)-$ref_time, 6)." sec");
				}
				else if ($print_debug) $app->print_debug("Skipped ".$running_game."; it's not fully loaded.");
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max($loop_target_time, $loop_time*8);
			
			if ($loop_time < $loop_target_time) {
				$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
				
				if ($print_debug) $app->print_debug("Sleeping ".round($loop_target_time - $loop_time, 2)." sec");
				
				usleep($sleep_usec);
			}
		}
		while (microtime(true)-$script_start_time < $script_target_time);
	}
	else echo "This process is already running.\n";
}
else echo "Error: please supply the right key\n";
?>
