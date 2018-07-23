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
		
		$address_key = $app->new_address_key($account['currency_id'], $account);
		
		if ($address_key) $app->output_message(1, "Great, a new address was generated.", false);
		else $app->output_message(4, "There was an error generating the address.", false);
	}
	else $app->output_message(3, "Error, invalid account ID.", false);
}
else $app->output_message(2, "Please log in", false);
?>