<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['print_debug'])) $_REQUEST['print_debug'] = $cmd_vars['print_debug'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "loading_blocks";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = array();
		
		$blockchain_q = "SELECT * FROM blockchains WHERE online=1 AND p2p_mode IN ('rpc','web_api');";
		$blockchain_r = $GLOBALS['app']->run_query($blockchain_q);
		
		while ($db_blockchain = $blockchain_r->fetch()) {
			$blockchain_i = count($blockchains);
			$blockchains[$blockchain_i] = new Blockchain($app, $db_blockchain['blockchain_id']);
			$error = false;
			
			if ($db_blockchain['p2p_mode'] == "web_api") $coin_rpc = false;
			else {
				try {
					$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
					$coin_rpc->getinfo();
				} catch (Exception $e) {
					$error = true;
					if ($print_debug) echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.<br/>\n";
				}
			}
			
			if (!$error) {
				if ($print_debug) echo "Syncing ".$blockchains[$blockchain_i]->db_blockchain['blockchain_name']."\n";
				$debug_html = $blockchains[$blockchain_i]->sync_coind($coin_rpc);
				if ($print_debug) echo $debug_html;
			}
		}
	}
	else {
		$app->log_message("NOT running load_blocks.php");
		if ($print_debug) echo "Block loading script is already running, skip...\n";
	}
}
else echo "Please supply the correct key.\n";
?>