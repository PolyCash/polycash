<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	if ($user_game) {
		if (!empty($_REQUEST['change_to_currency_id'])) {
			$change_to_currency_id = (int) $_REQUEST['change_to_currency_id'];
			$user_game = $thisuser->set_buyin_currency($user_game, $change_to_currency_id);
		}
		
		if ($game->db_game['sellout_policy'] == "on") {
			$coins_in_existence = ($game->coins_in_existence(false, true)+$game->pending_bets(true))/pow(10, $game->db_game['decimal_places']);
			
			$sellout_currency = $app->fetch_currency_by_id($user_game['buyin_currency_id']);
			$escrow_value = $game->escrow_value_in_currency($user_game['buyin_currency_id'], $coins_in_existence);
			$ref_user = false;
			$game_sale_account = $game->check_set_game_sale_account($ref_user);
			
			if ($game_sale_account) {
				$sellout_blockchain = new Blockchain($app, $sellout_currency['blockchain_id']);
				
				if ($escrow_value > 0) {
					$exchange_rate = $coins_in_existence/$escrow_value;
				}
				else $exchange_rate = 0;
				
				$blockchain_sale_account = $game->check_set_blockchain_sale_account($ref_user, $sellout_currency);
				$blockchain_sale_amount = $sellout_blockchain->account_balance($blockchain_sale_account['account_id'], false);
				$game_forsale_amount = ($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']))*$exchange_rate;
				
				$output_obj = [];
				$content_html = "";
				$invoices_html = "";
				$message = "";
				$error_code = 1;
				
				if (in_array($_REQUEST['action'], ["check_amount","confirm"])) {
					$sellout_amount = floatval(str_replace(",", "", urldecode($_REQUEST['sellout_amount'])));
				}
				
				if ($_REQUEST['action'] == "initiate") {
					$content_html .= '<p>You can use the form below to exchange your '.$game->db_game['coin_name_plural'].' for Bitcoin or Litecoin. If instead you want send your '.$game->db_game['coin_name_plural'].' somewhere please go to <a href="/wallet/'.$game->db_game['url_identifier'].'/?initial_tab=4">Send & Receive</a>.</p>';
					$content_html .= '<p>If you find that there are no '.$sellout_blockchain->db_blockchain['coin_name_plural'].' available here, you may need to use an external exchange to convert your '.$game->db_game['coin_name_plural'].'.</p>'."\n";
					
					$content_html .= '<div class="form-group"><label for="buyin_currency_id">What do you want to receive?</label>';
					$content_html .= '<select class="form-control" id="buyin_currency_id" name="buyin_currency_id" onchange="thisPageManager.change_sellout_currency(this);">';
					$content_html .= "<option value=\"\">-- Please Select --</option>\n";
					$buyin_currencies = $app->run_query("SELECT * FROM currencies c JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE b.p2p_mode='rpc' ORDER BY c.name ASC;");
					while ($a_buyin_currency = $buyin_currencies->fetch()) {
						$content_html .= "<option ";
						if ($a_buyin_currency['currency_id'] == $sellout_currency['currency_id']) $content_html .= "selected=\"selected\" ";
						$content_html .= "value=\"".$a_buyin_currency['currency_id']."\">".$a_buyin_currency['name']."</option>\n";
					}
					$content_html .= "</select>\n";
					$content_html .= "</div>\n";
					
					$content_html .= "<p>The exchange rate is ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$sellout_currency['short_name'].".<p>";
					
					$content_html .= "<p>There are ".$app->format_bignum($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']))." ".$sellout_blockchain->db_blockchain['coin_name_plural']." available (".$app->format_bignum($game_forsale_amount)." ".$game->db_game['coin_name_plural'].").<p>\n";
					
					if ($game->db_game['buyin_policy'] != 'for_sale' || $sellout_blockchain->db_blockchain['online'] == 1) {
						$content_html .= '
						<div class="form-group">
							<label for="sellout_amount">How many '.$game->db_game['coin_name_plural'].' do you want to change?'.($game->db_game['min_sellout_amount'] ? ' &nbsp; (Minimum: '.$game->db_game['min_sellout_amount'].')' : '').'</label>
							<div class="row">
								<div class="col-sm-6">
									<input type="text" class="form-control" id="sellout_amount" style="text-align: right;" />
								</div>
								<div class="col-sm-6 form-control-static">
									'.$game->db_game['coin_name_plural'].'
								</div>
							</div>
						</div>
						<div class="form-group">
							<label for="sellout_fee">How much do you want to pay in fees to get this transaction confirmed?</label>
							<div class="row">
								<div class="col-sm-6">
									<input type="text" class="form-control" id="sellout_fee" value="'.$app->format_bignum($user_game['transaction_fee']).'" style="text-align: right;" />
								</div>
								<div class="col-sm-6 form-control-static">
									'.$sellout_blockchain->db_blockchain['coin_name_plural'].'
								</div>
							</div>
						<div>
						<p style="margin-top: 10px;">
							<button class="btn btn-primary" onclick="thisPageManager.manage_sellout(\'check_amount\');">Check</button>
						</p>'."\n";
					}
					else {
						$content_html .= '<p class="text-danger">You can\'t sell '.$game->db_game['coin_name_plural'].' for '.$sellout_currency['abbreviation'].' here right now. '.$sellout_blockchain->db_blockchain['blockchain_name']." is not running on this node.</p>\n";
					}
				}
				else if ($_REQUEST['action'] == "check_amount") {
					$account_balance = $game->account_balance($user_game['account_id']);
					$fee_amount = max(0, (float) $_REQUEST['fee_amount']);
					
					if ($account_balance < $sellout_amount*pow(10, $game->db_game['decimal_places'])) {
						$content_html .= '<p class="text-danger">You don\'t have that many '.$game->db_game['coin_name_plural'].'.</p>';
					}
					else if ($game->db_game['min_sellout_amount'] && $sellout_amount < $game->db_game['min_sellout_amount']) {
						$content_html .= '<p class="text-danger">Please change at least '.$game->db_game['min_sellout_amount'].' '.$game->db_game['coin_name_plural'].".</p>\n";
					}
					else {
						$sellout_receive_amount = $sellout_amount/$exchange_rate - $fee_amount;
						
						if ($game->db_game['buyin_policy'] == "for_sale" && $sellout_amount > $game_forsale_amount) {
							$content_html .= '<p class="text-danger">Don\'t sell that many '.$game->db_game['coin_name_plural'].'. There are only '.$app->format_bignum($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places'])).' '.$sellout_blockchain->db_blockchain['coin_name_plural']." available.</p>\n";
						}
						else {
							if ($sellout_receive_amount <= 0) {
								$content_html .= '<p class="text-danger">That\'s not enough to conver transaction fees.</p>';
							}
							else {
								$content_html .= '
								<p>
									'.$app->format_bignum($fee_amount).' '.$sellout_currency['short_name'].' tx fee
								</p>
								<p>
									'.$sellout_amount.' '.$game->db_game['coin_name_plural'].' will get you approximately '.$app->format_bignum($sellout_receive_amount).' '.$sellout_currency['short_name_plural'].' after fees.
								</p>
								<form method="post" onsubmit="thisPageManager.manage_sellout(\'confirm\'); return false;">
									<div class="form-group">
										<label for="sellout_blockchain_address">What address should your '.$sellout_currency['short_name_plural'].' be sent to?</label>
										<input type="text" class="form-control" id="sellout_blockchain_address" required="true" />
									</div>
									<p>
										<button type="submit" class="btn btn-success">Sell '.$game->db_game['coin_name_plural'].'</button>
									</p>
								</form>';
							}
						}
					}
				}
				else if ($_REQUEST['action'] == "confirm") {
					$sellout_amount = ceil($sellout_amount*pow(10, $game->db_game['decimal_places']));
					$account_balance = $game->account_balance($user_game['account_id']);
					$fee_amount = max(0, (float) $_REQUEST['fee_amount']);
					
					if ($account_balance >= $sellout_amount) {
						$invoice = $app->new_currency_invoice($game_sale_account, $sellout_currency['currency_id'], false, $thisuser, $user_game, "sellout");
						$invoice_address = $app->fetch_address_by_id($invoice['address_id']);
						
						$tx_fee = (int) ($fee_amount*pow(10, $game->blockchain->db_blockchain['decimal_places']));
						
						$user_game_account = $app->fetch_account_by_id($user_game['account_id']);
						
						$receive_address = $_REQUEST['address'];
						$db_receive_address = $sellout_blockchain->create_or_fetch_address($receive_address, false, null);
						
						$app->run_query("UPDATE currency_invoices SET receive_address_id=:receive_address_id, buyin_amount=:buyin_amount, fee_amount=:fee_amount WHERE invoice_id=:invoice_id;", [
							'receive_address_id' => $db_receive_address['address_id'],
							'buyin_amount' => $sellout_amount/pow(10, $game->db_game['decimal_places']),
							'fee_amount' => $fee_amount,
							'invoice_id' => $invoice['invoice_id']
						]);
						
						$my_spendable_ios = $app->spendable_ios_in_account($user_game['account_id'], $game->db_game['game_id'], false, false);
						
						$io_amount_sum = 0;
						$game_amount_sum = 0;
						$ios = [];
						$io_ids = [];
						$keep_looping = true;
						
						$recycle_io = $app->fetch_recycle_ios_in_account($user_game['account_id'], 1)[0];
						
						if ($recycle_io) {
							array_push($io_ids, $recycle_io['io_id']);
							$io_amount_sum += $recycle_io['amount'];
						}
						
						while ($keep_looping && $io = $my_spendable_ios->fetch()) {
							$game_amount_sum += $io['coins'];
							$io_amount_sum += $io['amount'];
							array_push($io_ids, $io['io_id']);
							array_push($ios, $io);
							
							if ($game_amount_sum >= $sellout_amount && $io_amount_sum > ($tx_fee*2)) $keep_looping = false;
						}
						
						if ($io_amount_sum > $tx_fee*2) {
							$io_nonfee_amount = $io_amount_sum-$tx_fee;
							$game_coins_per_coin = $game_amount_sum/$io_nonfee_amount;
							
							$send_chain_amount = ceil($sellout_amount/$game_coins_per_coin);
							
							$amounts = array($send_chain_amount);
							$address_ids = array($invoice['address_id']);
							
							$remainder_error = false;
							
							if ($io_nonfee_amount > $send_chain_amount) {
								$remainder_amount = $io_nonfee_amount-$send_chain_amount;
								$remainder_address = $app->new_normal_address_key($user_game_account['currency_id'], $user_game_account);
								
								if ($remainder_address) {
									array_push($amounts, $remainder_amount);
									array_push($address_ids, $remainder_address['address_id']);
								}
								else $remainder_error = true;
							}
							
							if (!$remainder_error) {
								$transaction_id = $game->blockchain->create_transaction("transaction", $amounts, false, $io_ids, $address_ids, $tx_fee, $message);
								
								if ($transaction_id) {
									$message = ucfirst($game->db_game['coin_name_plural'])." have been debited from this account. ".ucwords($sellout_currency['short_name_plural'])." will be sent to the address you specified soon.";
								}
								else $error_code = 9;
							}
							else $error_code = 8;
						}
						else {
							$error_code = 7;
							$message = "Error: you can't afford the TX fee for the sellout transaction.";
						}
					}
					else {
						$error_code = 6;
						$message = "You don't have that many ".$game->db_game['coin_name_plural']." to sell.";
					}
				}
				
				if (in_array($_REQUEST['action'], ['initiate','confirm','refresh'])) {
					list($num_invoices, $sellout_invoices_html) = $game->display_sellouts_by_user_game($user_game['user_game_id']);
					
					if ($num_invoices > 0) {
						$invoices_html = '<p style="margin-top: 10px;">You have '.$num_invoices.' sellout';
						if ($num_invoices != 1) $invoices_html .= 's';
						$invoices_html .= ".</p>\n";
					}
					
					$invoices_html .= '<div class="buyin_sellout_list">'.$sellout_invoices_html."</div>\n";
					$output_obj['invoices_html'] = $invoices_html;
					$output_obj['invoices_hash'] = AppSettings::standardHash($invoices_html);
				}
				
				if (!empty($content_html)) $output_obj['content_html'] = $content_html;
				
				$app->output_message($error_code, $message, $output_obj);
			}
			else $app->output_message(5, "There was an error finding the sale account for the currency you selected.", false);
		}
		else $app->output_message(4, "Sellouts are disabled for this game.", false);
	}
	else $app->output_message(3, "You're not logged in to this game.", false);
}
else $app->output_message(2, "Error: it looks like you're not logged into this game.", false);
?>
