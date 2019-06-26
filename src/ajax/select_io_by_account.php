<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

if ($thisuser) {
	$account_id = (int) $_REQUEST['account_id'];

	$account = $app->run_query("SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE ca.account_id=:account_id AND ca.user_id=:user_id;", [
		'account_id' => $account_id,
		'user_id' => $thisuser->db_user['user_id']
	])->fetch();
	
	if ($account) {
		$html = "";
		
		$ios_by_account = $app->run_query("SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE k.account_id=:account_id AND io.spend_status='unspent' ORDER BY io.amount DESC;", [
			'account_id' => $account['account_id']
		]);
		
		while ($io = $ios_by_account->fetch()) {
			$html .= "<option value=\"".$io['io_id']."\">UTXO #".$io['io_id']." (".$app->format_bignum($io['amount']/pow(10,$account['decimal_places']))." ".$account['coin_name_plural'].")</option>\n";
		}
		
		$output_obj['html'] = $html;
		
		$app->output_message(1, "", $output_obj);
	}
}
else $app->output_message(1, "Please log in", false);
?>