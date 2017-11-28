<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$currency_id = (int) $_REQUEST['currency_id'];
	$html = "";

	$q = "SELECT * FROM currencies WHERE currency_id='".$currency_id."';";
	$r = $app->run_query($q);

	if ($r->rowCount() == 1) {
		$currency = $r->fetch();
		
		$fv_currency_id = (int) $_REQUEST['fv_currency_id'];
		
		$q = "SELECT * FROM currencies WHERE currency_id='".$fv_currency_id."';";
		$r = $app->run_query($q);

		if ($r->rowCount() == 1) {
			$fv_currency = $r->fetch();
			
			$html .= "<option value=\"\">-- Please Select --</option>\n";
			
			$denominations = $app->get_card_denominations($currency, $fv_currency['currency_id']);
			
			foreach ($denominations as $denomination) {
				$html .= "<option value=\"".$denomination['denomination_id']."\">".$denomination['denomination']."</option>\n";
			}
		}

		$currency_price = $app->latest_currency_price($currency['currency_id']);
		$output_obj['cost_per_coin'] = $currency_price['price'];
	}

	$output_obj['html'] = $html;
	$output_obj['coin_abbreviation'] = $fv_currency['abbreviation'];
	echo json_encode($output_obj);
}
else {
	$app->output_message(1, "Please log in", false);
}
?>