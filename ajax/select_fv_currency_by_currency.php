<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$currency_id = (int) $_REQUEST['currency_id'];

	$q = "SELECT * FROM currencies WHERE currency_id='".$currency_id."';";
	$r = $app->run_query($q);

	if ($r->rowCount() == 1) {
		$currency = $r->fetch();
		
		echo "<option value=\"\">-- Please Select --</option>\n";
		
		$q = "SELECT * FROM card_currency_denominations d JOIN currencies c ON d.fv_currency_id=c.currency_id WHERE d.currency_id='".$currency['currency_id']."' GROUP BY c.currency_id ORDER BY c.name ASC;";
		$r = $app->run_query($q);
		while ($currency = $r->fetch()) {
			echo "<option value=\"".$currency['currency_id']."\">".$currency['name']."</option>\n";
		}
	}
}
else {
	$app->output_message(1, "Please log in", false);
}
?>