<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];

	$accounts_by_blockchain = $app->run_query("SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id WHERE c.blockchain_id=:blockchain_id AND ca.user_id=:user_id AND ca.is_escrow_account=0 ORDER BY ca.account_name ASC;", [
		'blockchain_id' => $blockchain_id,
		'user_id' => $thisuser->db_user['user_id']
	]);

	$html = "<option value=\"\">-- Please Select --</option>\n";
	
	while ($account = $accounts_by_blockchain->fetch()) {
		$html .= "<option value=\"".$account['account_id']."\">".$account['account_name']."</option>\n";
	}
	
	$output_obj['html'] = $html;
	
	$app->output_message(1, "", $output_obj);
}
else $app->output_message(1, "Please log in", false);
?>