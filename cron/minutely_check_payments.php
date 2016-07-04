<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM currency_invoices i JOIN invoice_addresses a ON i.invoice_address_id=a.invoice_address_id WHERE i.status != 'confirmed' AND i.status != 'settled' AND (i.status='unconfirmed' OR i.expire_time >= ".time().");";
	$r = $app->run_query($q);

	echo "Checking ".$r->rowCount()." invoices.<br/>\n";
	
	while ($invoice = $r->fetch()) {
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
		$rr = $app->run_query($qq);

		if ($confirm_it) {
			// Todo: make sure amounts match what's expected to make sure no one tampered with the db since the invoice was generated
			$invoice_exchange_rate = $app->historical_currency_conversion_rate($invoice['settle_price_id'], $invoice['pay_price_id']);
			$expected_pay_amount = round(pow(10,8)*$settle_amount/$invoice_exchange_rate)/pow(10,8);

			$game = new Game($app, $invoice['game_id']);

			if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") {
				$invoice_user = new User($app, $invoice['user_id']);
				
				$invoice_user->ensure_user_in_game($game->db_game['game_id']);

				$qq = "SELECT * FROM user_games WHERE user_id='".$invoice['user_id']."' AND game_id='".$game->db_game['game_id']."';";
				$rr = $app->run_query($qq);

				if ($r->rowCount() > 0) {
					$user_game = $rr->fetch();

					if ($user_game['paid_invoice_id'] > 0) {
						if ($invoice['invoice_id'] != $user_game['paid_invoice_id']) {
							$qq = "UPDATE currency_invoices SET status='pending_refund' WHERE invoice_id='".$invoice['invoice_id']."';";
							$rr = $app->run_query($qq);
						}
					}
					else {
						$qq = "UPDATE user_games SET paid_invoice_id='".$invoice['invoice_id']."', payment_required=0 WHERE user_game_id='".$user_game['user_game_id']."';";
						$rr = $app->run_query($qq);

						$giveaway = $game->new_game_giveaway($invoice['user_id'], 'initial_purchase', false);
					}
				}
			}
		}
	}
	
	$q = "SELECT * FROM game_buyins gb JOIN invoice_addresses a ON gb.invoice_address_id=a.invoice_address_id WHERE gb.status IN ('unpaid','unconfirmed') AND (gb.expire_time >= ".time()." OR gb.status='unconfirmed');";
	$r = $app->run_query($q);
	echo "Checking ".$r->rowCount()." buyins.<br/>\n";
	
	while ($buyin = $r->fetch()) {
		$confirm_it = false;

		$qq = "SELECT SUM(gb.unconfirmed_amount_paid) FROM game_buyins gb JOIN invoice_addresses a ON gb.invoice_address_id=a.invoice_address_id WHERE gb.buyin_id != '".$buyin['buyin_id']."' AND a.invoice_address_id='".$buyin['invoice_address_id']."' AND gb.status='confirmed';";
		$rr = $app->run_query($qq);
		$existing_bal = $rr->fetch(PDO::FETCH_NUM);
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
			$qq .= ", status='unconfirmed'";
		}
		$qq .= " WHERE buyin_id='".$buyin['buyin_id']."';";
		$rr = $app->run_query($qq);
		
		if ($confirm_it) {
			$buyin_game = new Game($app, $buyin['game_id']);
			$buyin_user = new User($app, $buyin['user_id']);
			
			$btc_exchange_rate = $app->currency_conversion_rate($buyin['settle_currency_id'], $buyin['pay_currency_id']);
			
			$invite_amount = $btc_exchange_rate['conversion_rate']*$confirmed_added;
			$user_buyin_limit = $buyin_user->user_buyin_limit($buyin_game);
			if ($buyin_game->db_game['buyin_policy'] != 'unlimited') {
				$invite_amount = min($invite_amount, $user_buyin_limit['user_buyin_limit']);
			}
			$pot_value = $buyin_game->pot_value();
			$coins_in_existence = $buyin_game->coins_in_existence(false);
			
			if ($pot_value > 0) {
				$exchange_rate = ($coins_in_existence/pow(10,8))/$pot_value;
			}
			else $exchange_rate = 0;
			
			$giveaway_coins = floor($invite_amount*$exchange_rate*pow(10,8));
			
			if ($giveaway_coins > 0) {
				$giveaway = $buyin_game->new_game_giveaway($buyin_user->db_user['user_id'], 'buyin', $giveaway_coins);
				$invitation = false;
				$success = $buyin_game->try_capture_giveaway($buyin_user, $invitation);
				$qq = "UPDATE game_buyins SET settle_amount='".$invite_amount."', giveaway_id='".$giveaway['giveaway_id']."' WHERE buyin_id='".$buyin['buyin_id']."';";
				$rr = $app->run_query($qq);
				$qq = "UPDATE game_buyins SET status='unpaid' WHERE invoice_address_id='".$buyin['invoice_address_id']."' AND status='unconfirmed';";
				$rr = $app->run_query($qq);
			}
		}
	}
	
	$runtime_sec = microtime(true)-$script_start_time;
	$sec_until_refresh = round(60-$runtime_sec);
	if ($sec_until_refresh < 0) $sec_until_refresh = 0;
	
	echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
	echo "Script ran for ".round($runtime_sec, 2)." seconds.<br/>\n";
}
else echo "Error: incorrect key supplied in cron/minutely_check_payments.php\n";
?>
