<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/jsonRPCClient.php");

$script_start_time = microtime(true);

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$btc_currency = get_currency_by_abbreviation('btc');
	$latest_btc_price = latest_currency_price($btc_currency['currency_id']);
	
	if (!$latest_btc_price || $latest_btc_price['time_added'] < time()-$GLOBALS['currency_price_refresh_seconds']) {
		$latest_btc_price = update_currency_price($btc_currency['currency_id']);
	}
	
	$q = "SELECT * FROM games WHERE game_status='published' AND start_condition='players_joined';";
	$r = run_query($q);
	while ($unstarted_game = mysql_fetch_array($r)) {
		$num_players = paid_players_in_game($unstarted_game);
		if ($num_players >= $unstarted_game['start_condition_players']) {
			start_game($unstarted_game);
		}
	}
	
	$q = "SELECT * FROM games WHERE game_status='published' AND start_condition='fixed_time' AND start_datetime <= NOW();";
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
					$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
					$empirecoin_rpc->setgenerate(false);
					$empirecoin_rpc->setgenerate(true);
					echo "Started generating coins...<br/>\n";
				}
			}
		}
	}
	
	if ($real_game && ($GLOBALS['walletnotify_by_cron'] || $GLOBALS['min_unallocated_addresses'] > 0)) {
		$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
	}
	
	if ($real_game && $GLOBALS['min_unallocated_addresses'] > 0) {
		$need_addresses = false;
		$q = "SELECT * FROM nations ORDER BY nation_id ASC;";
		$r = run_query($q);
		while ($nation = mysql_fetch_array($r)) {
			$qq = "SELECT COUNT(*) FROM addresses WHERE game_id='".$real_game['game_id']."' AND nation_id='".$nation['nation_id']."' AND user_id IS NULL;";
			$rr = run_query($qq);
			$num_addr = mysql_fetch_row($rr);
			$num_addr = $num_addr[0];
			if ($num_addr < $GLOBALS['min_unallocated_addresses']) {
				echo "Add ".($GLOBALS['min_unallocated_addresses']-$num_addr)." for ".$nation['name']."<br/>\n";
				for ($i=0; $i<($GLOBALS['min_unallocated_addresses']-$num_addr); $i++) {
					$new_addr_str = $empirecoin_rpc->getnewvotingaddress($nation['name']);
					$new_addr_db = create_or_fetch_address($real_game, $new_addr_str, false, $empirecoin_rpc, true);
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
						echo walletnotify($running_games[$game_i], $empirecoin_rpc, "");
						update_nation_scores($running_games[$game_i]);
					}
					if ($running_games[$game_i]['game_type'] == "simulation") {
						$last_block_id = last_block_id($running_games[$game_i]['game_id']);
						
						$num = rand(0, round($running_games[$game_i]['seconds_per_block']/$seconds_to_sleep)-1);
						if ($_REQUEST['force_new_block'] == "1") $num = 0;
						
						if ($num == 0) {
							echo new_block($running_games[$game_i]['game_id']);
						}
						else {
							echo "No block (".$num." vs ".$running_games[$game_i]['seconds_per_block']/$seconds_to_sleep.")<br/>\n";
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
	
	echo "Script ran for ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
	/*
	$q = "UPDATE users SET logged_in=0 WHERE last_active<".(time()-60*2).";";
	$r = run_query($q);
	*/
}
else echo "Error: permission denied.";
?>