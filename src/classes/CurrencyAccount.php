<?php
class CurrencyAccount {
	public static $fieldsInfo = [
		'faucet_donations_on' => [
			'editableInSettings' => true,
		],
		'faucet_target_balance' => [
			'editableInSettings' => true,
		],
		'faucet_amount_each' => [
			'editableInSettings' => true,
		],
		'backups_enabled' => [
			'editableInSettings' => true,
		],
	];
	
	public static function updateAccount(&$app, &$account, $updateParams) {
		$updateQuery = "UPDATE currency_accounts SET ";
		foreach ($updateParams as $paramName => $paramVal) {
			$updateQuery .= $paramName."=:".$paramName.", ";
			$account[$paramName] = $paramVal;
		}
		$updateQuery = substr($updateQuery, 0, -2)." WHERE account_id=:account_id;";
		$updateParams['account_id'] = $account['account_id'];
		
		$app->run_query($updateQuery, $updateParams);
	}
	
	public static function fetchAllBackupAccounts(&$app) {
		return $app->run_query("SELECT * FROM currency_accounts WHERE backups_enabled=1 ORDER BY account_id ASC;")->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function fetchAccountAddressesNeedingBackup(&$app, &$account) {
		return $app->run_query("SELECT * FROM address_keys WHERE account_id=:account_id AND backed_up_at IS NULL ORDER BY option_index ASC, address_id ASC;", [
			'account_id' => $account['account_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function fetchAccountAddressesNeedingExport(&$app, &$account) {
		return $app->run_query("SELECT * FROM address_keys WHERE account_id=:account_id AND backed_up_at IS NOT NULL AND exported_backup_at IS NULL ORDER BY option_index ASC, address_id ASC;", [
			'account_id' => $account['account_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function getLastExportInAccount(&$app, &$account) {
		$info = $app->run_query("SELECT * FROM address_keys WHERE account_id=:account_id AND exported_backup_at IS NOT NULL ORDER BY exported_backup_at DESC LIMIT 1;", [
			'account_id' => $account['account_id'],
		])->fetch();
		
		if (!empty($info['exported_backup_at'])) return $info['exported_backup_at'];
		else return null;
	}
	
	public static function recordBackup(&$app, &$user, &$account, &$address_key_ids, $ip_address) {
		$app->run_query("INSERT INTO currency_account_backups SET user_id=:user_id, account_id=:account_id, ip_address=:ip_address, extra_info=:extra_info, exported_at=NOW();", [
			'user_id' => $user->db_user['user_id'],
			'account_id' => $account['account_id'],
			'ip_address' => $ip_address,
			'extra_info' => json_encode([
				'address_key_ids' => $address_key_ids,
			]),
		]);
		
		return CurrencyAccount::fetchBackupById($app, $app->last_insert_id());
	}
	
	public static function fetchBackupById(&$app, $backup_id) {
		return $app->run_query("SELECT * FROM currency_account_backups WHERE backup_id=:backup_id;", [
			'backup_id' => $backup_id
		])->fetch();
	}
	
	public static function fetchAllBackupsByUser(&$app, &$user) {
		return $app->run_query("SELECT b.*, u.username FROM currency_account_backups b JOIN currency_accounts a ON b.account_id=a.account_id LEFT JOIN users u ON b.user_id=u.user_id WHERE a.user_id=:user_id ORDER BY b.exported_at DESC, a.account_id DESC;", [
			'user_id' => $user->db_user['user_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function fetchAddressKeysByIdArr(&$app, &$account, $address_key_ids) {
		return $app->run_query("SELECT * FROM address_keys WHERE account_id=:account_id AND address_key_id IN (".implode(",", $address_key_ids).") ORDER BY option_index ASC, address_id ASC;", [
			'account_id' => $account['account_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}
}
