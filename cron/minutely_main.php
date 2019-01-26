<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

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
		
		$blockchains = array();
		$real_games = array();
		$coin_rpcs = array();
		$game_id2real_game_i = array();
		$game_id2private_game_i = array();
		$private_blockchain_ids = array();
		$public_blockchain_ids = array();

		// If block hashing hasn't run for a long time on private blockchain, add some blocks
		if (!empty($GLOBALS['mine_private_blocks_when_offline'])) {
			$q = "SELECT * FROM blockchains WHERE online=1 AND p2p_mode='none' AND last_hash_time IS NOT NULL AND (".time()."-last_hash_time) > (seconds_per_block*2);";
			$r = $GLOBALS['app']->run_query($q);
			$log_text = "";
			
			while ($db_blockchain = $r->fetch()) {
				if (empty($blockchains[$db_blockchain['blockchain_id']])) $blockchains[$db_blockchain['blockchain_id']] = new Blockchain($app, $db_blockchain['blockchain_id']);
				
				$seconds_to_add = time()-$db_blockchain['last_hash_time'];
				$blocks_to_add = round($seconds_to_add/$db_blockchain['seconds_per_block']);
				if ($print_debug) echo "adding $blocks_to_add\n";
				
				for ($i=0; $i<$blocks_to_add; $i++) {
					$created_block_id = $blockchains[$db_blockchain['blockchain_id']]->new_block($log_text);
					$sim_hash_time = $db_blockchain['last_hash_time']+round(($i/$blocks_to_add)*$seconds_to_add);
					$blockchains[$db_blockchain['blockchain_id']]->set_last_hash_time($sim_hash_time);
				}
			}
		}
		
		// Initial load of all online games & blockchains
		$q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1;";
		$r = $GLOBALS['app']->run_query($q);
		$real_game_i = 0;
		$private_game_i = 0;

		while ($db_game = $r->fetch()) {
			if (empty($blockchains[$db_game['blockchain_id']])) {
				$blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
				
				if ($db_game['p2p_mode'] == "rpc") array_push($public_blockchain_ids, $db_game['blockchain_id']);
				else array_push($private_blockchain_ids, $db_game['blockchain_id']);
			}
			
			if ($db_game['p2p_mode'] == "rpc") {
				$game_id2real_game_i[$db_game['game_id']] = $real_game_i;
				$real_games[$real_game_i] = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
				
				try {
					$coin_rpcs[$real_game_i] = new jsonRPCClient('http://'.$db_game['rpc_username'].':'.$db_game['rpc_password'].'@127.0.0.1:'.$db_game['rpc_port'].'/');
					$getblockchaininfo = $coin_rpcs[$real_game_i]->getblockchaininfo();
				}
				catch (Exception $e) {
					$coin_rpcs[$real_game_i] = false;
				}
				
				if ($coin_rpcs[$real_game_i]) {
					$qq = "UPDATE blockchains SET rpc_last_time_connected='".time()."', block_height='".$getblockchaininfo['headers']."' WHERE blockchain_id='".$db_game['blockchain_id']."';";
					$rr = $app->run_query($qq);
				}
				$real_game_i++;
			}
			else {
				$game_id2private_game_i[$db_game['game_id']] = $private_game_i;
				$private_games[$private_game_i] = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
				$private_game_i++;
			}
		}

		$q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_status='published' AND g.start_condition='players_joined' AND g.start_condition_players > 0 AND b.online=1;";
		$r = $app->run_query($q);
		
		while ($db_unstarted_game = $r->fetch()) {
			if (!$blockchains[$db_unstarted_game['blockchain_id']]) $blockchains[$db_unstarted_game['blockchain_id']] = new Blockchain($app, $db_unstarted_game['blockchain_id']);
			$unstarted_game = new Game($blockchains[$db_unstarted_game['blockchain_id']], $db_unstarted_game['game_id']);
			$num_players = $unstarted_game->paid_players_in_game();
			if ($num_players >= $unstarted_game->db_game['start_condition_players']) {
				$unstarted_game->start_game();
			}
		}

		$q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1 AND g.game_status='published' AND g.start_condition='fixed_time' AND g.start_datetime <= NOW() AND g.start_datetime IS NOT NULL;";
		$r = $app->run_query($q);
		
		while ($db_unstarted_game = $r->fetch()) {
			if (time() >= strtotime($db_unstarted_game['start_datetime'])) {
				if (!$blockchains[$db_unstarted_game['blockchain_id']]) $blockchains[$db_unstarted_game['blockchain_id']] = new Blockchain($app, $db_unstarted_game['blockchain_id']);
				$unstarted_game = new Game($blockchains[$db_unstarted_game['blockchain_id']], $db_unstarted_game['game_id']);
				$unstarted_game->start_game();
			}
		}

		if ($GLOBALS['outbound_email_enabled']) {
			$q = "SELECT *, TIME_TO_SEC(TIMEDIFF(NOW(), completion_datetime)) AS sec_since_completion FROM games WHERE giveaway_status IN ('public_pay','invite_pay') AND game_status='completed' AND payout_complete=0 AND (payout_reminder_datetime < DATE_SUB(NOW(), INTERVAL 30 MINUTE) OR payout_reminder_datetime IS NULL);";
			$r = $app->run_query($q);

			while ($completed_game = $r->fetch()) {
				$qq = "UPDATE games SET payout_reminder_datetime=NOW() WHERE game_id='".$completed_game['game_id']."';";
				$rr = $app->run_query($qq);
				
				$subject = $completed_game['name']." has finished, please process payouts.";
				$message = "This game finished ".$app->format_seconds($completed_game['sec_since_completion'])." ago. Please log in with your admin account and follow this link to complete the payout: ".$GLOBALS['base_url']."/payout_game.php?game_id=".$completed_game['game_id'];
				$app->mail_async($GLOBALS['rsa_keyholder_email'], $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "", "");
			}
		}

		// Load all running games
		$running_games = array();
		$q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1 AND g.game_status IN('published','running');";
		$r = $GLOBALS['app']->run_query($q);
		
		while ($running_game = $r->fetch()) {
			$game_i = count($running_games);
			if (empty($blockchains[$running_game['blockchain_id']])) $blockchains[$running_game['blockchain_id']] = new Blockchain($app, $running_game['blockchain_id']);
			$running_games[$game_i] = new Game($blockchains[$running_game['blockchain_id']], $running_game['game_id']);
			if ($print_debug) echo "Including game: ".$running_game['name']."\n";
			
			// Update user account values
			$qq = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$running_game['game_id']."' ORDER BY u.user_id ASC;";
			$rr = $app->run_query($qq);
			
			while ($user_game = $rr->fetch()) {
				$user = new User($app, $user_game['user_id']);
				$user_pending_bets = $running_games[$game_i]->user_pending_bets($user_game);
				$account_value = ($running_games[$game_i]->account_balance($user_game['account_id'])+$user_pending_bets)/pow(10,$running_games[$game_i]->db_game['decimal_places']);
				
				$qqq = "UPDATE user_games SET account_value='".$account_value."' WHERE user_game_id='".$user_game['user_game_id']."';";
				$rrr = $app->run_query($qqq);
			}
		}
		
		$unconf_message = $app->delete_unconfirmable_transactions();
		if ($print_debug) echo $unconf_message."\n";
		
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
						
						if ($print_debug) {
							echo $txt;
							echo (microtime(true)-$benchmark_time)." sec\n";
						}
						$benchmark_time = microtime(true);
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
				die("An error occurred when attempting a coin RPC call.\n");
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