<?php
$payments_pid = pcntl_fork();

if ($payments_pid == -1) {
	die('Failed to fork this process.');
}
else if ($payments_pid) {
	include("minutely_check_payments.php");
	die();
}
else {
	$fetchprice_pid = pcntl_fork();

	if ($fetchprice_pid == -1) {
		die('Failed to fork this process.');
	}

	$host_not_required = TRUE;
	include(realpath(dirname(__FILE__))."/../includes/connect.php");

	$script_start_time = microtime(true);

	if ($argv) $_REQUEST['key'] = $argv[1];

	if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
		$btc_currency = $app->get_currency_by_abbreviation('btc');
		$latest_btc_price = $app->latest_currency_price($btc_currency['currency_id']);
	
		if (!isset($GLOBALS['currency_price_refresh_seconds'])) die('Error: please add something like $GLOBALS[\'currency_price_refresh_seconds\'] = 60; to your config file.');
	
		if (!$fetchprice_pid && (!$latest_btc_price || $latest_btc_price['time_added'] < time()-$GLOBALS['currency_price_refresh_seconds'])) {
			$app->update_all_currency_prices();
			die();
		}
	
		echo "Done fetching currency prices at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";

		$app->generate_open_games();
	
		$q = "SELECT * FROM games WHERE game_status='published' AND start_condition='players_joined' AND start_condition_players > 0;";
		$r = $app->run_query($q);
		while ($db_unstarted_game = $r->fetch()) {
			$unstarted_game = new Game($app, $db_unstarted_game['game_id']);
			$num_players = $unstarted_game->paid_players_in_game();
			if ($num_players >= $unstarted_game->db_game['start_condition_players']) {
				$unstarted_game->start_game();
			}
		}
	
		$q = "SELECT * FROM games WHERE game_status='published' AND start_condition='fixed_time' AND start_datetime <= NOW() AND start_datetime IS NOT NULL;";
		$r = $app->run_query($q);
		while ($db_unstarted_game = $r->fetch()) {
			$unstarted_game = new Game($app, $db_unstarted_game['game_id']);
			$unstarted_game->start_game();
		}
	
		if ($GLOBALS['outbound_email_enabled']) {
			$q = "SELECT *, TIME_TO_SEC(TIMEDIFF(NOW(), completion_datetime)) AS sec_since_completion FROM games WHERE giveaway_status IN ('public_pay','invite_pay') AND game_status='completed' AND payout_complete=0 AND (payout_reminder_datetime < DATE_SUB(NOW(), INTERVAL 30 MINUTE) OR payout_reminder_datetime IS NULL);";
			$r = $app->run_query($q);
		
			while ($completed_game = $r->fetch()) {
				$qq = "UPDATE games SET payout_reminder_datetime=NOW() WHERE game_id='".$completed_game['game_id']."';";
				$rr = $app->run_query($qq);
			
				$subject = $completed_game['name']." has finished, please process payouts.";
				$message = "This game finished ".$app->format_seconds($completed_game['sec_since_completion'])." ago. Please log in with your admin account and follow this link to complete the payout: ".$GLOBALS['base_url']."/payout_game.php?game_id=".$completed_game['game_id'];
				$app->mail_async($GLOBALS['rsa_keyholder_email'], $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
			}
		}
	
		$coin_rpc = false;
	
		$q = "SELECT * FROM games WHERE game_type='real';";
		$r = $app->run_query($q);
	
		if ($r->rowCount() == 1)	{
			$db_real_game = $r->fetch();
			$real_game = new Game($app, $db_real_game['game_id']);
		
			$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
		}
	
		$running_games = array();
		$q = "SELECT * FROM games WHERE game_status='running';";
		$r = $app->run_query($q);
		while ($running_game = $r->fetch()) {
			$running_games[count($running_games)] = new Game($app, $running_game['game_id']);
			echo "Including game: ".$running_game['name']."<br/>\n";
		}
	
		if ($real_game->db_game['game_status'] == "running") {
			if ($GLOBALS['always_generate_coins']) {
				$q = "SELECT * FROM blocks WHERE game_id='".$real_game->db_game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
				$r = $app->run_query($q);
			
				if ($r->rowCount() > 0) {
					$lastblock = $r->fetch();
				
					if ($lastblock['time_created'] < time()-$GLOBALS['restart_generation_seconds']) {
						$coin_rpc->setgenerate(false);
						$coin_rpc->setgenerate(true);
						echo "Started generating coins...<br/>\n";
					}
				}
			}
		}
	
		if ($real_game && $GLOBALS['min_unallocated_addresses'] > 0) {
			$need_addresses = false;
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$real_game->db_game['game_id']."' ORDER BY option_id ASC;";
			$r = $app->run_query($q);
			while ($option = $r->fetch()) {
				$qq = "SELECT * FROM addresses WHERE game_id='".$real_game->db_game['game_id']."' AND option_id='".$option['option_id']."' AND user_id IS NULL;";
				$rr = $app->run_query($qq);
				$num_addr = $rr->rowCount();
			
				if ($num_addr < $GLOBALS['min_unallocated_addresses']) {
					echo "Generate ".($GLOBALS['min_unallocated_addresses']-$num_addr)." unallocated ".$option['name']." addresses in \"".$real_game->db_game['name']."\"<br/>\n";
					for ($i=0; $i<($GLOBALS['min_unallocated_addresses']-$num_addr); $i++) {
						$new_addr_str = $coin_rpc->getnewvotingaddress($option['name']);
						$new_addr_db = $real_game->create_or_fetch_address($new_addr_str, false, $coin_rpc, true, false);
					}
				}
			}
		
			echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
		}
	
		if (count($running_games) > 0) {
			try {
				$seconds_to_sleep = 5;
				do {
					$loop_start_time = microtime(true);
				
					for ($game_i=0; $game_i<count($running_games); $game_i++) {
						if ($GLOBALS['walletnotify_by_cron'] && $running_games[$game_i]->db_game['game_type'] == "real") {
							echo $running_games[$game_i]->sync_coind($coin_rpc);
							$running_games[$game_i]->update_option_scores();
						}
						if ($running_games[$game_i]->db_game['game_type'] == "simulation") {
							$last_block_id = $running_games[$game_i]->last_block_id();
						
							$block_prob = min(1, round($seconds_to_sleep/$running_games[$game_i]->db_game['seconds_per_block'], 4));
							$rand_num = rand(0, pow(10,4))/pow(10,4);
							if (!empty($_REQUEST['force_new_block'])) $rand_num = 0;
						
							echo $running_games[$game_i]->db_game['name']." (".$rand_num." vs ".$block_prob."): ";
							if ($rand_num <= $block_prob) {
								echo $running_games[$game_i]->new_block();
							}
							else {
								echo "No block<br/>\n";
							}
						}
					
						echo $running_games[$game_i]->apply_user_strategies();
					}
					$loop_stop_time = microtime(true);
				
					$sleep_time = $seconds_to_sleep - ($loop_stop_time - $loop_start_time);
					if ($sleep_time > 0) sleep($sleep_time);
				} while (microtime(true) < $script_start_time + (60-$seconds_to_sleep));
			}
			catch (Exception $e) {
				die("An error occurred when attempting a coin RPC call.\n");
			}
		}
	
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round(60-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
	
		echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
		echo "Script ran for ".round($runtime_sec, 2)." seconds.<br/>\n";
		/*
		$q = "UPDATE users SET logged_in=0 WHERE last_active<".(time()-60*2).";";
		$r = $app->run_query($q);
		*/
	}
	else echo "Error: permission denied.";
}
?>
