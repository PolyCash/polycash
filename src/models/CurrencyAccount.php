<?php
class CurrencyAccount {
	public static $fieldsInfo = [
		'account_name' => [
			'editableInSettings' => true,
			'stripTags' => true,
		],
		'donate_to_faucet_id' => [
			'editableInSettings' => true,
		],
		'faucet_target_balance' => [
			'editableInSettings' => true,
		],
		'faucet_amount_each' => [
			'editableInSettings' => true,
		],
		'join_txos_on_quantity' => [
			'editableInSettings' => true,
		],
		'account_transaction_fee' => [
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
	
	public static function getLastExportInAccount(&$app, &$account) {
		$info = $app->run_query("SELECT * FROM address_keys WHERE account_id=:account_id AND exported_backup_at IS NOT NULL ORDER BY exported_backup_at DESC LIMIT 1;", [
			'account_id' => $account['account_id'],
		])->fetch();
		
		if (!empty($info['exported_backup_at'])) return $info['exported_backup_at'];
		else return null;
	}
}
