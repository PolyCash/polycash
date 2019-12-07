<?php
set_time_limit(0);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 595;
$min_loop_target_time = 5;
$script_start_time = microtime(true);

$allowed_params = ['print_debug','blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$only_blockchain_id = false;
	if (!empty($_REQUEST['blockchain_id'])) $only_blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	// If running from browser, run in background to get a unique PID, to avoid process lock problems
	if (!AppSettings::runningFromCommandline()) {
		$pipe_config = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];
		$pipes = [];
		
		$cmd = $app->php_binary_location().' "'.AppSettings::srcPath().'/cron/load_blocks.php"';
		if ($only_blockchain_id) $cmd .= " blockchain_id=".$only_blockchain_id;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) echo "Started the background process.<br/>$cmd<br/>\n";
		else echo "Failed to start a process for loading blocks.<br/>$cmd<br/>\n";
		die();
	}
	
	$process_lock_name = "load_blocks";
	if ($only_blockchain_id) $process_lock_name .= "_".$only_blockchain_id;
	
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		do {
			$loop_start_time = microtime(true);
			
			$sync_blockchain_q = "SELECT * FROM blockchains WHERE ";
			if ($only_blockchain_id) $sync_blockchain_q .= "blockchain_id=".$only_blockchain_id;
			else $sync_blockchain_q .= " online=1 AND p2p_mode IN ('rpc','web_api')";
			$sync_blockchain_q .= ";";
			
			$sync_blockchains = $app->run_query($sync_blockchain_q);
			$any_success = false;
			
			while ($db_blockchain = $sync_blockchains->fetch()) {
				$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
				$blockchain->load_coin_rpc();
				$error = false;
				
				if ($db_blockchain['p2p_mode'] == "rpc") {
					if ($blockchain->coin_rpc) {
						try {
							$blockchain->coin_rpc->getwalletinfo();
						} catch (Exception $e) {
							$error = true;
						}
					}
					else $error = true;
					
					if ($print_debug && $error) echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.\n\n";
				}
				
				if (!$error) {
					$any_success = true;
					$blockchain->sync_coind($print_debug);
					if ($print_debug) echo "\n";
				}
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max($min_loop_target_time, $loop_time);
			$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			if ($print_debug) {
				echo "Script time: ".(microtime(true)-$script_start_time);
				if ($any_success) echo ", sleeping ".$sleep_usec/pow(10,6)." seconds.";
				echo "\n\n";
			}
			if ($any_success) usleep($sleep_usec);
		}
		while ($any_success && microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
	}
	else {
		if ($print_debug) echo "Block loading script is already running, skip...\n";
	}
}
else echo "Please supply the correct key.\n";
?>