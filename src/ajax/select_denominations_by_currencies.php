<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$currency_id = (int) $_REQUEST['currency_id'];
	$denominations_html = "";
	$accounts_html = "";

	$currency = $app->fetch_currency_by_id($currency_id);

	if ($currency) {
		$fv_currency = $app->fetch_currency_by_id((int)$_REQUEST['fv_currency_id']);
		
		if ($fv_currency) {
			$denominations_html .= "<option value=\"\">-- Please Select --</option>\n";
			
			$denominations = $app->get_card_denominations($currency, $fv_currency['currency_id']);
			
			foreach ($denominations as $denomination) {
				$denominations_html .= "<option value=\"".$denomination['denomination_id']."\">".$denomination['denomination']."</option>\n";
			}
			
			$accounts_html .= "<option value=\"\">-- Please Select --</option>\n";
			
			$accounts_by_currency = $app->run_query("SELECT * FROM currency_accounts WHERE game_id IS NULL AND user_id=:user_id AND currency_id=:currency_id ORDER BY account_name ASC;", [
				'user_id' => $thisuser->db_user['user_id'],
				'currency_id' => $fv_currency['currency_id']
			]);
			
			while ($account = $accounts_by_currency->fetch()) {
				$accounts_html .= "<option value=\"".$account['account_id']."\">".$account['account_name']."</option>\n";;
			}
		}

		$currency_price = $app->latest_currency_price($currency['currency_id']);
		$output_obj['cost_per_coin'] = $currency_price['price'];
	}

	$output_obj['denominations_html'] = $denominations_html;
	$output_obj['accounts_html'] = $accounts_html;
	if (!empty($fv_currency)) $output_obj['coin_abbreviation'] = $fv_currency['abbreviation'];
	echo json_encode($output_obj);
}
else $app->output_message(1, "Please log in", false);
?>