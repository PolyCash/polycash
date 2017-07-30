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

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$loading_blocks = $app->check_process_running("loading_blocks");
	
	if (!$loading_blocks) {
		if ($GLOBALS['process_lock_method'] == "db") {
			$GLOBALS['app'] = $app;
			$GLOBALS['shutdown_lock_name'] = "loading_blocks";
			$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
			register_shutdown_function("script_shutdown");
		}
		$app->log_message("running load_blocks.php");
		$app->set_site_constant("last_script_run_time", time());
		
		$blockchains = array();
		
		$blockchain_q = "SELECT * FROM blockchains WHERE online=1;";
		$blockchain_r = $GLOBALS['app']->run_query($blockchain_q);
		
		while ($db_blockchain = $blockchain_r->fetch()) {
			$blockchain_i = count($blockchains);
			$blockchains[$blockchain_i] = new Blockchain($app, $db_blockchain['blockchain_id']);
			$error = false;
			try {
				$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
				$coin_rpc->getinfo();
			} catch (Exception $e) {
				$error = true;
				echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.<br/>\n";
			}
			
			if (!$error) $blockchains[$blockchain_i]->sync_coind($coin_rpc);
		}
	}
	else {
		$app->log_message("NOT running load_blocks.php");
		echo "Block loading script is already running, skip...\n";
	}
}
else echo "Please supply the correct key.\n";
?>
