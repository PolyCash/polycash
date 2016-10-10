<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$blockchains = array();
	
	/*$q = "SELECT * FROM user_games ug JOIN currency_invoices i ON ug.current_invoice_id=i.invoice_id JOIN currency_addresses a ON i.currency_address_id=a.currency_address_id JOIN games g ON ug.game_id=g.game_id WHERE i.status != 'confirmed' AND i.status != 'settled' AND (i.status='unconfirmed' OR i.expire_time >= ".time().");";
	$r = $app->run_query($q);
	
	echo "Checking ".$r->rowCount()." invoices.<br/>\n";
	
	while ($invoice = $r->fetch()) {
		$confirm_it = false;
		$currency_address = $app->fetch_currency_address_by_id($invoice['currency_address_id']);
		$app->reset_currency_address($currency_address);
		
		list($unconfirmed_balance, $confirmed_balance) = $app->currency_address_balance($currency_address);
		$confirmed_balance = 0.001;
		$unconfirmed_balance = $confirmed_balance;
		
		$qq = "UPDATE currency_invoices SET confirmed_amount_paid='".$confirmed_balance."', unconfirmed_amount_paid='".$unconfirmed_balance."'";
		if ($unconfirmed_balance >= $invoice['pay_amount'] || $confirmed_balance >= $invoice['pay_amount']) {
			$qq .= ", status='confirmed'";
			$confirm_it = true;
		}
		else if ($unconfirmed_balance > 0) {
			$qq .= ", status='unconfirmed'";
		}
		$qq .= " WHERE invoice_id='".$invoice['invoice_id']."';";
		$rr = $app->run_query($qq);
		
		if ($confirm_it) {
			if (!$blockchains[$invoice['blockchain_id']]) $blockchains[$invoice['blockchain_id']] = new Blockchain($app, $invoice['blockchain_id']);
			$game = new Game($blockchains[$invoice['blockchain_id']], $invoice['game_id']);
			
			if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") {
				$invoice_user = new User($app, $invoice['user_id']);
				$invoice_user->ensure_user_in_game($game->db_game['game_id']);
				
				$qq = "SELECT * FROM user_games WHERE user_id='".$invoice['user_id']."' AND game_id='".$game->db_game['game_id']."';";
				$rr = $app->run_query($qq);
				
				if ($invoice['paid_invoice_id'] > 0) {
					if ($invoice['invoice_id'] != $invoice['paid_invoice_id']) {
						$qq = "UPDATE currency_invoices SET status='pending_refund' WHERE invoice_id='".$invoice['invoice_id']."';";
						$rr = $app->run_query($qq);
					}
				}
				else {
					$qq = "UPDATE user_games SET paid_invoice_id='".$invoice['invoice_id']."', payment_required=0 WHERE user_game_id='".$invoice['user_game_id']."';";
					$rr = $app->run_query($qq);
					
					$coins_in_existence = $game->coins_in_existence(false);
					$pot_value = $game->pot_value();
					
					if ($pot_value > 0) {
						$exchange_rate = ($coins_in_existence/pow(10,8))/$pot_value;
						$target_amount = $exchange_rate*$unconfirmed_balance*pow(10,8);
					}
					else $target_amount = $this->db_game['giveaway_amount'];
					
					$giveaway = $game->new_game_giveaway($invoice, $target_amount, $currency_address);
					
					$qq = "UPDATE user_games SET current_invoice_id=NULL WHERE user_game_id='".$invoice['user_game_id']."';";
					$rr = $app->run_query($qq);
				}
			}
		}
	}*/
}
else echo "Error: incorrect key supplied in cron/minutely_check_payments.php\n";
?>
