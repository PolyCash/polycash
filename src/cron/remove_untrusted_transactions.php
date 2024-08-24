<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 295;
$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug', 'blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$blockchain = null;
	
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	}
	
	if (empty($blockchain)) die("Please specify a valid blockchain_id.\n");
	if ($blockchain->db_blockchain['p2p_mode'] != "rpc") die("This process only runs for RPC blockchains.\n");
	
	$process_lock_name = "remove_untrusted_".$blockchain->db_blockchain['blockchain_id'];
	
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked && $app->lock_process($process_lock_name)) {
		if ($print_debug) $app->print_debug("Removing untrusted transactions for ".$blockchain->db_blockchain['blockchain_name']);
		
		$loop_target_time = 60;
		do {
			$loop_start_time = microtime(true);
			
			$blockchain = new Blockchain($app, $blockchain->db_blockchain['blockchain_id']);
			$blockchain->load_coin_rpc();
			
			if ($blockchain->coin_rpc) {
				$blockchain_info = $blockchain->coin_rpc->getblockchaininfo();
				
				if ($blockchain_info) {
					$process_from_block_height = $blockchain->last_block_id();
					
					$process_from_block = $blockchain->fetch_block_by_id($process_from_block_height);
					
					if ($process_from_block) {
						if (!empty($process_from_block['block_hash'])) {
							$ref_time = microtime(true);
							$listsinceblock = $blockchain->coin_rpc->listsinceblock($process_from_block['block_hash']);
							
							if (!empty($listsinceblock['transactions']) && count($listsinceblock['transactions']) > 0) {
								if ($print_debug) $app->print_debug("Checking ".count($listsinceblock['transactions'])." transactions from block ".$process_from_block['block_id']);
								
								$remove_tx_hashes = [];
								
								foreach ($listsinceblock['transactions'] as $rpc_txo) {
									if (isset($rpc_txo['trusted']) && $rpc_txo['trusted'] == false) {
										if (empty($remove_tx_hashes[$rpc_txo['txid']])) {
											$remove_tx_hashes[$rpc_txo['txid']] = true;
										}
									}
								}
								
								if ($print_debug) $app->print_debug("Found ".count($remove_tx_hashes)." untrusted transactions to remove.");
								
								if (count($remove_tx_hashes) > 0) {
									$success_count = 0;
									$fail_count = 0;
									$skip_count = 0;
									
									foreach (array_keys($remove_tx_hashes) as $remove_tx_hash) {
										$db_transaction = $blockchain->fetch_transaction_by_hash($remove_tx_hash);
										
										if (!$db_transaction) $remove_pruned = true;
										else if ($db_transaction['block_id'] == "") $remove_pruned = true;
										else {
											$message = $app->log_message("Transaction ".$remove_tx_hash." is untrusted but has a block set in db. You may need to delete the ".$blockchain->db_blockchain['blockchain_name']." blockchain from height ".$db_transaction['block_id']);
											if ($print_debug) $app->print_debug($message);
											$remove_pruned = false;
										}
										
										if ($remove_pruned) {
											$blockchain->coin_rpc->removeprunedfunds($remove_tx_hash);
											$removed_tx = $blockchain->coin_rpc->gettransaction($remove_tx_hash);
											
											if (empty($removed_tx['txid'])) {
												if ($db_transaction) {
													$blockchain->delete_transaction($db_transaction);
													$message = $app->log_message("Successfully removed untrusted tx ".$remove_tx_hash." from coin daemon and db.");
													if ($print_debug) $app->print_debug($message);
												}
												else {
													$message = $app->log_message("Removed untrusted tx ".$remove_tx_hash." from coin daemon but it did not need to be deleted from the db.");
													if ($print_debug) $app->print_debug($message);
												}
												$success_count++;
											}
											else {
												$fail_count++;
												$message = $app->print_debug("Failed to remove untrusted tx ".$remove_tx_hash." from coin daemon.");
												if ($print_debug) $app->print_debug($message);
											}
										}
										else $skip_count++;
									}
									
									if ($print_debug) $app->print_debug("Successfully removed ".$success_count." transactions, ".$fail_count." failed and ".$skip_count." were skipped in ".round(microtime(true)-$ref_time, 6)." sec.");
								}
							}
							else if ($print_debug) $app->print_debug("listsinceblock ".$process_from_block['block_id']." returned 0 transactions.");
						}
						else if ($print_debug) $app->print_debug("Block hash not yet set for block #".$process_from_block['block_id']);
					}
					else if ($print_debug) $app->print_debug("Failed to fetch block #".$process_from_block_height." from db.");
				}
				else if ($print_debug) $app->print_debug("Skipped removing untrusted transactions, RPC connection failed.");
			}
			else if ($print_debug) $app->print_debug("Failed to initialize RPC client.");
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			if ($loop_time < $loop_target_time) $sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			else $sleep_usec = 0;
			
			if ($print_debug) $app->print_debug("Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.");
			
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + $script_target_time);
		
		$runtime_sec = microtime(true)-$script_start_time;
		
		if ($print_debug) $app->print_debug("Script ran for ".round($runtime_sec, 2)." seconds.");
	}
	else echo "Remove untrusted transactions process is already running.\n";
}
else echo "Please run this script as administrator\n";
