<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/jsonRPCClient.php");

$script_start_time = microtime(true);

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$btc_currency = get_currency_by_abbreviation('btc');
	$latest_btc_price = latest_currency_price($btc_currency['currency_id']);
	
	if (!isset($GLOBALS['currency_price_refresh_seconds'])) die('Error: please add something like $GLOBALS[\'currency_price_refresh_seconds\'] = 60; to your config file.');
	
	if (!$latest_btc_price || $latest_btc_price['time_added'] < time()-$GLOBALS['currency_price_refresh_seconds']) {
		$latest_btc_price = update_currency_price($btc_currency['currency_id']);
	}
	
	generate_open_games();
	
	$q = "SELECT * FROM games WHERE game_status='published' AND start_condition='players_joined' AND start_condition_players > 0;";
	$r = run_query($q);
	while ($unstarted_game = mysql_fetch_array($r)) {
		$num_players = paid_players_in_game($unstarted_game);
		if ($num_players >= $unstarted_game['start_condition_players']) {
			start_game($unstarted_game);
		}
	}
	
	$q = "SELECT * FROM games WHERE game_status='published' AND start_condition='fixed_time' AND start_datetime <= NOW() AND start_datetime IS NOT NULL;";
	$r = run_query($q);
	while ($unstarted_game = mysql_fetch_array($r)) {
		start_game($unstarted_game);
	}
	
	$real_game = false;
	$q = "SELECT * FROM games WHERE game_type='real';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1)	$real_game = mysql_fetch_array($r);
	
	$running_games = array();
	$q = "SELECT * FROM games WHERE game_status='running';";
	$r = run_query($q);
	while ($running_game = mysql_fetch_array($r)) {
		$running_games[count($running_games)] = $running_game;
	}
	
	if ($real_game['game_status'] == "running") {
		if ($GLOBALS['always_generate_coins']) {
			$q = "SELECT * FROM blocks WHERE game_id='".$real_game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
			$r = run_query($q);
			if (mysql_numrows($r) > 0) {
				$lastblock = mysql_fetch_array($r);
				if ($lastblock['time_created'] < time()-$GLOBALS['restart_generation_seconds']) {
					$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
					$coin_rpc->setgenerate(false);
					$coin_rpc->setgenerate(true);
					echo "Started generating coins...<br/>\n";
				}
			}
		}
	}
	
	if ($real_game && ($GLOBALS['walletnotify_by_cron'] || $GLOBALS['min_unallocated_addresses'] > 0)) {
		$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
	}
	
	if ($real_game && $GLOBALS['min_unallocated_addresses'] > 0) {
		$need_addresses = false;
		$q = "SELECT * FROM game_voting_options WHERE game_id='".$real_game['game_id']."' ORDER BY option_id ASC;";
		$r = run_query($q);
		while ($option = mysql_fetch_array($r)) {
			$qq = "SELECT COUNT(*) FROM addresses WHERE game_id='".$real_game['game_id']."' AND option_id='".$option['option_id']."' AND user_id IS NULL;";
			$rr = run_query($qq);
			$num_addr = mysql_fetch_row($rr);
			$num_addr = $num_addr[0];
			if ($num_addr < $GLOBALS['min_unallocated_addresses']) {
				echo "Add ".($GLOBALS['min_unallocated_addresses']-$num_addr)." for ".$option['name']."<br/>\n";
				for ($i=0; $i<($GLOBALS['min_unallocated_addresses']-$num_addr); $i++) {
					$new_addr_str = $coin_rpc->getnewvotingaddress($option['name']);
					$new_addr_db = create_or_fetch_address($real_game, $new_addr_str, false, $coin_rpc, true);
				}
			}
		}
		
		echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
	}
	
	if (count($running_games) > 0) {
		try {
			$seconds_to_sleep = 5;
			do {
				for ($game_i=0; $game_i<count($running_games); $game_i++) {
					if ($GLOBALS['walletnotify_by_cron'] && $running_games[$game_i]['game_type'] == "real") {
						echo apply_user_strategies($running_games[$game_i]);
						echo walletnotify($running_games[$game_i], $coin_rpc, "");
						update_option_scores($running_games[$game_i]);
					}
					if ($running_games[$game_i]['game_type'] == "simulation") {
						$last_block_id = last_block_id($running_games[$game_i]['game_id']);
						
						$num = rand(0, round($running_games[$game_i]['seconds_per_block']/$seconds_to_sleep)-1);
						if ($_REQUEST['force_new_block'] == "1") $num = 0;
						
						if ($num == 0) {
							echo new_block($running_games[$game_i]['game_id']);
						}
						else {
							echo $running_games[$game_i]['name'].": No block (".$num." vs ".$running_games[$game_i]['seconds_per_block']/$seconds_to_sleep.")<br/>\n";
						}
					}
					
					apply_user_strategies($running_games[$game_i]);
				}
				sleep($seconds_to_sleep);
			} while (microtime(true) < $script_start_time + (60-$seconds_to_sleep));
		}
		catch (Exception $e) {
			die("An error occurred when attempting a coin RPC call.");
		}
	}
	
	$runtime_sec = microtime(true)-$script_start_time;
	$sec_until_refresh = round(60-$runtime_sec);
	if ($sec_until_refresh < 0) $sec_until_refresh = 0;
	
	echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
	echo "Script ran for ".round($runtime_sec, 2)." seconds.<br/>\n";
	/*
	$q = "UPDATE users SET logged_in=0 WHERE last_active<".(time()-60*2).";";
	$r = run_query($q);
	*/
}
else echo "Error: permission denied.";
?>