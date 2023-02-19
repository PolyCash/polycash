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
}
