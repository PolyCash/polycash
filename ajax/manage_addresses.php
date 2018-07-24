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
		
		if ($_REQUEST['action'] == "new") {
			$address_key = $app->new_address_key($account['currency_id'], $account);
			
			if ($address_key) $app->output_message(1, "Great, a new address was generated.", false);
			else $app->output_message(4, "There was an error generating the address.", false);
		}
		else if ($_REQUEST['action'] == "set_primary") {
			$address_id = (int) $_REQUEST['address_id'];
			
			$addr_q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id='".$address_id."' AND k.account_id='".$account['account_id']."';";
			$addr_r = $app->run_query($addr_q);
			
			if ($addr_r->rowCount() > 0) {
				$address_key = $addr_r->fetch();
				
				$q = "UPDATE currency_accounts SET current_address_id='".$address_key['address_id']."' WHERE account_id='".$account['account_id']."';";
				$r = $app->run_query($q);
				
				$app->output_message(5, "This address has been set as primary for this account.", false);
			}
			else $app->output_message(4, "That address is not in this account.", false);
		}
	}
	else $app->output_message(3, "Error, invalid account ID.", false);
}
else $app->output_message(2, "Please log in", false);
?>