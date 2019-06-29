<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 54;
$min_loop_target_time = 5;
$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "main_loop_running";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = [];
		$real_games = [];
		$game_id2real_game_i = [];
		$game_id2private_game_i = [];
		$private_blockchain_ids = [];
		$public_blockchain_ids = [];
		
		// Initial load of all online games & blockchains
		$online_games = $app->run_query("SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1;");
		$real_game_i = 0;
		$private_game_i = 0;

		while ($db_game = $online_games->fetch()) {
			if (empty($blockchains[$db_game['blockchain_id']])) {
				$blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
				$blockchains[$db_game['blockchain_id']]->load_coin_rpc();
				
				if ($blockchains[$db_game['blockchain_id']]->coin_rpc) {
					try {
						$getblockchaininfo = $blockchains[$db_game['blockchain_id']]->coin_rpc->getblockchaininfo();
						
						$app->run_query("UPDATE blockchains SET rpc_last_time_connected=:current_time, block_height=:block_height WHERE blockchain_id=:blockchain_id;", [
							'current_time' => time(),
							'block_height' => $getblockchaininfo['headers'],
							'blockchain_id' => $db_game['blockchain_id']
						]);
					}
					catch (Exception $e) {}
				}
				
				if ($db_game['p2p_mode'] == "rpc") array_push($public_blockchain_ids, $db_game['blockchain_id']);
				else array_push($private_blockchain_ids, $db_game['blockchain_id']);
			}
			
			if ($db_game['p2p_mode'] == "rpc") {
				$game_id2real_game_i[$db_game['game_id']] = $real_game_i;
				$real_games[$real_game_i] = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
				
				$real_game_i++;
			}
			else {
				$game_id2private_game_i[$db_game['game_id']] = $private_game_i;
				$private_games[$private_game_i] = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
				$private_game_i++;
			}
		}

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

		// Load all running games
		$running_games = [];
		$db_running_games = $app->run_query("SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1 AND g.game_status IN('published','running');");
		
		while ($running_game = $db_running_games->fetch()) {
			$game_i = count($running_games);
			if (empty($blockchains[$running_game['blockchain_id']])) $blockchains[$running_game['blockchain_id']] = new Blockchain($app, $running_game['blockchain_id']);
			$running_games[$game_i] = new Game($blockchains[$running_game['blockchain_id']], $running_game['game_id']);
			if ($print_debug) {
				echo "Including game: ".$running_game['name']."\n";
				$app->flush_buffers();
			}
			
			// Update user account values
			$user_games = $app->run_query("SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id=:game_id ORDER BY u.user_id ASC;", ['game_id'=>$running_game['game_id']]);
			
			while ($user_game = $user_games->fetch()) {
				$user = new User($app, $user_game['user_id']);
				$user_pending_bets = $running_games[$game_i]->user_pending_bets($user_game);
				$account_value = ($running_games[$game_i]->account_balance($user_game['account_id'])+$user_pending_bets)/pow(10,$running_games[$game_i]->db_game['decimal_places']);
				
				$app->run_query("UPDATE user_games SET account_value=:account_value WHERE user_game_id=:user_game_id;", [
					'account_value' => $account_value,
					'user_game_id' => $user_game['user_game_id']
				]);
			}
		}
		
		if ($print_debug) {
			echo "Done setting account values. Now deleting unconfirmable transactions.\n";
		}
		
		$unconf_message = $app->delete_unconfirmable_transactions();
		if ($print_debug) {
			echo $unconf_message."\n";
			$app->flush_buffers();
		}
		
		if (count($running_games) > 0 || count($private_blockchain_ids) > 0) {
			try {
				$loop_target_time = $app->get_site_constant("loop_target_time");
				do {
					$loop_start_time = microtime(true);
					
					for ($private_blockchain_i=0; $private_blockchain_i<count($private_blockchain_ids); $private_blockchain_i++) {
						$blockchain_id = $private_blockchain_ids[$private_blockchain_i];
						
						if ($blockchains[$blockchain_id]->db_blockchain['p2p_mode'] == "none") {
							$remaining_prob = round($loop_target_time/$blockchains[$blockchain_id]->db_blockchain['seconds_per_block'], 4);
							
							do {
								$benchmark_time = microtime(true);
								
								$last_block_id = $blockchains[$blockchain_id]->last_block_id();
								
								$block_prob = min(1, $remaining_prob);
								$remaining_prob = $remaining_prob-$block_prob;
								$rand_num = rand(0, pow(10,4))/pow(10,4);
								if (!empty($_REQUEST['force_new_block'])) $rand_num = 0;
								
								if ($print_debug) echo "\n".$blockchains[$blockchain_id]->db_blockchain['blockchain_name']." (".$rand_num." vs ".$block_prob."): ";
								
								if ($rand_num <= $block_prob) {
									if ($print_debug) echo "FOUND A BLOCK!!\n";
									$txt = "";
									$blockchains[$blockchain_id]->new_block($txt);
									if ($print_debug) echo $txt."\n";
								}
								else {
									if ($print_debug) echo "No block\n";
								}
								
								if ($print_debug) echo (microtime(true)-$benchmark_time)." sec\n";
								$benchmark_time = microtime(true);
							}
							while ($remaining_prob > 0);
							
							$blockchains[$blockchain_id]->set_last_hash_time(time());
						}
					}
					
					for ($running_game_i=0; $running_game_i<count($running_games); $running_game_i++) {
						if ($print_debug) echo "\n".$running_games[$running_game_i]->db_game['name']."\n";
						
						if ($print_debug) echo "Apply user strategies...";
						$running_games[$running_game_i]->apply_user_strategies($print_debug);
						
						if (!empty($running_games[$running_game_i]->db_game['module'])) {
							if (method_exists($running_games[$running_game_i]->module, "regular_actions")) {
								$game_last_block_id = $running_games[$running_game_i]->last_block_id();
								$blockchain_last_block_id = $running_games[$running_game_i]->blockchain->last_block_id();
								
								if ($game_last_block_id == $blockchain_last_block_id) {
									$running_games[$running_game_i]->module->regular_actions($running_games[$running_game_i]);
								}
							}
						}
					}
					
					$loop_stop_time = microtime(true);
					$loop_time = $loop_stop_time-$loop_start_time;
					$loop_target_time = max($min_loop_target_time, $loop_time);
					$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
					if ($print_debug) echo "script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n";
					usleep($sleep_usec);
					$app->set_site_constant("loop_target_time", round($loop_target_time, 4));
				}
				while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
			}
			catch (Exception $e) {
				var_dump($e);
				die("An error occurred in the game loop.\n");
			}
		}

		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_target_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;

		if ($print_debug) {
			echo "</pre>";
			echo "Script ran for ".round($runtime_sec, 2)." seconds.\n";
		}
	}
	else echo "Skipped starting the game loop; it's already running.\n";
}
else echo "Error: incorrect key supplied in cron/minutely_main.php\n";
?>