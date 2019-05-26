<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$show_internal_params = false;
	$script_target_time = 54;
	$cache_expiry_time = 30;
	$loop_target_time = 10;
	$blockchains = [];
	$running_games = [];
	
	$running_game_r = $app->run_query("SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1 AND g.game_status IN('published','running');");
	
	while ($running_game = $running_game_r->fetch()) {
		$game_i = count($running_games);
		if (empty($blockchains[$running_game['blockchain_id']])) $blockchains[$running_game['blockchain_id']] = new Blockchain($app, $running_game['blockchain_id']);
		$running_games[$game_i] = new Game($blockchains[$running_game['blockchain_id']], $running_game['game_id']);
		if ($print_debug) echo "Including game: ".$running_game['name']."\n";
	}
	
	do {
		$loop_start_time = microtime(true);
		
		foreach ($running_games as $running_game) {
			$actual_game_def = $app->fetch_game_definition($running_game, "actual", $show_internal_params);
			$actual_game_def_str = $app->game_def_to_text($actual_game_def);
			$actual_game_def_hash = $app->game_def_to_hash($actual_game_def_str);
			
			if ($running_game->db_game['cached_definition_hash'] != $actual_game_def_hash) {
				$app->run_query("UPDATE games SET cached_definition_hash='".$actual_game_def_hash."', cached_definition_time='".time()."' WHERE game_id='".$running_game->db_game['game_id']."';");
				$running_game->db_game['cached_definition_hash'] = $actual_game_def_hash;
			}
			
			$defined_game_def = $app->fetch_game_definition($running_game, "defined", $show_internal_params);
			$defined_game_def_str = $app->game_def_to_text($defined_game_def);
			$defined_game_def_hash = $app->game_def_to_hash($defined_game_def_str);
			
			if ($running_game->db_game['defined_cached_definition_hash'] != $defined_game_def_hash) {
				$app->run_query("UPDATE games SET defined_cached_definition_hash='".$defined_game_def_hash."' WHERE game_id='".$running_game->db_game['game_id']."';");
				$running_game->db_game['defined_cached_definition_hash'] = $defined_game_def_hash;
			}
		}
		
		$loop_stop_time = microtime(true);
		$loop_time = $loop_stop_time-$loop_start_time;
		$loop_target_time = max($loop_target_time, $loop_time*4);
		
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
else echo "Error: please supply the right key\n";
?>
