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
		
		$script_start_time = microtime(true);
		$script_target_time = 171;
		$blockchains_by_id = [];
		$add_privkey_count = 0;
		
		$backup_addresses = $app->run_query("SELECT * FROM users u JOIN currency_accounts a ON a.user_id=u.user_id JOIN address_keys k ON a.account_id=k.account_id JOIN currencies c ON a.currency_id=c.currency_id WHERE u.backups_enabled=1 AND c.blockchain_id IS NOT NULL AND k.backed_up_at IS NULL ORDER BY u.user_id ASC, a.account_id ASC, k.address_key_id ASC;")->fetchAll(PDO::FETCH_ASSOC);
		
		if ($print_debug) $app->print_debug("Processing ".count($backup_addresses)." addresses for backup.");
		
		foreach ($backup_addresses as $backup_address) {
			$address_start_time = microtime(true);
			
			if (empty($blockchains_by_id[$backup_address['blockchain_id']])) $blockchains_by_id[$backup_address['blockchain_id']] = new Blockchain($app, $backup_address['blockchain_id']);
			
			$blockchain = &$blockchains_by_id[$backup_address['blockchain_id']];
			$blockchain->load_coin_rpc();
			
			if ($blockchain->coin_rpc) {
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
			}
			else if ($print_debug) $app->print_debug("Failed to load RPC client for blockchain #".$blockchain->db_blockchain['blockchain_id']);
		}
		
		if ($print_debug) $app->print_debug("Backed up ".$add_privkey_count." addresses in ".round(microtime(true)-$script_start_time, 6)." sec.");
		
		$export_start_time = microtime(true);
		
		$users_needing_backup = $app->run_query("SELECT u.* FROM users u JOIN currency_accounts a ON a.user_id=u.user_id JOIN address_keys k ON a.account_id=k.account_id JOIN currencies c ON a.currency_id=c.currency_id WHERE u.backups_enabled=1 AND u.unsubscribed=0 AND u.time_created <= ".(time() - (60*15))." AND c.blockchain_id IS NOT NULL AND k.backed_up_at IS NOT NULL AND k.exported_backup_at IS NULL GROUP BY u.user_id ORDER BY u.user_id ASC;")->fetchAll(PDO::FETCH_ASSOC);
		
		if ($print_debug) $app->print_debug("Exporting address backups for ".count($users_needing_backup)." users.");
		
		$export_count = 0;
		
		foreach ($users_needing_backup as $backup_user) {
			$lastExportAt = User::getLastAddressExportAt($app, $backup_user['user_id']);
			
			if ($lastExportAt === null || $lastExportAt <= time()-(60*5)) {
				if (!empty($backup_user['notification_email']) && strpos($backup_user['notification_email'], "@") !== false) $to_email = $backup_user['notification_email'];
				else if (strpos($backup_user['username'], "@") !== false) $to_email = $thisuser->db_user['username'];
				else $to_email = null;
				
				if ($to_email !== null) {
					$backup_accounts = $app->run_query("SELECT a.*, b.blockchain_name FROM currency_accounts a JOIN address_keys k ON a.account_id=k.account_id JOIN currencies c ON a.currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE a.user_id=:user_id AND c.blockchain_id IS NOT NULL AND k.backed_up_at IS NOT NULL AND k.exported_backup_at IS NULL GROUP BY a.account_id ORDER BY a.account_id ASC;", [
						'user_id' => $backup_user['user_id'],
					])->fetchAll(PDO::FETCH_ASSOC);
					
					if ($print_debug) $app->print_debug("Exporting backups on ".count($backup_accounts)." accounts for user #".$backup_user['user_id']);
					
					$message = "";
					
					$new_address_key_ids = [];
					$new_address_key_ids_by_account = [];
					
					$csv_arr = [
						['Account ID','Address','Private Key','Backup Status']
					];
					
					foreach ($backup_accounts as $backup_account) {
						$backup_addresses = $app->run_query("SELECT * FROM address_keys WHERE account_id=:account_id AND backed_up_at IS NOT NULL ORDER BY address_key_id ASC;", [
							'account_id' => $backup_account['account_id'],
						])->fetchAll(PDO::FETCH_ASSOC);
						
						if (!empty($backup_account['game_id'])) $account_game = $app->fetch_game_by_id($backup_account['game_id']);
						else $account_game = null;
						
						$message .= $backup_account['blockchain_name'].($account_game ? " - ".$account_game['name'] : '')." account #".$backup_account['account_id']." (".count($backup_addresses)." address".(count($backup_addresses) == 1 ? "" : "es").")<br/>\n";
						
						$new_address_key_ids_by_account[$backup_account['account_id']] = [];
						
						foreach ($backup_addresses as $backup_address) {
							array_push($csv_arr, [
								$backup_account['account_id'],
								$backup_address['pub_key'],
								$backup_address['priv_key'],
								empty($backup_address['exported_backup_at']) ? 'New' : 'Existing',
							]);
							
							if (empty($backup_address['exported_backup_at'])) {
								array_push($new_address_key_ids, $backup_address['address_key_id']);
								array_push($new_address_key_ids_by_account[$backup_account['account_id']], $backup_address['address_key_id']);
							}
						}
					}
					
					$backup_extra_info = [
						'account_ids' => array_keys($new_address_key_ids_by_account),
						'address_key_ids' => $new_address_key_ids_by_account,
					];
					
					$backup = User::recordBackupExport($app, $backup_user, $backup_extra_info, null);
					
					if (count($new_address_key_ids) > 0) {
						$app->run_query("UPDATE address_keys SET exported_backup_at=NOW() WHERE address_key_id IN (".implode(",", $new_address_key_ids).");");
					}
					
					$subject = "Export of private keys for ".AppSettings::getParam("site_domain");
					
					$message = "<p>This backup includes private keys for ".count($new_address_key_ids)." new address".(count($new_address_key_ids) == 1 ? "" : "es").".</p><p>".$message."</p><p>Backup details are available here:<br/>".AppSettings::getParam('base_url')."/accounts/backups/?view_backup_id=".$backup['export_id']."</p>\n";
					
					$csv_raw = $app->array2csv($csv_arr);
					
					$delivery_id = $app->mail_async($to_email, AppSettings::getParam('site_name'), AppSettings::defaultFromEmailAddress(), $subject, $message, "", "", null, "csv", $csv_raw);
					
					$export_count++;
				}
				else if ($print_debug) $app->print_debug("Skipping backup exports for user #".$backup_user['user_id']." due to invalid notification_email.");
			}
			else if ($print_debug) $app->print_debug("User #".$backup_user['user_id']." had a recent export, skipping..");
		}
		
		if ($print_debug) $app->print_debug("Exported backups for ".$export_count." users.");
	}
	else echo "Address processing is already running.\n";
}
else echo "Please run this script as administrator\n";
