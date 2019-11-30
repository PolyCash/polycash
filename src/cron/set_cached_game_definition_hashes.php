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
	
	$process_lock_name = "set_cached_game_definition";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked || $only_game_id) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$script_target_time = 549;
		$loop_target_time = 30;
		$blockchains = [];
		$running_games = [];
		
		$game_q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE ";
		if ($only_game_id) $game_q .= "g.game_id=".$only_game_id;
		else $game_q .= "b.online=1 AND g.game_status IN('published','running')";
		$db_running_games = $app->run_query($game_q);
		
		while ($running_game = $db_running_games->fetch()) {
			$game_i = count($running_games);
			if (empty($blockchains[$running_game['blockchain_id']])) $blockchains[$running_game['blockchain_id']] = new Blockchain($app, $running_game['blockchain_id']);
			$running_games[$game_i] = new Game($blockchains[$running_game['blockchain_id']], $running_game['game_id']);
			if ($print_debug) echo "Including game: ".$running_game['name']."\n";
		}
		
		do {
			$loop_start_time = microtime(true);
			
			foreach ($running_games as $running_game) {
				$running_game->set_cached_definition_hashes();
				if ($print_debug) echo "Set ".$running_game->db_game['name']." at ".round(microtime(true)-$loop_start_time, 8)."\n";
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max($loop_target_time, $loop_time*5);
			
			if ($loop_time < $loop_target_time) {
				$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
				if ($print_debug) {
					echo "Sleeping ".round($loop_target_time - $loop_time, 2)." sec\n";
					$app->flush_buffers();
				}
				usleep($sleep_usec);
			}
		}
		while (microtime(true)-$script_start_time < $script_target_time);
	}
	else echo "This process is already running.\n";
}
else echo "Error: please supply the right key\n";
?>
