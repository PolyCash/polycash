<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/handle_script_shutdown.php");

$script_target_time = 120;
$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (!empty($_REQUEST['key']) && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$main_loop_running = (int) $app->get_site_constant("main_loop_running");
	
	if ($main_loop_running == 0) {
		$GLOBALS['app'] = $app;
		$GLOBALS['shutdown_lock_name'] = "main_loop_running";
		$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
		register_shutdown_function("script_shutdown");
		
		$real_games = array();
		$coin_rpcs = array();
		$game_id2real_game_i = array();

		$q = "SELECT * FROM games WHERE game_type='real';";
		$r = $GLOBALS['app']->run_query($q);
		$real_game_i = 0;

		while ($db_real_game = $r->fetch()) {
			$game_id2real_game_i[$db_real_game['game_id']] = $real_game_i;
			$real_games[$real_game_i] = new Game($app, $db_real_game['game_id']);
			$coin_rpcs[$real_game_i] = new jsonRPCClient('http://'.$db_real_game['rpc_username'].':'.$db_real_game['rpc_password'].'@127.0.0.1:'.$db_real_game['rpc_port'].'/');
			$real_game_i++;
		}

		for ($real_game_i=0; $real_game_i<count($real_games); $real_game_i++) {
			if ($real_games[$real_game_i]->db_game['game_status'] == "running" && $real_games[$real_game_i]->db_game['always_generate_coins'] == 1) {
				$q = "SELECT * FROM blocks WHERE game_id='".$real_games[$real_game_i]->db_game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
				$r = $app->run_query($q);

				if ($r->rowCount() > 0) {
					$lastblock = $r->fetch();
	
					if ($lastblock['time_created'] < time()-$real_games[$real_game_i]->db_game['restart_generation_seconds']) {
						$coin_rpcs[$real_game_i]->setgenerate(false);
						$coin_rpcs[$real_game_i]->setgenerate(true);
						echo "Started generating coins for ".$real_games[$real_game_i]->db_game['name']."...<br/>\n";
					}
				}
			}
		}
		
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

		$running_games = array();
		$q = "SELECT * FROM games WHERE game_status='running';";
		$r = $GLOBALS['app']->run_query($q);
		while ($running_game = $r->fetch()) {
			$running_games[count($running_games)] = new Game($app, $running_game['game_id']);
			echo "Including game: ".$running_game['name']."<br/>\n";
		}
		
		if (count($running_games) > 0) {
			try {
				$seconds_to_sleep = 5;
				do {
					$loop_start_time = microtime(true);

					for ($running_game_i=0; $running_game_i<count($running_games); $running_game_i++) {
						if ($running_games[$running_game_i]->db_game['sync_coind_by_cron'] == 1 && $running_games[$running_game_i]->db_game['game_type'] == "real") {
							$real_game_i = $game_id2real_game_i[$running_games[$running_game_i]->db_game['game_id']];
							echo $running_games[$running_game_i]->sync_coind($coin_rpcs[$real_game_i]);
						}
						if ($running_games[$running_game_i]->db_game['game_type'] == "simulation") {
							$last_block_id = $running_games[$running_game_i]->last_block_id();
				
							$block_prob = min(1, round($seconds_to_sleep/$running_games[$running_game_i]->db_game['seconds_per_block'], 4));
							$rand_num = rand(0, pow(10,4))/pow(10,4);
							if (!empty($_REQUEST['force_new_block'])) $rand_num = 0;
					
							echo $running_games[$running_game_i]->db_game['name']." (".$rand_num." vs ".$block_prob."): ";
							if ($rand_num <= $block_prob) {
								echo $running_games[$running_game_i]->new_block();
							}
							else {
								echo "No block<br/>\n";
							}
						}
					
						echo $running_games[$running_game_i]->apply_user_strategies();
					}
					$loop_stop_time = microtime(true);
				
					$sleep_time = $seconds_to_sleep - ($loop_stop_time - $loop_start_time);
					if ($sleep_time > 0) sleep($sleep_time);
				}
				while (microtime(true) < $script_start_time + ($script_target_time-$seconds_to_sleep));
			}
			catch (Exception $e) {
				var_dump($e);
				die("An error occurred when attempting a coin RPC call.\n");
			}
		}

		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_target_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;

		echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
		echo "Script ran for ".round($runtime_sec, 2)." seconds.<br/>\n";
	}
	else echo "Skipped starting the game loop; it's already running.\n";
}
else echo "Error: incorrect key supplied in cron/minutely_main.php\n";
?>
