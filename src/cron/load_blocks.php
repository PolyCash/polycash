<?php
set_time_limit(0);
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 54;
$min_loop_target_time = 5;
$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	// If running from browser, run in background to get a unique PID, to avoid process lock problems
	if (!$app->running_from_commandline()) {
		$pipe_config = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];
		$pipes = [];
		
		$cmd = $app->php_binary_location().' "'.AppSettings::srcPath().'/cron/load_blocks.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) echo "Started the background process.<br/>$cmd<br/>\n";
		else echo "Failed to start a process for loading blocks.<br/>$cmd<br/>\n";
		die();
	}
	
	$process_lock_name = "load_blocks";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = array();
		
		$sync_blockchains = $app->run_query("SELECT * FROM blockchains WHERE online=1 AND p2p_mode IN ('rpc','web_api');");
		
		while ($db_blockchain = $sync_blockchains->fetch()) {
			$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
			$blockchain->load_coin_rpc();
			$error = false;
			
			if ($db_blockchain['p2p_mode'] != "web_api") {
				if ($blockchain->coin_rpc) {
					try {
						$blockchain->coin_rpc->getwalletinfo();
					} catch (Exception $e) {
						$error = true;
					}
				}
				else $error = true;
				
				if ($print_debug && $error) echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.<br/>\n";
			}
			
			if (!$error) {
				$blockchain_i = count($blockchains);
				$blockchains[$blockchain_i] = $blockchain;
			}
		}
		
		do {
			$loop_start_time = microtime(true);
			
			for ($i=0; $i<count($blockchains); $i++) {
				$debug_html = $blockchains[$i]->sync_coind($print_debug);
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
		if ($print_debug) echo "Block loading script is already running, skip...\n";
	}
}
else echo "Please supply the correct key.\n";
?>