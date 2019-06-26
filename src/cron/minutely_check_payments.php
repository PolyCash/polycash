<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

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
		
		$ref_user = false;
		$blockchains = [];
		
		$buyin_invoices = $app->run_query("SELECT *, ug.user_id AS user_id, ug.account_id AS user_game_account_id FROM user_games ug JOIN currency_invoices i ON ug.user_game_id=i.user_game_id JOIN addresses a ON i.address_id=a.address_id JOIN games g ON ug.game_id=g.game_id WHERE i.status IN ('unpaid','unconfirmed','confirmed') AND (i.status='unconfirmed' OR i.expire_time >= :current_time) AND i.invoice_type != 'sellout' GROUP BY a.address_id;", ['current_time'=>time()]);
		
		if ($print_debug) echo "Checking ".$buyin_invoices->rowCount()." buyin addresses.<br/>\n";
		
		while ($invoice_address = $buyin_invoices->fetch()) {
			$transaction_id = false;
			
			if (empty($blockchains[$invoice_address['blockchain_id']])) $blockchains[$invoice_address['blockchain_id']] = new Blockchain($app, $invoice_address['blockchain_id']);
			$game = new Game($blockchains[$invoice_address['blockchain_id']], $invoice_address['game_id']);
			
			$last_block_id = $game->blockchain->last_block_id();
			$round_id = $game->block_to_round($last_block_id+1);
			
			if ($game->last_block_id() == $last_block_id) {
				$pay_currency = $app->fetch_currency_by_id($invoice_address['pay_currency_id']);
				if (empty($blockchains[$pay_currency['blockchain_id']])) $blockchains[$pay_currency['blockchain_id']] = new Blockchain($app, $pay_currency['blockchain_id']);
				$pay_blockchain = $blockchains[$pay_currency['blockchain_id']];
				
				$address_balance_float = (float)($pay_blockchain->total_paid_to_address($invoice_address, true)/pow(10, $pay_blockchain->db_blockchain['decimal_places']));
				
				$preexisting_balance_float = (float)($app->run_query("SELECT SUM(confirmed_amount_paid) FROM currency_invoices WHERE address_id=:address_id AND status IN('confirmed','settled','pending_refund','refunded');", [
					'address_id' => $invoice_address['address_id']
				])->fetch()['SUM(confirmed_amount_paid)']);
				
				$amount_paid_float = $address_balance_float-$preexisting_balance_float;
				
				if ($print_debug) echo $invoice_address['address']." ".$address_balance_float.", paid: ".$amount_paid_float."<br/>\n";
				
				if ($amount_paid_float > 0) {
					$pay_tx_hash = false;
					$pay_out_index = false;
					$pay_game_out_index = false;
					
					if ($invoice_address['invoice_type'] == "sale_buyin") {
						$escrow_value_float = $game->escrow_value_in_currency($invoice_address['pay_currency_id']);
						$coins_in_existence = $game->coins_in_existence(false, false)+$game->pending_bets(false);
						$exchange_rate = $coins_in_existence/pow(10, $game->db_game['decimal_places'])/$escrow_value_float;
						$buyin_amount_int = ceil($amount_paid_float*$exchange_rate*pow(10, $game->db_game['decimal_places']));
						$sale_game_account = $game->check_set_game_sale_account($ref_user);
						$sale_game_account_balance_int = $game->account_balance($sale_game_account['account_id']);
						
						$sale_spend_ios = $app->spendable_ios_in_account($sale_game_account['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
						
						$spend_ios = [];
						$spend_io_ids = [];
						$game_coins_in = 0;
						$chain_coins_in = 0;
						
						while ($spend_io = $sale_spend_ios->fetch()) {
							if ($game_coins_in < $buyin_amount_int) {
								array_push($spend_ios, $spend_io);
								array_push($spend_io_ids, $spend_io['io_id']);
								$game_coins_in += $spend_io['coins'];
								$chain_coins_in += $spend_io['amount'];
							}
						}
						
						if ($game_coins_in >= $buyin_amount_int) {
							$chain_fee_float = 0.0001;
							$chain_fee_int = (int)($chain_fee_float*pow(10, $game->blockchain->db_blockchain['decimal_places']));
							
							$chain_coins_per_game_coin = ($chain_coins_in-$chain_fee_int)/$game_coins_in;
							$send_chain_coins = ceil($buyin_amount_int*$chain_coins_per_game_coin);
							
							$pay_to_address = $app->any_normal_address_in_account($invoice_address['user_game_account_id']);
							
							if ($pay_to_address) {
								$amounts = array($send_chain_coins);
								$address_ids = array($pay_to_address['address_id']);
								
								$remainder_error = false;
								if ($chain_coins_in > $send_chain_coins+$chain_fee_int) {
									$remainder_chain_coins = $chain_coins_in-$send_chain_coins-$chain_fee_int;
									$remainder_address = $app->new_normal_address_key($sale_game_account['currency_id'], $sale_game_account);
									
									if ($remainder_address) {
										array_push($amounts, $remainder_chain_coins);
										array_push($address_ids, $remainder_address['address_id']);
									}
									else $remainder_error = true;
								}
								
								if (!$remainder_error) {
									$error_message = false;
									$transaction_id = $game->blockchain->create_transaction("transaction", $amounts, false, $spend_io_ids, $address_ids, $chain_fee_int, $error_message);
									
									if ($transaction_id) {
										$transaction = $app->fetch_transaction_by_id($transaction_id);
										
										$pay_out_index = 0;
										$pay_game_out_index = 0;
										$pay_tx_hash = $transaction['tx_hash'];
										
										if ($print_debug) echo "Created tx: ".$transaction['tx_hash']."\n";
									}
									else if ($print_debug) echo "TX failed: ".$error_message."\n";
								}
								else if ($print_debug) echo "Couldn't find an remainder address.\n";
							}
							else if ($print_debug) echo "Couldn't find the address to pay to.\n";
						}
						else if ($print_debug) echo "Not enough game coins available.\n";
					}
					else {
						if ($invoice_address['buyin_amount'] > 0) $buyin_amount_float = $invoice_address['buyin_amount'];
						else $buyin_amount_float = (int)($amount_paid_float/2);
						
						$buyin_amount_int = ceil($buyin_amount_float*pow(10, $game->db_game['decimal_places']));
						
						$strategy = $app->fetch_strategy_by_id($invoice_address['strategy_id']);
						if ($strategy) $fee_amount_int = $strategy['transaction_fee']*pow(10,$game->blockchain->db_blockchain['decimal_places']);
						else $fee_amount_int = 0.0001*pow(10,$game->blockchain->db_blockchain['decimal_places']);
						
						$amount_paid_int = (int) ($amount_paid_float*pow(10,$game->blockchain->db_blockchain['decimal_places']));
						
						$amount_to_color = $amount_paid_int - $fee_amount_int - $buyin_amount_int;
						
						if ($fee_amount_int > 0 && $buyin_amount_int > 0 && $amount_to_color > 0) {
							$invoice_user = new User($app, $invoice_address['user_id']);
							
							$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
							$user_address = $app->any_normal_address_in_account($invoice_address['user_game_account_id']);
							
							if ($user_address) {
								$io_ids = [];
								
								/*$escrow_spendable_ios = $app->run_query("SELECT * FROM transaction_ios WHERE blockchain_id=:blockchain_id AND address_id=:address_id AND spend_status='unspent';", [
									'blockchain_id' => $invoice_address['blockchain_id'],
									'address_id' => $invoice_address['address_id']
								]);
								
								while ($io = $escrow_spendable_ios->fetch()) {
									array_push($io_ids, $io['io_id']);
								}
								
								$address_ids = array($escrow_address['address_id'], $game_currency_account['current_address_id']);
								
								$error_message = false;
								$transaction_id = $game->blockchain->create_transaction("transaction", array($buyin_amount, $amount_to_color), false, $io_ids, $address_ids, $fee_amount, $error_message);
								
								if ($transaction_id) {
									$transaction = $app->fetch_transaction_by_id($transaction_id);
									$pay_out_index = 1;
									$pay_game_out_index = 0;
									$pay_tx_hash = $transaction['tx_hash'];
								}*/
								if ($print_debug) echo "Direct game buyins are currently disabled.\n";
							}
							else if ($print_debug) echo "Failed to find an address to pay to for this user.\n";
						}
						else if ($print_debug) echo "fee: ".$app->format_bignum($fee_amount_int).", buyin: ".$app->format_bignum($buyin_amount_int).", color: ".$app->format_bignum($amount_to_color)."<br/>\n";
					}
					
					if ($transaction_id) {
						if ($print_debug) echo "created tx #".$transaction_id."\n";
						
						$app->run_query("UPDATE currency_invoices SET confirmed_amount_paid=:address_balance_float, unconfirmed_amount_paid=:address_balance_float, status='confirmed' WHERE invoice_id=:invoice_id;", [
							'address_balance_float' => $address_balance_float,
							'invoice_id' => $invoice_address['invoice_id']
						]);
						
						$app->run_query("INSERT INTO currency_invoice_ios SET invoice_id=:invoice_id, tx_hash=:tx_hash, out_index=:out_index, game_out_index=:game_out_index;", [
							'invoice_id' => $invoice_address['invoice_id'],
							'tx_hash' => $pay_tx_hash,
							'out_index' => $pay_out_index,
							'game_out_index' => $pay_game_out_index
						]);
					}
					else if ($print_debug) echo "failed to create a transaction.\n";
				}
				else if ($print_debug) echo "amount paid: ".$amount_paid_float."<br/>\n";
			}
		}
		
		$sellout_invoices = $app->run_query("SELECT *, ug.user_id AS user_id, ug.account_id AS user_game_account_id FROM user_games ug JOIN currency_invoices i ON ug.user_game_id=i.user_game_id JOIN addresses a ON i.address_id=a.address_id JOIN games g ON ug.game_id=g.game_id WHERE i.status IN ('unpaid','unconfirmed','confirmed') AND (i.status='unconfirmed' OR i.expire_time >= :current_time) AND i.invoice_type='sellout' GROUP BY a.address_id;", ['current_time'=>time()]);
		
		while ($invoice_address = $sellout_invoices->fetch()) {
			if (empty($blockchains[$invoice_address['blockchain_id']])) $blockchains[$invoice_address['blockchain_id']] = new Blockchain($app, $invoice_address['blockchain_id']);
			$game = new Game($blockchains[$invoice_address['blockchain_id']], $invoice_address['game_id']);
			
			if ($game->last_block_id() == $game->blockchain->last_block_id()) {
				$sellout_currency = $app->fetch_currency_by_id($invoice_address['pay_currency_id']);
				$sellout_blockchain = new Blockchain($app, $sellout_currency['blockchain_id']);
				
				$address_balance_float = (float)($game->total_paid_to_address($invoice_address, true)/pow(10, $game->db_game['decimal_places']));
				
				$preexisting_balance_float = (float)($app->run_query("SELECT SUM(confirmed_amount_paid) FROM currency_invoices WHERE address_id=:address_id AND status IN('confirmed','settled','pending_refund','refunded');", [
					'address_id' => $invoice_address['address_id']
				])->fetch()['SUM(confirmed_amount_paid)']);
				
				$amount_paid_float = $address_balance_float-$preexisting_balance_float;
				
				if ($print_debug) echo $invoice_address['address']." ".$address_balance_float.", paid: ".$amount_paid_float."\n";
				
				if ($amount_paid_float > 0) {
					$escrow_value_float = $game->escrow_value_in_currency($sellout_currency['currency_id']);
					$coins_in_existence = $game->coins_in_existence(false, false)+$game->pending_bets(false);
					$exchange_rate = $coins_in_existence/pow(10, $game->db_game['decimal_places'])/$escrow_value_float;
					
					$sellout_account = $game->check_set_blockchain_sale_account($ref_user, $sellout_currency);
					$sellout_account_balance_int = $sellout_blockchain->account_balance($sellout_account['account_id']);
					
					$sellout_amount_int = (int)($amount_paid_float/$exchange_rate*pow(10, $sellout_blockchain->db_blockchain['decimal_places']));
					$fee_amount_int = (int)($invoice_address['fee_amount']*pow(10, $sellout_blockchain->db_blockchain['decimal_places']));
					$sellout_amount_int = max(0, min($sellout_amount_int, $sellout_account_balance_int)-$fee_amount_int);
					
					if ($sellout_amount_int > 0) {
						$sellout_cost_int = $sellout_amount_int+$fee_amount_int;
						
						if ($print_debug) echo "exchange rate: $exchange_rate, pay: $sellout_amount_int, tx fee: $fee_amount_int<br/>\n";
						
						$spend_ios = $app->run_query("SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status IN ('unspent','unconfirmed') AND k.account_id=:account_id ORDER BY io.amount ASC;", ['account_id'=>$sellout_account['account_id']]);
						
						$io_amount_sum = 0;
						$ios = [];
						$io_ids = [];
						$keep_looping = true;
						
						while ($keep_looping && $io = $spend_ios->fetch()) {
							$io_amount_sum += $io['amount'];
							array_push($io_ids, $io['io_id']);
							array_push($ios, $io);
							
							if ($io_amount_sum >= $sellout_cost_int) $keep_looping = false;
						}
						
						$io_nonfee_amount = $io_amount_sum-$fee_amount_int;
						
						$amounts = array($sellout_amount_int);
						$address_ids = array($invoice_address['receive_address_id']);
						
						$remainder_error = false;
						if ($io_nonfee_amount > $sellout_amount_int) {
							$remainder_amount = $io_nonfee_amount-$sellout_amount_int;
							$remainder_address = $app->new_normal_address_key($sellout_account['currency_id'], $sellout_account);
							
							if ($remainder_address) {
								array_push($amounts, $remainder_amount);
								array_push($address_ids, $remainder_address['address_id']);
							}
							else $remainder_error = true;
						}
						
						if (!$remainder_error) {
							$error_message = false;
							$transaction_id = $sellout_blockchain->create_transaction("transaction", $amounts, false, $io_ids, $address_ids, $fee_amount_int, $error_message);
							
							if ($transaction_id) {
								$transaction = $app->fetch_transaction_by_id($transaction_id);
								
								$app->run_query("UPDATE currency_invoices SET confirmed_amount_paid=:address_balance_float, unconfirmed_amount_paid=:address_balance_float, status='confirmed' WHERE invoice_id=:invoice_id;", [
									'address_balance_float' => $address_balance_float,
									'invoice_id' => $invoice_address['invoice_id']
								]);
								
								$app->run_query("INSERT INTO currency_invoice_ios SET invoice_id=:invoice_id, tx_hash=:tx_hash, out_index=0, game_out_index=NULL;", [
									'invoice_id' => $invoice_address['invoice_id'],
									'tx_hash' => $transaction['tx_hash']
								]);
							}
							
							if ($print_debug) {
								if ($transaction_id) echo "Created tx #".$transaction_id."\n";
								else echo "Failed to create the transaction: ".$error_message."\n";
							}
						}
						else if ($print_debug) echo "Couldn't find an remainder address.\n";
					}
					else if ($print_debug) echo "Invalid payment amount.\n";
				}
				else if ($print_debug) echo "Nothing was paid.\n";
				
				if ($print_debug) echo "\n";
			}
		}
		
		// Broadcast sellout refund transactions for games where this node owns the escrow address
		// This functionality is currently disabled.
		
		/*
		$db_running_games = $app->fetch_running_games();
		
		while ($db_game = $db_running_games->fetch()) {
			if (empty($blockchains[$db_game['blockchain_id']])) $blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
			$escrow_address = $blockchains[$db_game['blockchain_id']]->create_or_fetch_address($db_game['escrow_address'], true, false, false, false, false);
			
			if ($escrow_address['is_mine'] == 1) {
				$this_game = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
				$required_block = $blockchains[$db_game['blockchain_id']]->last_block_id()+1-(int)$db_game['sellout_confirmations'];
				
				$unprocessed_sellouts = $app->run_query("SELECT * FROM game_sellouts WHERE game_id=:game_id AND out_tx_hash IS NULL AND in_block_id <= :block_id;", [
					'game_id' => $db_game['game_id'],
					'block_id' => $required_block
				]);
				
				while ($unprocessed_sellout = $unprocessed_sellouts->fetch()) {
					$sellout_transaction = $this_game->blockchain->fetch_transaction_by_hash($unprocessed_sellout['in_tx_hash']);
					
					if ($sellout_transaction) {
						$refund_amount = $unprocessed_sellout['amount_out'] - $unprocessed_sellout['fee_amount'];
						
						if ($print_debug) echo "process sellout ".$unprocessed_sellout['in_tx_hash']."<br/>\n";
						
						$input_sum = 0;
						$io_ids = [];
						
						$escrow_inputs = $app->run_query("SELECT * FROM transaction_ios WHERE blockchain_id=:blockchain_id AND address_id=:address_id AND spend_status='unspent' AND create_block_id IS NOT NULL ORDER BY create_block_id ASC;", [
							'blockchain_id' => $db_game['blockchain_id'],
							'address_id' => $escrow_address['address_id']
						]);
						
						while ($input_sum < $unprocessed_sellout['amount_out'] && $escrow_utxo = $escrow_inputs->fetch()) {
							$input_sum += $escrow_utxo['amount'];
							array_push($io_ids, $escrow_utxo['io_id']);
						}
						
						if ($input_sum >= $unprocessed_sellout['amount_out']) {
							$amounts = explode(",", $unprocessed_sellout['out_amounts']);
							$address_ids = [];
							
							$sellout_inputs = $app->run_query("SELECT * FROM transaction_ios WHERE spend_transaction_id=:transaction_id;", ['transaction_id'=>$sellout_transaction['transaction_id']]);
							
							while ($in_io = $sellout_inputs->fetch()) {
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
								
								$app->run_query("UPDATE game_sellouts SET out_tx_hash=:tx_hash WHERE sellout_id=:sellout_id;", [
									'tx_hash' => $db_transaction['tx_hash'],
									'sellout_id' => $unprocessed_sellout['sellout_id']
								]);
								
								if ($print_debug) echo "Created sellout refund transaction ".$db_transaction['tx_hash']."<br/>\n";
							}
							else {
								if ($print_debug) echo "Failed to add transaction for sellout #".$unprocessed_sellout['sellout_id'].": ".$error_message."<br/>\n";
							}
						}
					}
				}
			}
		}*/
	}
	else echo "This process is already running.\n";
}
else echo "Error: incorrect key supplied in cron/minutely_check_payments.php\n";
?>
