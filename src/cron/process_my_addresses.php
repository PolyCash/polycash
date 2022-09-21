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
		
		$total_add_count = 0;
		$total_add_privkey_count = 0;
		
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
								if ($print_debug) $app->print_debug("Checking ".count($listsinceblock['transactions'])." transactions from block ".$process_from_block['block_id']);
								
								$currency_id = $blockchain->currency_id();
								$add_count = 0;
								$add_privkey_count = 0;
								$transfer_count = 0;
								$transaction_pos = 0;
								
								foreach ($listsinceblock['transactions'] as $my_transaction) {
									$address_info = $blockchain->coin_rpc->getaddressinfo($my_transaction['address']);
									
									if ($address_info && array_key_exists('ismine', $address_info) && $address_info['ismine']) {
										$db_address = $blockchain->create_or_fetch_address($my_transaction['address'], false, null);
										
										if (!$db_address['is_mine']) {
											$app->run_query("UPDATE addresses SET is_mine=1 WHERE address_id=:address_id;", ['address_id' => $db_address['address_id']]);
											$add_count++;
										}
										
										$address_key = $app->fetch_address_key_by_address_id($db_address['address_id']);
										
										if ($address_key) {
											if (empty($address_key['used_in_my_tx'])) {
												$app->run_query("UPDATE address_keys SET used_in_my_tx=1 WHERE address_key_id=:address_key_id;", [
													'address_key_id' => $address_key['address_key_id']
												]);
											}
										}
										else {
											$address_key = $app->insert_address_key([
												'currency_id' => $currency_id,
												'address_id' => $db_address['address_id'],
												'account_id' => null,
												'pub_key' => $db_address['address'],
												'option_index' => $db_address['option_index'],
												'primary_blockchain_id' => $db_address['primary_blockchain_id'],
												'used_in_my_tx' => 1,
											]);
										}
										
										if ($address_key && empty($address_key['account_id']) && !empty($blockchain->db_blockchain['auto_claim_to_account_id'])) {
											$app->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
												'account_id' => $blockchain->db_blockchain['auto_claim_to_account_id'],
												'address_key_id' => $address_key['address_key_id'],
											]);
											$address_key['account_id'] = $blockchain->db_blockchain['auto_claim_to_account_id'];
											$transfer_count++;
										}
										
										if ($address_key && empty($address_key['priv_key'])) {
											$priv_key = $blockchain->coin_rpc->dumpprivkey($address_key['pub_key']);
											
											if ($priv_key && is_string($priv_key)) {
												$app->run_query("UPDATE address_keys SET priv_key=:priv_key WHERE address_key_id=:address_key_id;", [
													'priv_key' => $priv_key,
													'address_key_id' => $address_key['address_key_id']
												]);
												$add_privkey_count++;
											}
										}
									}
									
									if ($print_debug && $transaction_pos > 0 && $transaction_pos%1000 == 0) $app->print_debug($transaction_pos."/".count($listsinceblock['transactions']).", ".round(100*$transaction_pos/count($listsinceblock['transactions']), 4)."%");
									
									$transaction_pos++;
								}
								
								$blockchain->set_processed_my_addresses_to_block($last_block_id);
								
								$total_add_count += $add_count;
								$total_add_privkey_count += $add_privkey_count;
								
								if ($print_debug) $app->print_debug("Set ".$add_count." addresses as mine, backed up ".$add_privkey_count." private keys, transferred ".$transfer_count." to account");
							}
							else if ($print_debug) $app->print_debug("listsinceblock ".$process_from_block['block_id']." returned 0 transactions.");
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
		
		if ($print_debug) $app->print_debug("Set ".$total_add_count." addresses as mine, backed up ".$total_add_privkey_count." private keys.");
		
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_target_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		
		if ($print_debug) $app->print_debug("Script ran for ".round($runtime_sec, 2)." seconds.");
	}
	else echo "Address processing is already running.\n";
}
else echo "Please run this script as administrator\n";
