<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$account_id = (int) $_REQUEST['account_id'];

	$account = $app->fetch_account_by_id($account_id);
	
	if ($account && $account['user_id'] == $thisuser->db_user['user_id']) {
		if ($_REQUEST['action'] == "new") {
			$address_key = $app->new_address_key($account['currency_id'], $account);
			
			$currency = $app->fetch_currency_by_id($account['currency_id']);
			$account_blockchain = $app->fetch_blockchain_by_id($currency['blockchain_id']);
			
			if ($address_key) $app->output_message(1, "/explorer/blockchains/".$account_blockchain['url_identifier']."/addresses/".$address_key['address'], false);
			else $app->output_message(4, "There was an error generating the address.", false);
		}
		else if ($_REQUEST['action'] == "set_primary") {
			$address_id = (int) $_REQUEST['address_id'];
			
			$address_key = $app->run_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id=:address_id AND k.account_id=:account_id;", [
				'address_id' => $address_id,
				'account_id' => $account['account_id']
			])->fetch();
			
			if ($address_key) {
				if ($address_key['is_separator_address'] == 0 && $address_key['is_destroy_address'] == 0) {
					$app->run_query("UPDATE currency_accounts SET current_address_id=:address_id WHERE account_id=:account_id;", [
						'address_id' => $address_key['address_id'],
						'account_id' => $account['account_id']
					]);
					
					$app->output_message(6, "This address has been set as primary for this account.", false);
				}
				else $app->output_message(5, "Action canceled: this is not a standard address.", false);
			}
			else $app->output_message(4, "That address is not in this account.", false);
		}
	}
	else $app->output_message(3, "Error, invalid account ID.", false);
}
else $app->output_message(2, "Please log in", false);
?>