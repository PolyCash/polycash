<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "process_address_backups";
	
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$script_target_time = 171;
		$process_sec_per_account = 15;
		$min_loop_time = 5;
		
		$currencies_by_id = [];
		$blockchains_by_id = [];
		
		$total_add_count = 0;
		$total_add_privkey_count = 0;
		
		do {
			$loop_start_time = microtime(true);
			$add_privkey_count = 0;
			
			$backup_accounts = CurrencyAccount::fetchAllBackupAccounts($app);
			
			if ($print_debug) $app->print_debug("Processing ".count($backup_accounts)." accounts with backups enabled.");
			
			foreach ($backup_accounts as $backup_account) {
				$account_start_time = microtime(true);
				
				$backup_addresses = CurrencyAccount::fetchAccountAddressesNeedingBackup($app, $backup_account);
				if ($print_debug) $app->print_debug(count($backup_addresses)." addresses need to be backed up in account #".$backup_account['account_id']);
				
				if (count($backup_addresses) > 0) {
					if (empty($currencies_by_id[$backup_account['currency_id']])) $currencies_by_id[$backup_account['currency_id']] = $app->fetch_currency_by_id($backup_account['currency_id']);
					
					if (empty($currencies_by_id[$backup_account['currency_id']]['blockchain_id'])) {
						if ($print_debug) $app->print_debug("Skipping backups for account #".$backup_account['account_id'].", it's not associated to a blockchain.");
					}
					else {
						if (empty($blockchains_by_id[$currencies_by_id[$backup_account['currency_id']]['blockchain_id']])) $blockchains_by_id[$currencies_by_id[$backup_account['currency_id']]['blockchain_id']] = new Blockchain($app, $currencies_by_id[$backup_account['currency_id']]['blockchain_id']);
						
						$blockchain = &$blockchains_by_id[$currencies_by_id[$backup_account['currency_id']]['blockchain_id']];
						$blockchain->load_coin_rpc();
						
						if ($blockchain->coin_rpc) {
							foreach ($backup_addresses as $backup_address) {
								$address_start_time = microtime(true);
								
								$priv_key = $blockchain->coin_rpc->dumpprivkey($backup_address['pub_key']);
								
								if ($priv_key && is_string($priv_key)) {
									$app->run_query("UPDATE address_keys SET priv_key=:priv_key, backed_up_at=NOW() WHERE address_key_id=:address_key_id;", [
										'priv_key' => $priv_key,
										'address_key_id' => $backup_address['address_key_id']
									]);
									$add_privkey_count++;
									
									if ($print_debug) $app->print_debug("Backed up address #".$backup_address['address_id']." in ".round(microtime(true)-$address_start_time, 6)." sec");
								}
								else if ($print_debug) $app->print_debug("Failed to back up address #".$backup_address['address_id']." in ".round(microtime(true)-$address_start_time, 6)." sec");
								
								if (microtime(true)- $account_start_time >= $process_sec_per_account) break;
							}
						}
						else if ($print_debug) $app->print_debug("Failed to load RPC client for blockchain #".$blockchain->db_blockchain['blockchain_id']);
					}
				}
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			if ($loop_time < $min_loop_time) $sleep_usec = round(pow(10,6)*($min_loop_time - $loop_time));
			else $sleep_usec = 0;
			
			if ($print_debug) {
				$app->print_debug("Backed up ".$add_privkey_count." addresses in ".round($loop_time, 6)." sec.");
				$app->print_debug("Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." sec.");
			}
			
			$total_add_privkey_count += $add_privkey_count;
			
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + $script_target_time);
		
		if ($print_debug) $app->print_debug("In total backed up ".$total_add_privkey_count." private keys.");
		
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round($script_target_time-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		
		if ($print_debug) $app->print_debug("Script ran for ".round($runtime_sec, 2)." seconds.");
	}
	else echo "Address processing is already running.\n";
}
else echo "Please run this script as administrator\n";
