<?php
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/jsonRPCClient.php");

$script_start_time = microtime(true);

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	echo "Running for ".$game['name']."<br/>\n";
	
	if ($GLOBALS['always_generate_coins']) {
		$q = "SELECT * FROM blocks WHERE game_id='".$game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
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
	
	// Apply user strategies
	echo apply_user_strategies($game);
	
	if ($GLOBALS['walletnotify_by_cron'] || $GLOBALS['min_unallocated_addresses'] > 0) {
		$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
	}
	
	if ($game['game_type'] == "real" && $GLOBALS['min_unallocated_addresses'] > 0) {
		$need_addresses = false;
		$q = "SELECT * FROM nations ORDER BY nation_id ASC;";
		$r = run_query($q);
		while ($nation = mysql_fetch_array($r)) {
			$qq = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game['game_id']."' AND nation_id='".$nation['nation_id']."' AND user_id IS NULL;";
			$rr = run_query($qq);
			$num_addr = mysql_fetch_row($rr);
			$num_addr = $num_addr[0];
			if ($num_addr < $GLOBALS['min_unallocated_addresses']) {
				echo "Add ".($GLOBALS['min_unallocated_addresses']-$num_addr)." for ".$nation['name']."<br/>\n";
				for ($i=0; $i<($GLOBALS['min_unallocated_addresses']-$num_addr); $i++) {
					$new_addr_str = $empirecoin_rpc->getnewvotingaddress($nation['name']);
					$new_addr_db = create_or_fetch_address($game, $new_addr_str, false, $empirecoin_rpc, true);
				}
			}
		}
		
		echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
	}
	
	if (($GLOBALS['walletnotify_by_cron'] && $game['game_type'] == "real") || $game['game_type'] == "simulation") {
		try {
			$seconds_to_sleep = 5;
			do {
				if ($GLOBALS['walletnotify_by_cron'] && $game['game_type'] == "real") {
					echo apply_user_strategies($game);
					echo walletnotify($game, $empirecoin_rpc, "");
					update_nation_scores($game);
				}
				if ($game['game_type'] == "simulation") {
					$last_block_id = last_block_id($game['game_id']);
					
					$num = rand(0, round($game['seconds_per_block']/$seconds_to_sleep)-1);
					if ($_REQUEST['force_new_block'] == "1") $num = 0;
					
					if ($num == 0) {
						echo new_block($game['game_id']);
					}
					else {
						echo "No block (".$num." vs ".$game['seconds_per_block']/$seconds_to_sleep.")<br/>\n";
					}
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