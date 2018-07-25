<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$account_id = (int) $_REQUEST['account_id'];

	$q = "SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE ca.account_id='".$account_id."' AND ca.user_id='".$thisuser->db_user['user_id']."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 1) {
		$account = $r->fetch();
		$html = "";
		
		$q = "SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE k.account_id=".$account['account_id']." AND io.spend_status='unspent' ORDER BY io.amount DESC;";
		$r = $app->run_query($q);
		
		while ($io = $r->fetch()) {
			$html .= "<option value=\"".$io['io_id']."\">UTXO #".$io['io_id']." (".$app->format_bignum($io['amount']/pow(10,$account['decimal_places']))." ".$account['coin_name_plural'].")</option>\n";
		}
		
		$output_obj['html'] = $html;
		
		$app->output_message(1, "", $output_obj);
	}
}
else {
	$app->output_message(1, "Please log in", false);
}
?>