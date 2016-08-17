<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$address_miner_running = (int) $app->get_site_constant("address_miner_running");
	
	if ($address_miner_running == 0) {
		$GLOBALS['app'] = $app;
		$GLOBALS['shutdown_lock_name'] = "address_miner_running";
		$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
		register_shutdown_function("script_shutdown");
		
		$real_game_types = array();
		$coin_rpcs = array();
		$game_id2real_game_i = array();
		
		$q = "SELECT * FROM games WHERE min_unallocated_addresses > 0 AND game_status IN ('published','running');";
		$r = $app->run_query($q);
		echo "Looping through ".$r->rowCount()." games.<br/>\n";
		
		while ($db_game = $r->fetch()) {
			$game = new Game($app, $db_game['game_id']);
			
			if ($game->db_game['min_unallocated_addresses'] > 0) {
				if (!empty($game->db_game['rpc_username'])) {
					try {
						$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');
					}
					catch (Exception $e) {
						$coin_rpc = false;
					}
				}
				else $coin_rpc = false;
				
				$game->generate_voting_addresses($coin_rpc);
			}
		}
		
		echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
	}
}
else echo "Error: incorrect key supplied in cron/address_miner.php\n";
?>
