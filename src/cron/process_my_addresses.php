<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 171;
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
	
	$process_lock_name = "process_my_addresses_".$blockchain->db_blockchain['blockchain_id'];
	
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		if ($print_debug) $app->print_debug("Processing my addresses for ".$blockchain->db_blockchain['blockchain_name']);
		
		$loop_target_time = 8;
		do {
			$loop_start_time = microtime(true);
			
			$blockchain = new Blockchain($app, $blockchain->db_blockchain['blockchain_id']);
			$blockchain->load_coin_rpc();
			
			if ($blockchain->coin_rpc) {
				$blockchain_info = $blockchain->coin_rpc->getblockchaininfo();
				
				if ($blockchain_info) {
					if ($blockchain->db_blockchain['processed_my_addresses_to_block'] == "") $process_from_block_height = 1;
					else $process_from_block_height = min($blockchain->last_block_id(), $blockchain->db_blockchain['processed_my_addresses_to_block']+1);
					
					$process_from_block = $blockchain->fetch_block_by_id($process_from_block_height);
					
					if ($process_from_block) {
						if (!empty($process_from_block['block_hash'])) {
							$last_block_id = $blockchain->last_block_id();
							$listsinceblock = $blockchain->coin_rpc->listsinceblock($process_from_block['block_hash']);
							
							if (!empty($listsinceblock['transactions']) && count($listsinceblock['transactions']) > 0) {
								$currency_id = $blockchain->currency_id();
								$add_count = 0;
								
								foreach ($listsinceblock['transactions'] as $my_transaction) {
									$db_address = $blockchain->create_or_fetch_address($my_transaction['address'], false, null);
									if (!$db_address['is_mine']) {
										$app->run_query("UPDATE addresses SET is_mine=1 WHERE address_id=:address_id;", ['address_id' => $db_address['address_id']]);
										
										$app->insert_address_key([
											'currency_id' => $currency_id,
											'address_id' => $db_address['address_id'],
											'account_id' => null,
											'pub_key' => $db_address['address'],
											'option_index' => $db_address['option_index'],
											'primary_blockchain_id' => $db_address['primary_blockchain_id']
										]);
										
										$add_count++;
									}
								}
								
								$blockchain->set_processed_my_addresses_to_block($last_block_id);
								
								if ($print_debug) $app->print_debug("Checked ".count($listsinceblock['transactions'])." transactions from block #".$process_from_block['block_id'].", set ".$add_count." addresses as mine");
							}
							else if ($print_debug) $app->print_debug("listsinceblock returned 0 transactions.");
						}
						else if ($print_debug) $app->print_debug("Block hash not yet set for block #".$process_from_block['block_id']);
					}
					else if ($print_debug) $app->print_debug("Failed to fetch block #".$process_from_block_height." from db.");
				}
				else if ($print_debug) $app->print_debug("Skipped address processing, RPC connection failed.");
			}
			else if ($print_debug) $app->print_debug("Failed to initialize RPC client.");
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			if ($loop_time < $loop_target_time) $sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			else $sleep_usec = 0;
			
			if ($print_debug) $app->print_debug("Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.");
			
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
		
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_target_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		
		if ($print_debug) $app->print_debug("Script ran for ".round($runtime_sec, 2)." seconds.");
	}
	else echo "A block mining process is already running.\n";
}
else echo "Please run this script as administrator\n";
