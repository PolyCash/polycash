<?php
set_time_limit(0);
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

if (!empty($_REQUEST['key']) && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$loading_blocks = $app->check_process_running("loading_blocks");
	
	if (!$loading_blocks) {
		if ($GLOBALS['process_lock_method'] == "db") {
			$GLOBALS['app'] = $app;
			$GLOBALS['shutdown_lock_name'] = "loading_blocks";
			$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
			register_shutdown_function("script_shutdown");
		}
		
		$blockchains = array();
		
		$blockchain_q = "SELECT * FROM blockchains WHERE online=1;";
		$blockchain_r = $GLOBALS['app']->run_query($blockchain_q);
		
		while ($db_blockchain = $blockchain_r->fetch()) {
			$blockchain_i = count($blockchains);
			$blockchains[$blockchain_i] = new Blockchain($app, $db_blockchain['blockchain_id']);
			try {
				$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
				echo "Loading game block headers...\n";
				$blockchains[$blockchain_i]->load_new_blocks($coin_rpc);
				echo "Loading game blocks...\n";
				$blockchains[$blockchain_i]->load_all_blocks($coin_rpc, TRUE);
				echo "Loading all block headers...\n";
				$blockchains[$blockchain_i]->load_all_block_headers($coin_rpc, FALSE, 90);
			} catch (Exception $e) {
				echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.<br/>\n";
			}
		}
	}
	else echo "Block loading script is already running, skip...\n";
}
else echo "Please supply the correct key.\n";
?>
