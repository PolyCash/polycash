<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$blockchain_id = (int) $_REQUEST['blockchain_id'];
$address_id = (int) $_REQUEST['address_id'];
$permission_to_claim_address = false;

$blockchain = new Blockchain($app, $blockchain_id);

$q = "SELECT * FROM addresses WHERE address_id='".$address_id."' AND primary_blockchain_id='".$blockchain->db_blockchain['blockchain_id']."';";
$r = $app->run_query($q);

if ($r->rowCount() > 0) {
	$db_address = $r->fetch();
	$permission_to_claim_address = $app->permission_to_claim_address($blockchain, $db_address, $thisuser);
	
	if ($permission_to_claim_address) {
		if ($thisuser) {
			$app->give_address_to_user($blockchain, $db_address, $thisuser);
			$app->output_message(1, "successful!", false);
		}
		else {
			$redirect_url = $app->get_redirect_url("/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/addresses/".$db_address['address']."/?action=claim");
			$app->output_message(2, $redirect_url['redirect_url_id'], false);
		}
	}
	else $app->output_message(3, "Permission denied.", false);
}
else $app->output_message(4, "Invalid address ID.", false);
?>