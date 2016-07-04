<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/jsonRPCClient.php");

$script_start_time = microtime(true);

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM currency_invoices i JOIN invoice_addresses a ON i.invoice_address_id=a.invoice_address_id WHERE i.status != 'confirmed' AND i.status != 'settled' AND (i.status='unconfirmed' OR i.expire_time >= ".time().");";
	$r = run_query($q);

	echo "Checking ".mysql_numrows($r)." invoices.<br/>\n";
	
	while ($invoice = mysql_fetch_array($r)) {
		$confirm_it = false;

		$api_unconfirmed_balance = json_decode(file_get_contents('https://blockchain.info/q/addressbalance/'.$invoice['pub_key'].'?confirmations=0'))/pow(10,8);
		$api_confirmed_balance = json_decode(file_get_contents('https://blockchain.info/q/addressbalance/'.$invoice['pub_key'].'?confirmations=1'))/pow(10,8);

		$qq = "UPDATE currency_invoices SET confirmed_amount_paid='".$api_confirmed_balance."', unconfirmed_amount_paid='".$api_unconfirmed_balance."'";
		if ($api_unconfirmed_balance >= $invoice['pay_amount'] || $api_confirmed_balance >= $invoice['pay_amount']) {
			$qq .= ", status='confirmed'";
			$confirm_it = true;
		}
		else if ($api_unconfirmed_balance > 0) {
			$qq .= ", status='unconfirmed'";
		}
		$qq .= " WHERE invoice_id='".$invoice['invoice_id']."';";
		$rr = run_query($qq);

		if ($confirm_it) {
			// Todo: make sure amounts match what's expected to make sure no one tampered with the db since the invoice was generated
			$invoice_exchange_rate = historical_currency_conversion_rate($invoice['settle_price_id'], $invoice['pay_price_id']);
			$expected_pay_amount = round(pow(10,8)*$settle_amount/$invoice_exchange_rate)/pow(10,8);

			$qq = "SELECT * FROM games WHERE game_id='".$invoice['game_id']."';";
			$rr = run_query($qq);
			$game = mysql_fetch_array($rr);

			if ($game['giveaway_status'] == "public_pay" || $game['giveaway_status'] == "invite_pay") {
				$qq = "SELECT * FROM users WHERE user_id='".$invoice['user_id']."';";
				$rr = run_query($qq);
				$invoice_user = mysql_fetch_array($rr);
				ensure_user_in_game($invoice_user, $game['game_id']);

				$qq = "SELECT * FROM user_games WHERE user_id='".$invoice['user_id']."' AND game_id='".$game['game_id']."';";
				$rr = run_query($qq);

				if (mysql_numrows($r) > 0) {
					$user_game = mysql_fetch_array($rr);

					if ($user_game['paid_invoice_id'] > 0) {
						if ($invoice['invoice_id'] != $user_game['paid_invoice_id']) {
							$qq = "UPDATE currency_invoices SET status='pending_refund' WHERE invoice_id='".$invoice['invoice_id']."';";
							$rr = run_query($qq);
						}
					}
					else {
						$qq = "UPDATE user_games SET paid_invoice_id='".$invoice['invoice_id']."', payment_required=0 WHERE user_game_id='".$user_game['user_game_id']."';";
						$rr = run_query($qq);

						$giveaway = new_game_giveaway($game, $invoice['user_id'], 'initial_purchase', false);
					}
				}
			}
		}
	}
	
	$q = "SELECT * FROM game_buyins gb JOIN invoice_addresses a ON gb.invoice_address_id=a.invoice_address_id WHERE gb.status IN ('unpaid','unconfirmed') AND gb.expire_time >= ".time().";";
	$r = run_query($q);
	echo "Checking ".mysql_numrows($r)." buyins.<br/>\n";
	
	while ($buyin = mysql_fetch_array($r)) {
		$confirm_it = false;

		$qq = "SELECT SUM(gb.unconfirmed_amount_paid) FROM game_buyins gb JOIN invoice_addresses a ON gb.invoice_address_id=a.invoice_address_id WHERE gb.buyin_id != '".$buyin['buyin_id']."' AND a.invoice_address_id='".$buyin['invoice_address_id']."';";
		$rr = run_query($qq);
		$existing_bal = mysql_fetch_row($rr);
		$existing_bal = floatval($existing_bal[0]);
		
		$api_unconfirmed_balance = json_decode(file_get_contents('https://blockchain.info/q/addressbalance/'.$buyin['pub_key'].'?confirmations=0'))/pow(10,8);
		$api_confirmed_balance = json_decode(file_get_contents('https://blockchain.info/q/addressbalance/'.$buyin['pub_key'].'?confirmations=1'))/pow(10,8);

		$unconfirmed_added = $api_unconfirmed_balance - $existing_bal;
		$confirmed_added = $api_confirmed_balance - $existing_bal;
		
		$qq = "UPDATE game_buyins SET confirmed_amount_paid='".$api_confirmed_balance."', unconfirmed_amount_paid='".$api_unconfirmed_balance."'";
		if ($confirmed_added >= $buyin['pay_amount'] || $confirmed_added >= $buyin['pay_amount']) {
			$qq .= ", status='confirmed'";
			$confirm_it = true;
		}
		else if ($unconfirmed_added > 0) {
			//$qq .= ", status='unconfirmed'";
			$qq .= ", status='confirmed'";
			$confirm_it = true;
		}
		$qq .= " WHERE buyin_id='".$buyin['buyin_id']."';";
		$rr = run_query($qq);

		if ($confirm_it) {
			$qq = "SELECT * FROM games WHERE game_id='".$buyin['game_id']."';";
			$rr = run_query($qq);
			$buyin_game = mysql_fetch_array($rr);
			
			$qq = "SELECT * FROM users WHERE user_id='".$buyin['user_id']."';";
			$rr = run_query($qq);
			$buyin_user = mysql_fetch_array($rr);
			
			$btc_exchange_rate = currency_conversion_rate($buyin['settle_currency_id'], $buyin['pay_currency_id']);
			$invite_amount = $btc_exchange_rate['conversion_rate']*$unconfirmed_added;
			$user_buyin_limit = user_buyin_limit($buyin_game, $buyin_user);
			$invite_amount = min($invite_amount, $user_buyin_limit['user_buyin_limit']);
			$pot_value = pot_value($buyin_game);
			$coins_in_existence = coins_in_existence($buyin_game);
			if ($pot_value > 0) {
				$exchange_rate = ($coins_in_existence/pow(10,8))/$pot_value;
			}
			else $exchange_rate = 0;
			
			$giveaway_coins = floor($invite_amount*$exchange_rate*pow(10,8));
			
			if ($giveaway_coins > 0) {
				$giveaway = new_game_giveaway($buyin_game, $buyin_user['user_id'], 'buyin', $giveaway_coins);
				$invitation = false;
				$success = try_capture_giveaway($buyin_game, $buyin_user, $invitation);
				$qq = "UPDATE game_buyins SET settle_amount='".$invite_amount."', giveaway_id='".$giveaway['giveaway_id']."' WHERE buyin_id='".$buyin['buyin_id']."';";
				$rr = run_query($qq);
			}
		}
	}
	
	$runtime_sec = microtime(true)-$script_start_time;
	$sec_until_refresh = round(60-$runtime_sec);
	if ($sec_until_refresh < 0) $sec_until_refresh = 0;
	
	echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
	echo "Script ran for ".round($runtime_sec, 2)." seconds.<br/>\n";
}
else echo "Error: permission denied.";
?>