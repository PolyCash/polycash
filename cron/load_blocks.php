<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/handle_script_shutdown.php");
$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (!empty($_REQUEST['key']) && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$loading_blocks = (int) $app->get_site_constant("loading_blocks");
	
	if ($loading_blocks == 0) {
		$GLOBALS['app'] = $app;
		$GLOBALS['shutdown_lock_name'] = "loading_blocks";
		$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
		register_shutdown_function("script_shutdown");
		
		$real_game_q = "SELECT * FROM games WHERE p2p_mode='rpc' AND game_status IN ('published','running');";
		$real_game_r = $GLOBALS['app']->run_query($real_game_q);
		
		while ($real_game = $real_game_r->fetch()) {
			$real_game_obj = new Game($app, $real_game['game_id']);
			try {
				$coin_rpc = new jsonRPCClient('http://'.$real_game['rpc_username'].':'.$real_game['rpc_password'].'@127.0.0.1:'.$real_game['rpc_port'].'/');
				echo "Loading new blocks...\n";
				$real_game_obj->load_new_blocks($coin_rpc);
				echo "Loading game block headers...\n";
				$real_game_obj->load_all_block_headers($coin_rpc, TRUE);
				echo "Loading game blocks...\n";
				$real_game_obj->load_all_blocks($coin_rpc, TRUE);
				echo "Loading all block headers...\n";
				$real_game_obj->load_all_block_headers($coin_rpc, FALSE);
				echo "Loading all blocks...\n";
				$real_game_obj->load_all_blocks($coin_rpc, FALSE);
			} catch (Exception $e) {
				echo "Error, skipped ".$real_game['name']." because RPC connection failed.<br/>\n";
			}
		}
	}
	else echo "Block loading script is already running, skip...\n";
}
else echo "Please supply the correct key.\n";
?>
