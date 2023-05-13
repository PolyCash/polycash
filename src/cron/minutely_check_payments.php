<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require_once(dirname(dirname(__FILE__))."/models/CoinbaseClient.php");

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
		
		$buyin_invoices = $app->run_query("SELECT *, ug.user_id AS user_id, ug.account_id AS user_game_account_id FROM user_games ug JOIN currency_invoices i ON ug.user_game_id=i.user_game_id JOIN addresses a ON i.address_id=a.address_id JOIN games g ON ug.game_id=g.game_id WHERE i.status IN ('unpaid','unconfirmed','confirmed') AND (i.status='unconfirmed' OR i.expire_time >= :current_time) AND i.invoice_type != 'sellout' GROUP BY a.address_id;", ['current_time'=>time()])->fetchAll();
		
		if ($print_debug) echo "Checking ".count($buyin_invoices)." buyin addresses.\n";
		
		foreach ($buyin_invoices as $invoice_address) {
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
				
				if ($print_debug) echo $invoice_address['address']." ".$address_balance_float.", paid: ".$amount_paid_float."\n";
				
				if ($amount_paid_float > 0) {
					$pay_tx_hash = false;
					$pay_out_index = false;
					$pay_game_out_index = false;
					
					if ($invoice_address['invoice_type'] == "sale_buyin") {
						$coins_in_existence = $game->coins_in_existence(false, false)+$game->pending_bets(false);
						$escrow_value_float = $game->escrow_value_in_currency($invoice_address['pay_currency_id'], $coins_in_existence/pow(10, $game->db_game['decimal_places']));
						$exchange_rate = $coins_in_existence/pow(10, $game->db_game['decimal_places'])/$escrow_value_float;
						$buyin_amount_int = ceil($amount_paid_float*$exchange_rate*pow(10, $game->db_game['decimal_places']));
						$sale_game_account = $game->check_set_game_sale_account($ref_user);
						$sale_game_account_balance_int = $game->account_balance($sale_game_account['account_id']);
						
						$chain_fee_float = $game->db_game['default_transaction_fee'];
						$chain_fee_int = (int)($chain_fee_float*pow(10, $game->blockchain->db_blockchain['decimal_places']));
						
						$sale_spend_ios = $app->spendable_ios_in_account($sale_game_account['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
						
						$spend_ios = [];
						$spend_io_ids = [];
						$game_coins_in = 0;
						$chain_coins_in = 0;
						
						while ($spend_io = $sale_spend_ios->fetch()) {
							if ($game_coins_in < $buyin_amount_int || $chain_coins_in < $chain_fee_int*5) {
								array_push($spend_ios, $spend_io);
								array_push($spend_io_ids, $spend_io['io_id']);
								$game_coins_in += $spend_io['coins'];
								$chain_coins_in += $spend_io['amount'];
							}
						}
						
						if ($game_coins_in >= $buyin_amount_int) {
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
									else if ($print_debug) {
										echo "TX failed: ".$error_message."\n";
										echo json_encode($amounts, JSON_PRETTY_PRINT)."\n"; 
									}
								}
								else if ($print_debug) echo "Couldn't find an remainder address.\n";
							}
							else if ($print_debug) echo "Couldn't find the address to pay to.\n";
						}
						else if ($print_debug) echo "Not enough game coins available.\n";
					}
					else { // Legacy buyins commented out and pending deletion
						if ($invoice_address['buyin_amount'] > 0) $buyin_amount_float = $invoice_address['buyin_amount'];
						else $buyin_amount_float = (int)($amount_paid_float/2);
						
						$buyin_amount_int = ceil($buyin_amount_float*pow(10, $game->db_game['decimal_places']));
						
						$strategy = $app->fetch_strategy_by_id($invoice_address['strategy_id']);
						if ($strategy) $fee_amount_int = $strategy['transaction_fee']*pow(10,$game->blockchain->db_blockchain['decimal_places']);
						else $fee_amount_int = $game->db_game['default_transaction_fee']*pow(10,$game->blockchain->db_blockchain['decimal_places']);
						
						$amount_paid_int = (int) ($amount_paid_float*pow(10,$game->blockchain->db_blockchain['decimal_places']));
						
						$amount_to_color = $amount_paid_int - $fee_amount_int - $buyin_amount_int;
						
						if ($fee_amount_int > 0 && $buyin_amount_int > 0 && $amount_to_color > 0) {
							$invoice_user = new User($app, $invoice_address['user_id']);
							
							$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], false, null);
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
						else if ($print_debug) echo "fee: ".$app->format_bignum($fee_amount_int).", buyin: ".$app->format_bignum($buyin_amount_int).", color: ".$app->format_bignum($amount_to_color)."\n";
					}
					
					if ($transaction_id) {
						if ($print_debug) echo "created tx #".$transaction_id."\n";
						
						$app->run_query("UPDATE currency_invoices SET confirmed_amount_paid=:address_balance_float, unconfirmed_amount_paid=:address_balance_float, status='confirmed' WHERE invoice_id=:invoice_id;", [
							'address_balance_float' => $address_balance_float,
							'invoice_id' => $invoice_address['invoice_id']
						]);
						
						$invoice_io_extra_info = [];
						
						if (!empty(AppSettings::getParam('coinbase_key'))) {
							$coinbase_client = new CoinbaseClient(AppSettings::getParam('coinbase_key'), AppSettings::getParam('coinbase_secret'), AppSettings::getParam('coinbase_passphrase'));
							
							echo "sell: ".$amount_paid_float."\n";
							
							list($sell_order, $returned_headers, $error_message) = $coinbase_client->apiRequest("/orders", "POST", [
								"size" => (string) $amount_paid_float,
								"side" => "sell",
								"type" => "market",
								"product_id" => $pay_currency['abbreviation']."-USD"
							]);
							
							$invoice_io_extra_info['order'] = $sell_order;
							
							if (!empty($sell_order->id)) {
								echo "order: ".$sell_order->id."\n";
								
								sleep(1);
								
								list($fulfillments, $returned_headers, $error_message) = $coinbase_client->apiRequest("/fills", "GET", ['order_id' => $sell_order->id]);
								
								$invoice_io_extra_info['fulfillments'] = $fulfillments;
							}
							else echo json_encode([$error_message, $sell_order], JSON_PRETTY_PRINT)."\n";
						}
						
						$app->run_insert_query("currency_invoice_ios", [
							'invoice_id' => $invoice_address['invoice_id'],
							'tx_hash' => $pay_tx_hash,
							'out_index' => $pay_out_index,
							'game_out_index' => $pay_game_out_index,
							'extra_info' => json_encode($invoice_io_extra_info, JSON_PRETTY_PRINT),
							'time_created' => time()
						]);
					}
					else if ($print_debug) echo "failed to create a transaction.\n";
				}
				else if ($print_debug) echo "amount paid: ".$amount_paid_float."\n";
			}
			else if ($print_debug) echo "game not fully loaded.. skipping\n";
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
					$coins_in_existence = $game->coins_in_existence(false, false)+$game->pending_bets(false);
					$escrow_value_float = $game->escrow_value_in_currency($sellout_currency['currency_id'], $coins_in_existence/pow(10, $game->db_game['decimal_places']));
					$exchange_rate = $coins_in_existence/pow(10, $game->db_game['decimal_places'])/$escrow_value_float;
					
					$sellout_account = $game->check_set_blockchain_sale_account($ref_user, $sellout_currency);
					
					$include_unconfirmed = false;
					$immature_only = false;
					$min_confirmations = $include_unconfirmed ? 0 : 1;
					if ($sellout_blockchain->db_blockchain['sync_mode'] == "no_db") {
						$sellout_account_balance_int = $sellout_blockchain->rpc_account_balance($sellout_account, $min_confirmations);
					}
					else $sellout_account_balance_int = $sellout_blockchain->account_balance($sellout_account['account_id'], $include_unconfirmed, $immature_only);
					
					$sellout_amount_int = (int)($amount_paid_float/$exchange_rate*pow(10, $sellout_blockchain->db_blockchain['decimal_places']));
					$fee_amount_int = (int)($invoice_address['fee_amount']*pow(10, $sellout_blockchain->db_blockchain['decimal_places']));
					$sellout_amount_int = max(0, min($sellout_amount_int, $sellout_account_balance_int)-$fee_amount_int);
					
					if ($sellout_amount_int > 0) {
						$sellout_cost_int = $sellout_amount_int+$fee_amount_int;
						
						if ($print_debug) echo "exchange rate: $exchange_rate, pay: $sellout_amount_int, tx fee: $fee_amount_int\n";
						
						$sellout_transaction_created = false;
						$sellout_transaction_error_message = null;
						$sellout_tx_hash = null;
						
						if ($sellout_blockchain->db_blockchain['sync_mode'] == "full") {
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
									$sellout_transaction_created = true;
									$sellout_tx_hash = $transaction['tx_hash'];
								}
								else $sellout_transaction_error_message = $error_message;
							}
						}
						else if ($sellout_blockchain->db_blockchain['sync_mode'] == "no_db") {
							$spendable_txo_info = $sellout_blockchain->rpc_spendable_ios_in_account($sellout_account, 0);
							
							$raw_txin = [];
							$raw_txout = [];
							
							$io_amount_sum = 0;
							$input_pos = 0;
							
							foreach ($spendable_txo_info as $txo_identifier => $txo_info) {
								$raw_txin[$input_pos] = [
									"txid" => $txo_info['tx_hash'],
									"vout" => $txo_info['out_index'],
								];
								$io_amount_sum += $txo_info['value']*pow(10, $sellout_blockchain->db_blockchain['decimal_places']);
								
								if ($io_amount_sum >= $sellout_cost_int) break;
							}
							
							$io_nonfee_amount = $io_amount_sum-$fee_amount_int;
							
							if ($io_nonfee_amount > 0) {
								$receive_address = $app->fetch_address_by_id($invoice_address['receive_address_id']);
								
								$raw_txout[$receive_address['address']] = sprintf('%.'.$sellout_blockchain->db_blockchain['decimal_places'].'F', $sellout_amount_int/pow(10, $sellout_blockchain->db_blockchain['decimal_places']));
								
								$remainder_error = false;
								if ($io_nonfee_amount > $sellout_amount_int) {
									$remainder_amount = $io_nonfee_amount-$sellout_amount_int;
									$remainder_address = $app->new_normal_address_key($sellout_account['currency_id'], $sellout_account);
									
									if ($remainder_address) {
										$raw_txout[$remainder_address['address']] = sprintf('%.'.$sellout_blockchain->db_blockchain['decimal_places'].'F', $remainder_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']));
									}
									else $remainder_error = true;
								}
								
								if (!$remainder_error) {
									list($sendraw_response, $tx_hash) = $sellout_blockchain->rpc_createrawtransaction($raw_txin, $raw_txout);
									
									if (isset($sendraw_response['message'])) {
										$sellout_transaction_error_message = $sendraw_response['message'];
									}
									else {
										$sellout_tx_hash = $sendraw_response;
										$sellout_transaction_created = true;
									}
								}
							}
						}
						
						if ($sellout_transaction_created) {
							$app->run_query("UPDATE currency_invoices SET confirmed_amount_paid=:address_balance_float, unconfirmed_amount_paid=:address_balance_float, status='confirmed' WHERE invoice_id=:invoice_id;", [
								'address_balance_float' => $address_balance_float,
								'invoice_id' => $invoice_address['invoice_id']
							]);
							
							$invoice_io_extra_info = [];
							
							if (!empty(AppSettings::getParam('coinbase_key'))) {
								$coinbase_client = new CoinbaseClient(AppSettings::getParam('coinbase_key'), AppSettings::getParam('coinbase_secret'), AppSettings::getParam('coinbase_passphrase'));
								
								$fulfill_buy_amount = $sellout_amount_int/pow(10, $sellout_blockchain->db_blockchain['decimal_places']);
								echo "fulfill buy: ".$fulfill_buy_amount."\n";
								
								list($buy_order, $returned_headers, $error_message) = $coinbase_client->apiRequest("/orders", "POST", [
									"size" => (string) $fulfill_buy_amount,
									"side" => "buy",
									"type" => "market",
									"product_id" => $sellout_currency['abbreviation']."-USD"
								]);
								
								$invoice_io_extra_info['order'] = $buy_order;
								
								if (!empty($buy_order->id)) {
									echo "order: ".$buy_order->id."\n";
									
									sleep(1);
								
									list($fulfillments, $returned_headers, $error_message) = $coinbase_client->apiRequest("/fills", "GET", ['order_id' => $buy_order->id]);
									
									$invoice_io_extra_info['fulfillments'] = $fulfillments;
								}
								else echo json_encode([$buy_order, $error_message], JSON_PRETTY_PRINT)."\n";
							}
							
							$app->run_insert_query("currency_invoice_ios", [
								'invoice_id' => $invoice_address['invoice_id'],
								'tx_hash' => $sellout_tx_hash,
								'extra_info' => json_encode($invoice_io_extra_info, JSON_PRETTY_PRINT),
								'time_created' => time(),
								'out_index' => 0,
								'game_out_index' => null
							]);
							
							if ($print_debug) echo "Created the sellout transaction: ".$sellout_tx_hash."\n";
						}
						else if ($print_debug) echo "Failed to create the sellout transaction.".(isset($sellout_transaction_error_message) ? " (".$sellout_transaction_error_message.")" : "")."\n";
					}
					else if ($print_debug) echo "Invalid payment amount.\n";
				}
				else if ($print_debug) echo "Nothing was paid.\n";
				
				if ($print_debug) echo "\n";
			}
		}
	}
	else echo "This process is already running.\n";
}
else echo "Error: incorrect key supplied in cron/minutely_check_payments.php\n";
?>
