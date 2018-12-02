<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_target_time = 54;
$min_loop_target_time = 5;
$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "loading_blocks";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = array();
		$coin_rpcs = array();
		
		$blockchain_q = "SELECT * FROM blockchains WHERE online=1 AND p2p_mode IN ('rpc','web_api');";
		$blockchain_r = $GLOBALS['app']->run_query($blockchain_q);
		
		while ($db_blockchain = $blockchain_r->fetch()) {
			$error = false;
			
			if ($db_blockchain['p2p_mode'] == "web_api") $coin_rpc = false;
			else {
				try {
					$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
					$coin_rpc->getwalletinfo();
				} catch (Exception $e) {
					$error = true;
					if ($print_debug) echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.<br/>\n";
				}
			}
			
			if (!$error) {
				$blockchain_i = count($blockchains);
				$blockchains[$blockchain_i] = new Blockchain($app, $db_blockchain['blockchain_id']);
				$coin_rpcs[$blockchain_i] = $coin_rpc;
			}
		}
		
		do {
			$loop_start_time = microtime(true);
			
			for ($i=0; $i<count($blockchains); $i++) {
				if ($print_debug) echo "Syncing ".$blockchains[$i]->db_blockchain['blockchain_name']."\n";
				$debug_html = $blockchains[$i]->sync_coind($coin_rpcs[$i], $print_debug);
				if ($print_debug) echo $debug_html;
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max($min_loop_target_time, $loop_time);
			$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			if ($print_debug) echo "script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n";
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
	}
	else {
		$app->log_message("NOT running load_blocks.php");
		if ($print_debug) echo "Block loading script is already running, skip...\n";
	}
}
else echo "Please supply the correct key.\n";
?>