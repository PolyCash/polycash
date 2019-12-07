<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 174;
$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "mine_blocks";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$unstarted_games = $app->run_query("SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_status='published' AND g.start_condition='players_joined' AND g.start_condition_players > 0 AND b.online=1;");
		
		while ($db_unstarted_game = $unstarted_games->fetch()) {
			if (!$blockchains[$db_unstarted_game['blockchain_id']]) $blockchains[$db_unstarted_game['blockchain_id']] = new Blockchain($app, $db_unstarted_game['blockchain_id']);
			$unstarted_game = new Game($blockchains[$db_unstarted_game['blockchain_id']], $db_unstarted_game['game_id']);
			$num_players = $unstarted_game->paid_players_in_game();
			if ($num_players >= $unstarted_game->db_game['start_condition_players']) {
				$unstarted_game->start_game();
			}
		}

		$unstarted_games = $app->run_query("SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1 AND g.game_status='published' AND g.start_condition='fixed_time' AND g.start_datetime <= NOW() AND g.start_datetime IS NOT NULL;");
		
		while ($db_unstarted_game = $unstarted_games->fetch()) {
			if (time() >= strtotime($db_unstarted_game['start_datetime'])) {
				if (!$blockchains[$db_unstarted_game['blockchain_id']]) $blockchains[$db_unstarted_game['blockchain_id']] = new Blockchain($app, $db_unstarted_game['blockchain_id']);
				$unstarted_game = new Game($blockchains[$db_unstarted_game['blockchain_id']], $db_unstarted_game['game_id']);
				$unstarted_game->start_game();
			}
		}
		
		$online_blockchains = $app->run_query("SELECT * FROM blockchains WHERE online=1 AND p2p_mode='rpc';");
		while ($db_online_blockchain = $online_blockchains->fetch()) {
			$online_blockchain = new Blockchain($app, $db_online_blockchain['blockchain_id']);
			$online_blockchain->load_coin_rpc();
			
			if ($online_blockchain->coin_rpc) {
				$getblockchaininfo = $online_blockchain->coin_rpc->getblockchaininfo();
				
				if (!empty($getblockchaininfo['headers'])) {
					$app->run_query("UPDATE blockchains SET rpc_last_time_connected=:current_time, block_height=:block_height WHERE blockchain_id=:blockchain_id;", [
						'current_time' => time(),
						'block_height' => $getblockchaininfo['headers'],
						'blockchain_id' => $online_blockchain->db_blockchain['blockchain_id']
					]);
				}
			}
		}
		
		$loop_target_time = 5;
		do {
			$loop_start_time = microtime(true);
			
			$mineable_blockchains = $app->run_query("SELECT * FROM blockchains WHERE online=1 AND p2p_mode='none';");
			
			while ($db_mineable_blockchain = $mineable_blockchains->fetch()) {
				$mineable_blockchain = new Blockchain($app, $db_mineable_blockchain['blockchain_id']);
				
				if ($mineable_blockchain->last_block_id()%10 == 0) $mineable_blockchain->set_average_seconds_per_block(false);
				
				$speedup_factor = $mineable_blockchain->seconds_per_block('average')/$mineable_blockchain->seconds_per_block('target');
				$remaining_prob = round($speedup_factor*$loop_target_time/$mineable_blockchain->seconds_per_block('target'), 4);
				
				do {
					$benchmark_time = microtime(true);
					
					$last_block_id = $mineable_blockchain->last_block_id();
					
					$block_prob = min(1, $remaining_prob);
					$remaining_prob = $remaining_prob-$block_prob;
					$rand_num = rand(0, pow(10,4))/pow(10,4);
					if (!empty($_REQUEST['force_new_block'])) $rand_num = 0;
					
					if ($print_debug) echo "\n".$mineable_blockchain->db_blockchain['blockchain_name']." (".$rand_num." vs ".$block_prob."): ";
					
					if ($rand_num <= $block_prob) {
						if ($print_debug) echo "FOUND A BLOCK!!\n";
						$txt = "";
						$mineable_blockchain->new_block($txt);
						if ($print_debug) echo $txt."\n";
					}
					else if ($print_debug) echo "No block\n";
					
					if ($print_debug) $app->flush_buffers();
					
					$benchmark_time = microtime(true);
				}
				while ($remaining_prob > 0);
				
				$mineable_blockchain->set_last_hash_time(time());
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			
			if ($print_debug) {
				echo "Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n";
				$app->flush_buffers();
			}
			
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
		
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_target_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		
		if ($print_debug) echo "Script ran for ".round($runtime_sec, 2)." seconds.\n";
	}
	else echo "Skipped starting the game loop; it's already running.\n";
}
else echo "Error: incorrect key supplied in cron/minutely_main.php\n";
?>