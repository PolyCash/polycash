<?php
set_time_limit(0);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug','game_id', 'print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$only_game_id = false;
	if (!empty($_REQUEST['game_id'])) $only_game_id = (int) $_REQUEST['game_id'];
	
	$process_lock_name = "set_cached_game_definition";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if ($only_game_id || (!$process_locked && $app->lock_process($process_lock_name))) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$script_target_time = 580;
		$loop_target_time = 15;
		$blockchains = [];
		
		do {
			$loop_start_time = microtime(true);
			
			$game_q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE ";
			if ($only_game_id) $game_q .= "g.game_id=".$only_game_id;
			else $game_q .= "b.online=1 AND g.game_status IN('published','running')";
			$db_running_games = $app->run_query($game_q);
			
			while ($db_running_game = $db_running_games->fetch()) {
				if (empty($blockchains[$db_running_game['blockchain_id']])) $blockchains[$db_running_game['blockchain_id']] = new Blockchain($app, $db_running_game['blockchain_id']);
				$running_game = new Game($blockchains[$db_running_game['blockchain_id']], $db_running_game['game_id']);
				
				if ($print_debug) $app->print_debug("Setting cached definitions for ".$running_game->db_game['name']);
				GameDefinition::set_cached_definition_hashes($running_game, $print_debug);
				
				if ($running_game->db_game['cached_definition_hash'] != $running_game->db_game['defined_cached_definition_hash']) {
					if ($running_game->last_block_id() == $running_game->blockchain->last_block_id()) {
						$actual_game_def_str = GameDefinition::get_game_definition_by_hash($app, $running_game->db_game['cached_definition_hash']);
						$defined_game_def_str = GameDefinition::get_game_definition_by_hash($app, $running_game->db_game['defined_cached_definition_hash']);

						if ($actual_game_def_str && $defined_game_def_str) {
							$actual_game_def = json_decode($actual_game_def_str);
							$defined_game_def = json_decode($defined_game_def_str);
							$message = $app->log_message("Automatically applying defined to actual: ".$running_game->db_game['cached_definition_hash']." -> ".$running_game->db_game['defined_cached_definition_hash']);
							if ($print_debug) $app->print_debug($message);
							$migrate_message = GameDefinition::migrate_game_definitions($running_game, null, "apply_defined_to_actual", $show_internal_params=false, $actual_game_def, $defined_game_def);
							if ($print_debug) $app->print_debug($migrate_message);
						}
						else {
							$message = $app->log_message("Failed to apply defined (".$running_game->db_game['defined_cached_definition_hash'].") to actual (".$running_game->db_game['cached_definition_hash'].") for ".$running_game->db_game['name']." after failing to fetch defs.");
							if ($print_debug) $app->print_debug($message);
						}
					}
					else if ($print_debug) $app->print_debug("Skipping application of game def, ".$running_game->db_game['name']." is not fully loaded.");
				}
				else if ($print_debug) $app->print_debug("Skipping application of game def, specified and loaded are the same for ".$running_game->db_game['name'].".");

				$set_cached_sec = round(microtime(true)-$loop_start_time, 8);
				$sleep_sec = $set_cached_sec*2;
				$sleep_usec = round(pow(10,6)*$sleep_sec);
				if ($print_debug) $app->print_debug("Completed in ".$set_cached_sec." sec, sleeping ".$sleep_sec." sec");
				usleep($sleep_usec);
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max($loop_target_time, $loop_time*1.5);
			
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
