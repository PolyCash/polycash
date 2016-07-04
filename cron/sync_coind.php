<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$script_start_time = microtime(true);

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games WHERE game_type='real' ORDER BY game_id ASC;";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0)	{
		$db_real_game = $r->fetch();
		$real_game = new Game($app, $db_real_game['game_id']);
		$script_max_time = 60*5;
		$min_sleep_time = 5;
		
		try {
			$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
			$loops_completed = 0;
			
			do {
				$loop_start_time = microtime(true);
				$real_game->sync_coind($coin_rpc);
				$loop_stop_time = microtime(true);
				
				$loop_time = $loop_stop_time - $loop_start_time;
				$sleep_time = $loop_time*3;
				if (microtime(true) + $sleep_time > $script_start_time + $script_max_time) {
					$sleep_time = $script_start_time + $script_max_time - microtime(true);
				}
				if ($sleep_time < $min_sleep_time) $sleep_time = $min_sleep_time;
				
				echo "Loop #".$loops_completed." took ".$loop_time." sec, sleeping for ".$sleep_time." sec.<br/>\n";
				sleep($sleep_time);
				
				$loops_completed++;
			}
			while (microtime(true) + $sleep_time < $script_start_time + $script_max_time);
		}
		catch (Exception $e) {
			var_dump($e);
			die("An error occurred when attempting a coin RPC call.");
		}
		
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_max_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		
		echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
		echo "Script ran for ".round($runtime_sec, 2)." seconds.<br/>\n";
	}
}
else echo "Error: permission denied.";
?>