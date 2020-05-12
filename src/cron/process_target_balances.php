<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require_once(dirname(dirname(__FILE__))."/classes/CoinbaseClient.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	if (empty(AppSettings::getParam("coinbase_key"))) die("Please configure coinbase parameters to use this feature.\n");
	
	$process_lock_name = "process_target_balances";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$admin_user_id = $app->get_site_constant("admin_user_id");
		
		$client = new CoinbaseClient(AppSettings::getParam('coinbase_key'), AppSettings::getParam('coinbase_secret'), AppSettings::getParam('coinbase_passphrase'));
		
		$min_buy_amounts = [
			'LTC' => 0.1,
			'BTC' => 0.001
		];
		
		$send_fee_by_currency = [
			'BTC' => 0.00002,
			'LTC' => 0.00002
		];
		
		$deposit_address_by_currency = [
			'BTC' => AppSettings::getParam('coinbase_deposit_btc_address'),
			'LTC' => AppSettings::getParam('coinbase_deposit_ltc_address')
		];
		
		$blockchains_by_id = [];
		
		$running_games = $app->fetch_running_games()->fetchAll();
		echo "Processing ".count($running_games)." games.\n";
		
		foreach ($running_games as &$running_game) {
			$sale_accounts = $app->run_query("SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id WHERE ca.game_id=:game_id AND ca.is_blockchain_sale_account=1 AND ca.target_balance > 0 AND ca.user_id=:admin_user_id;", [
				'game_id' => $running_game['game_id'],
				'admin_user_id' => $admin_user_id
			])->fetchAll();
			
			echo $running_game['name']."\n\n";
			
			foreach ($sale_accounts as &$sale_account) {
				if (empty($blockchains_by_id[$sale_account['blockchain_id']])) $blockchains_by_id[$sale_account['blockchain_id']] = new Blockchain($app, $sale_account['blockchain_id']);
				
				$bal = $blockchains_by_id[$sale_account['blockchain_id']]->account_balance($sale_account['account_id'], true)/pow(10, $blockchains_by_id[$sale_account['blockchain_id']]->db_blockchain['decimal_places']);
				
				echo $sale_account['account_name'].": ".$bal." / ".$sale_account['target_balance']."\n";
				
				if (empty($sale_account['current_address_id'])) {
					echo "Account has no primary address.\n";
				}
				else {
					$account_address = $app->fetch_address_by_id($sale_account['current_address_id']);
					
					$add_amount = $sale_account['target_balance'] - $bal;
					
					if ($add_amount != 0) {
						list($cb_accounts, $returned_headers, $error_message) = $client->apiRequest("/accounts", "GET", [
							'currency' => $sale_account['abbreviation']
						]);
						
						$accounts_by_abbrev = AppSettings::arrayToMapOnKey($cb_accounts, "currency");
						
						if ($add_amount > 0) {
							if ($add_amount >= $min_buy_amounts[$sale_account['abbreviation']]/10) {
								$avail_amount = $accounts_by_abbrev[$sale_account['abbreviation']]->available;
								
								echo "avail: ".$avail_amount."\n";
								
								if ($avail_amount <= $add_amount) {
									$buy_amount = max($add_amount-$avail_amount, $min_buy_amounts[$sale_account['abbreviation']]);
									
									echo "buy: ".$buy_amount."\n";
									
									list($order, $returned_headers, $error_message) = $client->apiRequest("/orders", "POST", [
										"size" => $buy_amount,
										"side" => "buy",
										"type" => "market",
										"product_id" => $sale_account['abbreviation']."-USD"
									]);
									
									echo "order: ".$order->id."\n";
									
									usleep(50000);
									
									list($cb_accounts, $returned_headers, $error_message) = $client->apiRequest("/accounts", "GET", [
										'currency' => $sale_account['abbreviation']
									]);
									
									$accounts_by_abbrev = AppSettings::arrayToMapOnKey($cb_accounts, "currency");
									
									$avail_amount = $accounts_by_abbrev[$sale_account['abbreviation']]->available;
								}
								
								if ($avail_amount >= $add_amount) {
									echo "send: ".$add_amount."\n";
									
									list($withdrawal, $returned_headers, $error_message) = $client->apiRequest("/withdrawals/crypto", "POST", [
										"amount" => $add_amount,
										"currency" => $sale_account['abbreviation'],
										"crypto_address" => $account_address['address']
									]);
									
									echo "withdrawal: ".$withdrawal->id."\n";
								}
							}
						}
						else {
							$fee_amount_float = $send_fee_by_currency[$sale_account['abbreviation']];
							$sell_amount_float = $add_amount*(-1) - $fee_amount_float;
							
							if ($deposit_address_by_currency[$sale_account['abbreviation']] && $sell_amount_float > $min_buy_amounts[$sale_account['abbreviation']]/10) {
								$fee_amount = $fee_amount_float*pow(10, $blockchains_by_id[$sale_account['blockchain_id']]->db_blockchain['decimal_places']);
								
								$sell_amount_int = (int) ($sell_amount_float*pow(10, $blockchains_by_id[$sale_account['blockchain_id']]->db_blockchain['decimal_places']));
								
								echo "send ".$sell_amount_float."\n";
								
								$spendable_ios = $blockchains_by_id[$sale_account['blockchain_id']]->spendable_ios_in_blockchain_account($sale_account['account_id'])->fetchAll();
								$spendable_io_pos = 0;
								$io_ids = [];
								$address_ids = [];
								$io_inputs_sum = 0;
								
								do {
									array_push($io_ids, $spendable_ios[$spendable_io_pos]['io_id']);
									$io_inputs_sum += $spendable_ios[$spendable_io_pos]['amount'];
									$spendable_io_pos++;
								}
								while (!empty($spendable_ios[$spendable_io_pos]['amount']) && $io_inputs_sum < $fee_amount+$sell_amount_int);
								
								if ($io_inputs_sum >= $fee_amount+$sell_amount_int) {
									$deposit_address = $blockchains_by_id[$sale_account['blockchain_id']]->create_or_fetch_address($deposit_address_by_currency[$sale_account['abbreviation']], true, false, false, false, false);
									
									if ($deposit_address) {
										$deposit_address_ids = [$deposit_address['address_id']];
										$io_amounts = [$sell_amount_int];
										
										if ($sell_amount_int+$fee_amount < $io_inputs_sum) {
											$refund_amount = $io_inputs_sum - $fee_amount - $sell_amount_int;
											
											$refund_address = $app->new_normal_address_key($sale_account['currency_id'], $sale_account);
											
											array_push($deposit_address_ids, $refund_address['address_id']);
											array_push($io_amounts, $refund_amount);
										}
										
										$error_message = null;
										$transaction_id = $blockchains_by_id[$sale_account['blockchain_id']]->create_transaction("transaction", $io_amounts, false, $io_ids, $deposit_address_ids, $fee_amount, $error_message);
										
										if ($transaction_id) {
											$transaction = $app->fetch_transaction_by_id($transaction_id);
											echo "tx: ".$transaction['tx_hash']."\n";
										}
										else echo "error: ".$error_message."\n";
									}
								}
							}
							
							echo "sell: ".$sell_amount_float."\n";
							
							if ($accounts_by_abbrev[$sale_account['abbreviation']]->available > $sell_amount_float) {
								list($sell_order, $returned_headers, $error_message) = $client->apiRequest("/orders", "POST", [
									"size" => $sell_amount_float,
									"side" => "sell",
									"type" => "market",
									"product_id" => $sale_account['abbreviation']."-USD"
								]);
								
								echo "order: ".$sell_order->id."\n";
							}
							else echo "can't afford it\n";
						}
					}
				}
				
				echo "\n";
			}
		}
	}
	else echo "This process is already running.\n";
}
else echo "You don't have permission to run this script.\n";
