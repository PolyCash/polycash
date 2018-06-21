<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];

	$q = "SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id WHERE c.blockchain_id='".$blockchain_id."' AND ca.user_id='".$thisuser->db_user['user_id']."' AND ca.game_id IS NULL ORDER BY ca.account_name ASC;";
	$r = $app->run_query($q);

	$html = "<option value=\"\">-- Please Select --</option>\n";
	
	while ($account = $r->fetch()) {
		$html .= "<option value=\"".$account['account_id']."\">".$account['account_name']."</option>\n";
	}
	
	$output_obj['html'] = $html;
	
	$app->output_message(1, "", $output_obj);
}
else {
	$app->output_message(1, "Please log in", false);
}
?>