<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
if ($GLOBALS['process_lock_method'] == "db") {
	include(realpath(dirname(dirname(__FILE__)))."/includes/handle_script_shutdown.php");
}
$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$address_miner_running = $app->check_process_running("address_miner_running");
	
	if (!$address_miner_running) {
		if ($GLOBALS['process_lock_method'] == "db") {
			$GLOBALS['app'] = $app;
			$GLOBALS['shutdown_lock_name'] = "address_miner_running";
			$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
			register_shutdown_function("script_shutdown");
		}
		
		$blockchains = array();
		
		$q = "SELECT * FROM games WHERE min_unallocated_addresses > 0 AND game_status IN ('published','running');";
		$r = $app->run_query($q);
		echo "Looping through ".$r->rowCount()." games.<br/>\n";
		
		while ($db_game = $r->fetch()) {
			if (empty($blockchains[$db_game['blockchain_id']])) $blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
			
			if ($game->db_game['min_unallocated_addresses'] > 0) {
				if (!empty($blockchains[$db_game['blockchain_id']]->db_blockchain['rpc_username'])) {
					try {
						$coin_rpc = new jsonRPCClient('http://'.$blockchains[$db_game['blockchain_id']]->db_blockchain['rpc_username'].':'.$blockchains[$db_game['blockchain_id']]->db_blockchain['rpc_password'].'@127.0.0.1:'.$blockchains[$db_game['blockchain_id']]->db_blockchain['rpc_port'].'/');
					}
					catch (Exception $e) {
						$coin_rpc = false;
					}
				}
				else $coin_rpc = false;
				
				$game->generate_voting_addresses($coin_rpc, false);
			}
		}
		
		echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.\n";
	}
	else echo "Address miner is already running.\n";
}
else echo "Error: incorrect key supplied in cron/address_miner.php\n";
?>
