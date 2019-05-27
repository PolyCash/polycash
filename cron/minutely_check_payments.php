<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "minutely_check_payments";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = array();
		
		$q = "SELECT *, ug.user_id AS user_id, ug.account_id AS user_game_account_id FROM user_games ug JOIN currency_invoices i ON ug.user_game_id=i.user_game_id JOIN addresses a ON i.address_id=a.address_id JOIN games g ON ug.game_id=g.game_id WHERE i.status IN ('unpaid','unconfirmed') AND (i.status='unconfirmed' OR i.expire_time >= ".time().") AND i.invoice_type != 'sellout' GROUP BY a.address_id;";
		$r = $app->run_query($q);
		
		if ($print_debug) echo "Checking ".$r->rowCount()." addresses.<br/>\n";
		
		while ($invoice_address = $r->fetch()) {
			$transaction_id = false;
			
			if (empty($blockchains[$invoice_address['blockchain_id']])) $blockchains[$invoice_address['blockchain_id']] = new Blockchain($app, $invoice_address['blockchain_id']);
			$game = new Game($blockchains[$invoice_address['blockchain_id']], $invoice_address['game_id']);
			
			$pay_currency = $app->fetch_currency_by_id($invoice_address['pay_currency_id']);
			if (empty($blockchains[$pay_currency['blockchain_id']])) $blockchains[$pay_currency['blockchain_id']] = new Blockchain($app, $pay_currency['blockchain_id']);
			$pay_blockchain = $blockchains[$pay_currency['blockchain_id']];
			
			$address_balance = (float) $pay_blockchain->address_balance_at_block($invoice_address, false);
			
			$qq = "SELECT SUM(confirmed_amount_paid) FROM currency_invoices WHERE address_id='".$invoice_address['address_id']."' AND status IN('confirmed','settled','pending_refund','refunded');";
			$rr = $app->run_query($qq);
			$preexisting_balance = $rr->fetch()['SUM(confirmed_amount_paid)'];
			
			$amount_paid = ($address_balance-$preexisting_balance)/pow(10, $pay_blockchain->db_blockchain['decimal_places']);
			
			if ($print_debug) echo $invoice_address['address']." ".$address_balance.", paid: ".$amount_paid."<br/>\n";
			
			if ($amount_paid > 0) {
				if ($invoice_address['invoice_type'] == "sale_buyin") {
					$escrow_value = $game->escrow_value_in_currency($invoice_address['pay_currency_id']);
					$coins_in_existence = ($game->coins_in_existence(false)+$game->pending_bets())/pow(10, $game->db_game['decimal_places']);
					$exchange_rate = $coins_in_existence/$escrow_value;
					$game_coins = $amount_paid*$exchange_rate;
					$sale_game_account = $game->check_set_game_sale_account($thisuser);
					$sale_game_account_balance = $game->account_balance($sale_game_account['account_id'])/pow(10, $game->db_game['decimal_places']);
					$game_coins = min($game_coins, $sale_game_account_balance);
					
					$qq = "SELECT io.io_id, io.address_id, io.amount, SUM(gio.colored_amount) AS game_amount FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id JOIN address_keys ak ON ak.address_id=io.address_id WHERE ak.account_id='".$sale_game_account['account_id']."' AND io.spend_status IN ('unspent','unconfirmed') AND gio.game_id='".$game->db_game['game_id']."' GROUP BY io.io_id;";
					$rr = $app->run_query($qq);
					
					$spend_ios = array();
					$spend_io_ids = array();
					$game_coins_in = 0;
					$chain_coins_in = 0;
					
					while ($spend_io = $rr->fetch()) {
						if ($game_coins_in < $game_coins) {
							array_push($spend_ios, $spend_io);
							array_push($spend_io_ids, $spend_io['io_id']);
							$game_coins_in += $spend_io['game_amount']/pow(10, $game->db_game['decimal_places']);
							$chain_coins_in += $spend_io['amount']/pow(10, $game->blockchain->db_blockchain['decimal_places']);
						}
					}
					
					if ($game_coins_in >= $game_coins) {
						$chain_fee = 0.0001;
						$chain_coins_per_game_coin = ($chain_coins_in-$chain_fee)/$game_coins_in;
						$send_chain_coins = ceil($game_coins*$chain_coins_per_game_coin*pow(10, $game->blockchain->db_blockchain['decimal_places']));
						
						$qq = "SELECT * FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id WHERE ak.account_id='".$invoice_address['user_game_account_id']."' AND a.is_destroy_address=0 AND a.is_separator_address=0 ORDER BY a.option_index ASC LIMIT 1;";
						$rr = $app->run_query($qq);
						
						if ($rr->rowCount() > 0) {
							$pay_to_address = $rr->fetch();
							$amounts = array($send_chain_coins);
							$address_ids = array($pay_to_address['address_id']);
							
							if ($chain_coins_in*pow(10, $game->blockchain->db_blockchain['decimal_places']) > $send_chain_coins+($chain_fee*pow(10, $game->blockchain->db_blockchain['decimal_places']))) {
								$overshoot_chain_coins = ($chain_coins_in*pow(10, $game->blockchain->db_blockchain['decimal_places'])) - $send_chain_coins - ($chain_fee*pow(10, $game->blockchain->db_blockchain['decimal_places']));
								$overshoot_address_id = $spend_ios[0]['address_id'];
								
								array_push($amounts, $overshoot_chain_coins);
								array_push($address_ids, $overshoot_address_id);
							}
							
							$error_message = false;
							$transaction_id = $game->blockchain->create_transaction("transaction", $amounts, false, $spend_io_ids, $address_ids, $chain_fee*pow(10, $game->blockchain->db_blockchain['decimal_places']), $error_message);
							
							if ($transaction_id) {
								$transaction = $app->fetch_transaction_by_id($transaction_id);
								if ($print_debug) echo "Created tx: ".$transaction['tx_hash']."\n";
							}
							else if ($print_debug) echo "TX failed: ".$error_message."\n";
						}
						else if ($print_debug) echo "Couldn't find the address to pay to.\n";
					}
					else if ($print_debug) echo "Not enough game coins available.\n";
				}
				else {
					$buyin_amount = (int)($amount_paid/2);
					if ($invoice_address['buyin_amount'] > 0) $buyin_amount = $invoice_address['buyin_amount']*pow(10,$game->blockchain->db_blockchain['decimal_places']);
					
					$fee_amount = false;
					if ($invoice_address['strategy_id'] > 0) {
						$qq = "SELECT * FROM user_strategies WHERE strategy_id='".$invoice_address['strategy_id']."';";
						$rr = $app->run_query($qq);
						
						if ($rr->rowCount() > 0) {
							$strategy = $rr->fetch();
							
							$fee_amount = (int) ($strategy['transaction_fee']*pow(10,$game->blockchain->db_blockchain['decimal_places']));
						}
					}
					if (!$fee_amount) $fee_amount = (int) (0.0001*pow(10,$game->blockchain->db_blockchain['decimal_places']));
					
					$color_amount = $amount_paid - $fee_amount - $buyin_amount;
					
					if ($print_debug) echo "preexisting: ".$app->format_bignum($preexisting_balance/pow(10,$game->blockchain->db_blockchain['decimal_places'])).", amount paid: ".$app->format_bignum($amount_paid/pow(10,$game->blockchain->db_blockchain['decimal_places'])).", fee: ".$app->format_bignum($fee_amount/pow(10,$game->blockchain->db_blockchain['decimal_places'])).", buyin: ".$app->format_bignum($buyin_amount/pow(10,$game->blockchain->db_blockchain['decimal_places'])).", color: ".$app->format_bignum($color_amount/pow(10,$game->blockchain->db_blockchain['decimal_places']))."<br/>\n";
					
					if ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0) {
						$invoice_user = new User($app, $invoice_address['user_id']);
						$user_game = $invoice_user->ensure_user_in_game($game, false);
						$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
						
						$game_currency_account = $app->fetch_account_by_id($user_game['account_id']);
						
						$io_ids = array();
						$qq = "SELECT * FROM transaction_ios WHERE blockchain_id='".$invoice_address['blockchain_id']."' AND address_id='".$invoice_address['address_id']."' AND spend_status='unspent';";
						$rr = $app->run_query($qq);
						while ($io = $rr->fetch()) {
							array_push($io_ids, $io['io_id']);
						}
						
						$address_ids = array($escrow_address['address_id'], $game_currency_account['current_address_id']);
						
						$error_message = false;
						$transaction_id = $game->blockchain->create_transaction("transaction", array($buyin_amount, $color_amount), false, $io_ids, $address_ids, array(0, 0), $fee_amount, $error_message);
					}
					else if ($print_debug) echo "fee: ".$app->format_bignum($fee_amount/pow(10,$game->blockchain->db_blockchain['decimal_places'])).", buyin: ".$app->format_bignum($buyin_amount/pow(10,$game->blockchain->db_blockchain['decimal_places'])).", color: ".$app->format_bignum($color_amount/pow(10,$game->blockchain->db_blockchain['decimal_places']))."<br/>\n";
				}
				
				if ($transaction_id) {
					if ($print_debug) echo "created tx #".$transaction_id."\n";
					
					$qq = "UPDATE currency_invoices SET confirmed_amount_paid='".$amount_paid/pow(10,$game->blockchain->db_blockchain['decimal_places'])."', unconfirmed_amount_paid='".$amount_paid/pow(10,$game->blockchain->db_blockchain['decimal_places'])."', status='confirmed' WHERE invoice_id='".$invoice_address['invoice_id']."';";
					$rr = $app->run_query($qq);
				}
				else if ($print_debug) echo "failed to create a transaction.\n";
			}
			else if ($print_debug) echo "amount paid: ".$amount_paid."<br/>\n";
		}
		
		// Handle sellout invoices
		$q = "SELECT *, ug.user_id AS user_id, ug.account_id AS user_game_account_id FROM user_games ug JOIN currency_invoices i ON ug.user_game_id=i.user_game_id JOIN addresses a ON i.address_id=a.address_id JOIN games g ON ug.game_id=g.game_id WHERE i.status IN ('unpaid','unconfirmed') AND (i.status='unconfirmed' OR i.expire_time >= ".time().") AND i.invoice_type='sellout' GROUP BY a.address_id;";
		$r = $app->run_query($q);
		
		while ($invoice_address = $r->fetch()) {
			if (empty($blockchains[$invoice_address['blockchain_id']])) $blockchains[$invoice_address['blockchain_id']] = new Blockchain($app, $invoice_address['blockchain_id']);
			$game = new Game($blockchains[$invoice_address['blockchain_id']], $invoice_address['game_id']);
			
			$blockchain_currency = $app->fetch_currency_by_id($invoice_address['pay_currency_id']);
			$blockchain = new Blockchain($app, $blockchain_currency['blockchain_id']);
			
			$address_balance = (float) $game->address_balance_at_block($invoice_address, false);
			
			$qq = "SELECT SUM(confirmed_amount_paid) FROM currency_invoices WHERE address_id='".$invoice_address['address_id']."' AND status IN('confirmed','settled','pending_refund','refunded');";
			$rr = $app->run_query($qq);
			$preexisting_balance = $rr->fetch()['SUM(confirmed_amount_paid)']*pow(10, $game->db_game['decimal_places']);
			
			$amount_paid = $address_balance-$preexisting_balance;
			
			if ($print_debug) echo $invoice_address['address']." ".$address_balance.", paid: ".$amount_paid."\n";
			
			if ($amount_paid > 0) {
				$escrow_value = $game->escrow_value_in_currency($invoice_address['pay_currency_id']);
				$coins_in_existence = ($game->coins_in_existence(false)+$game->pending_bets())/pow(10, $game->db_game['decimal_places']);
				$exchange_rate = $coins_in_existence/$escrow_value;
				
				$blockchain_amount = $amount_paid/$exchange_rate;
				$sale_blockchain_account = $game->check_set_blockchain_sale_account($thisuser, $blockchain_currency);
				$sale_blockchain_account_balance = $blockchain->account_balance($sale_blockchain_account['account_id']);
				$fee_amount = $invoice_address['fee_amount']*pow(10, $blockchain->db_blockchain['decimal_places']);
				$blockchain_amount = max(0, min($blockchain_amount, $sale_blockchain_account_balance)-$fee_amount);
				
				if ($blockchain_amount > 0) {
					$blockchain_cost = $blockchain_amount+$fee_amount;
					
					if ($print_debug) echo "exch: $exchange_rate, pay: $blockchain_amount, tx fee: ".$invoice_address['fee_amount']."<br/>\n";
					
					$qq = "SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$sale_blockchain_account['account_id']."' ORDER BY io.amount ASC;";
					$rr = $app->run_query($qq);
					
					$io_amount_sum = 0;
					$ios = array();
					$io_ids = array();
					$keep_looping = true;
					
					while ($keep_looping && $io = $rr->fetch()) {
						$io_amount_sum += $io['amount'];
						array_push($io_ids, $io['io_id']);
						array_push($ios, $io);
						
						if ($io_amount_sum >= $blockchain_cost) $keep_looping = false;
					}
					
					$io_nonfee_amount = $io_amount_sum-$fee_amount;
					
					$amounts = array($blockchain_amount);
					$address_ids = array($invoice_address['receive_address_id']);
					
					if ($io_nonfee_amount > $blockchain_cost) {
						$remainder_amount = $io_nonfee_amount-$blockchain_amount;
						array_push($amounts, $remainder_amount);
						array_push($address_ids, $ios[0]['address_id']);
					}
					
					$error_message = false;
					$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, $io_ids, $address_ids, $fee_amount, $error_message);
					
					if ($transaction_id) {
						$qq = "UPDATE currency_invoices SET confirmed_amount_paid='".$amount_paid/pow(10,$game->blockchain->db_blockchain['decimal_places'])."', unconfirmed_amount_paid='".$amount_paid/pow(10,$game->blockchain->db_blockchain['decimal_places'])."', status='confirmed' WHERE invoice_id='".$invoice_address['invoice_id']."';";
						$rr = $app->run_query($qq);
					}
					
					if ($print_debug) {
						if ($transaction_id) echo "Created tx #".$transaction_id."\n";
						else echo "Failed to create the transaction: ".$error_message."\n";
					}
				}
				else if ($print_debug) echo "Invalid payment amount.\n";
			}
			else if ($print_debug) echo "Nothing was paid.\n";
			
			if ($print_debug) echo "\n";
		}
		
		// Broadcast sellout refund transactions for games where this node owns the escrow address
		$q = "SELECT * FROM games WHERE game_status='running';";
		$r = $app->run_query($q);
		
		while ($db_game = $r->fetch()) {
			if (empty($blockchains[$db_game['blockchain_id']])) $blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
			$escrow_address = $blockchains[$db_game['blockchain_id']]->create_or_fetch_address($db_game['escrow_address'], true, false, false, false, false);
			
			if ($escrow_address['is_mine'] == 1) {
				$this_game = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
				$required_block = $blockchains[$db_game['blockchain_id']]->last_block_id()+1-(int)$db_game['sellout_confirmations'];
				
				$qq = "SELECT * FROM game_sellouts WHERE game_id='".$db_game['game_id']."' AND out_tx_hash IS NULL AND in_block_id <= ".$required_block.";";
				$rr = $app->run_query($qq);
				
				while ($unprocessed_sellout = $rr->fetch()) {
					$sellout_transaction = $this_game->blockchain->fetch_transaction_by_hash($unprocessed_sellout['in_tx_hash']);
					
					if ($sellout_transaction) {
						$refund_amount = $unprocessed_sellout['amount_out'] - $unprocessed_sellout['fee_amount'];
						
						if ($print_debug) echo "process sellout ".$unprocessed_sellout['in_tx_hash']."<br/>\n";
						
						$input_sum = 0;
						$io_ids = array();
						
						$qqq = "SELECT * FROM transaction_ios WHERE blockchain_id='".$db_game['blockchain_id']."' AND address_id='".$escrow_address['address_id']."' AND spend_status='unspent' AND create_block_id IS NOT NULL ORDER BY create_block_id ASC;";
						$rrr = $app->run_query($qqq);
						
						while ($input_sum < $unprocessed_sellout['amount_out'] && $escrow_utxo = $rrr->fetch()) {
							$input_sum += $escrow_utxo['amount'];
							array_push($io_ids, $escrow_utxo['io_id']);
						}
						
						if ($input_sum >= $unprocessed_sellout['amount_out']) {
							$amounts = explode(",", $unprocessed_sellout['out_amounts']);
							$address_ids = array();
							
							$qqq = "SELECT * FROM transaction_ios WHERE spend_transaction_id='".$sellout_transaction['transaction_id']."';";
							$rrr = $app->run_query($qqq);
							
							while ($in_io = $rrr->fetch()) {
								array_push($address_ids, $in_io['address_id']);
							}
							
							$remainder_amount = $input_sum - $refund_amount - $unprocessed_sellout['fee_amount'];
							if ($remainder_amount > 0) {
								array_push($amounts, $remainder_amount);
								array_push($address_ids, $escrow_address['address_id']);
							}
							
							$error_message = false;
							$transaction_id = $this_game->create_transaction(false, $amounts, false, false, 'transaction', $io_ids, $address_ids, false, $unprocessed_sellout['fee_amount'], $error_message);
							
							if ($transaction_id) {
								$db_transaction = $app->fetch_transaction_by_id($transaction_id);
								
								$qqq = "UPDATE game_sellouts SET out_tx_hash=".$app->quote_escape($db_transaction['tx_hash'])." WHERE sellout_id='".$unprocessed_sellout['sellout_id']."';";
								$rrr = $app->run_query($qqq);
								
								if ($print_debug) echo "Created sellout refund transaction ".$db_transaction['tx_hash']."<br/>\n";
							}
							else {
								if ($print_debug) echo "Failed to add transaction for sellout #".$unprocessed_sellout['sellout_id'].": ".$error_message."<br/>\n";
							}
						}
					}
				}
			}
		}
	}
	else echo "This process is already running.\n";
}
else echo "Error: incorrect key supplied in cron/minutely_check_payments.php\n";
?>
