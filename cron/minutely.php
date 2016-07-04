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
	
	if ($GLOBALS['walletnotify_by_cron']) {
		$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
		
		$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
		$r = run_query($q);
		$game = mysql_fetch_array($r);
		
		try {
			$seconds_to_sleep = 5;
			do {
				echo walletnotify($game, $empirecoin_rpc, "");
				sleep($seconds_to_sleep);
			} while (microtime(true) < $script_start_time + (60-$seconds_to_sleep));
		}
		catch (Exception $e) {
			die("An error occurred when attempting a coin RPC call.");
		}
	}
	/*
	$q = "UPDATE users SET logged_in=0 WHERE last_active<".(time()-60*2).";";
	$r = run_query($q);
	
	$last_block_id = last_block_id(get_site_constant('primary_game_id'));
	
	$num = rand(0, round($game['seconds_per_block']/60)-1);
	if ($_REQUEST['force_new_block'] == "1") $num = 0;
	
	if ($num == 0) {
		echo new_block(get_site_constant('primary_game_id'));
	}
	else {
		echo "No block (".$num.")<br/>";
	}
	
	// Apply user strategies
	echo apply_user_strategies($game);
	*/
}
else echo "Error: permission denied.";
?>