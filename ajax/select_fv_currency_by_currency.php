<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$currency = $app->fetch_currency_by_id((int)$_REQUEST['currency_id']);
	
	if ($currency) {
		echo "<option value=\"\">-- Please Select --</option>\n";
		
		$fv_currencies = $app->run_query("SELECT * FROM card_currency_denominations d JOIN currencies c ON d.fv_currency_id=c.currency_id WHERE d.currency_id='".$currency['currency_id']."' GROUP BY c.currency_id ORDER BY c.name ASC;");
		
		while ($fv_currency = $fv_currencies->fetch()) {
			echo "<option value=\"".$fv_currency['currency_id']."\">".$fv_currency['name']."</option>\n";
		}
	}
}
else $app->output_message(1, "Please log in", false);
?>