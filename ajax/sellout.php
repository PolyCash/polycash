<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	if ($user_game) {
		if ($game->db_game['sellout_policy'] == "on") {
			$coins_in_existence = ($game->coins_in_existence(false)+$game->pending_bets())/pow(10, $game->db_game['decimal_places']);
			
			$sellout_currency = $app->fetch_currency_by_id($user_game['buyin_currency_id']);
			$escrow_value = $game->escrow_value_in_currency($user_game['buyin_currency_id']);
			$ref_user = false;
			$game_sale_account = $game->check_set_game_sale_account($ref_user);
			
			if ($game_sale_account) {
				$sellout_blockchain = new Blockchain($app, $sellout_currency['blockchain_id']);
				
				if ($escrow_value > 0) {
					$exchange_rate = $coins_in_existence/$escrow_value;
				}
				else $exchange_rate = 0;
				
				$blockchain_sale_account = $game->check_set_blockchain_sale_account($ref_user, $sellout_currency);
				$blockchain_sale_amount = $sellout_blockchain->account_balance($blockchain_sale_account['account_id']);
				$game_forsale_amount = ($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']))*$exchange_rate;
				
				$output_obj = [];
				$content_html = "";
				$message = "";
				$error_code = 1;
				
				if ($_REQUEST['action'] == "initiate") {
					$content_html .= '<p>';
					$content_html .= "Right now, there are ".$app->format_bignum($coins_in_existence)." ".$game->db_game['coin_name_plural']." in existence";
					$content_html .= " and the exchange rate is ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$sellout_currency['short_name'].". ";
					$content_html .= '<p>';
					
					$content_html .= "<p>There are ".$app->format_bignum($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']))." ".$sellout_blockchain->db_blockchain['coin_name_plural']." for sale (".$app->format_bignum($game_forsale_amount)." ".$game->db_game['coin_name_plural'].").<p>\n";
					
					if ($game->db_game['buyin_policy'] != 'for_sale' || $sellout_blockchain->db_blockchain['online'] == 1) {
						$content_html .= '
						<p>
							How many '.$game->db_game['coin_name_plural'].' do you want to change?
						</p>
						<p>
							<input type="text" class="form-control" id="sellout_amount" />
						</p>
						<button class="btn btn-primary" onclick="manage_sellout(\'check_amount\');">Check</button>'."\n";
					}
					else {
						$content_html .= '<p class="redtext">You can\'t sell '.$game->db_game['coin_name_plural'].' for '.$sellout_currency['abbreviation'].' here right now. '.$sellout_blockchain->db_blockchain['blockchain_name']." is not running on this node.</p>\n";
					}
				}
				else if ($_REQUEST['action'] == "check_amount") {
					$sellout_amount = floatval(str_replace(",", "", urldecode($_REQUEST['sellout_amount'])));
					$sellout_receive_amount = $sellout_amount/$exchange_rate - $user_game['transaction_fee'];
					$content_html .= '
					<p>
						'.(float)$user_game['transaction_fee'].' '.$sellout_currency['short_name'].' tx fee
					</p>
					<p>
						'.$sellout_amount.' '.$game->db_game['coin_name_plural'].' will get you approximately '.$app->format_bignum($sellout_receive_amount).' '.$sellout_currency['short_name_plural'].' after fees.
					</p>
					<div class="form-group">
						<label for="sellout_blockchain_address">What address should your '.$sellout_currency['short_name_plural'].' be sent to?</label>
						<input type="text" class="form-control" id="sellout_blockchain_address" />
					</div>
					<p>
						<button class="btn btn-success" onclick="manage_sellout(\'confirm\');">Sell '.$game->db_game['coin_name_plural'].'</button>
					</p>';
				}
				else if ($_REQUEST['action'] == "confirm") {
					$invoice_type = "sellout";
					$invoice = $app->new_currency_invoice($game_sale_account, $sellout_currency['currency_id'], false, $thisuser, $user_game, $invoice_type);
					$invoice_address = $app->fetch_address_by_id($invoice['address_id']);
					
					$tx_fee = (int) ($user_game['transaction_fee']*pow(10, $game->blockchain->db_blockchain['decimal_places']));
					
					$receive_address = $_REQUEST['address'];
					$db_receive_address = $sellout_blockchain->create_or_fetch_address($receive_address, true, false, true, false, false);
					
					$app->run_query("UPDATE currency_invoices SET receive_address_id='".$db_receive_address['address_id']."', buyin_amount='".$sellout_amount/pow(10, $game->db_game['decimal_places'])."', fee_amount='".$user_game['transaction_fee']."' WHERE invoice_id='".$invoice['invoice_id']."';");
					
					$q = "SELECT *, SUM(gio.colored_amount) AS coins FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.is_resolved=1 AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$user_game['account_id']."' GROUP BY gio.io_id ORDER BY coins ASC;";
					$r = $app->run_query($q);
					
					$io_amount_sum = 0;
					$game_amount_sum = 0;
					$ios = array();
					$io_ids = array();
					$keep_looping = true;
					
					$recycle_r = $app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$user_game['account_id']."' AND a.is_destroy_address=1 AND io.spend_status='unspent' ORDER BY io.amount ASC;");
					
					if ($recycle_r->rowCount() > 0) {
						$recycle_io = $recycle_r->fetch();
						array_push($io_ids, $recycle_io['io_id']);
						$io_amount_sum += $recycle_io['amount'];
					}
					
					while ($keep_looping && $io = $r->fetch()) {
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
						
						if ($io_nonfee_amount > $send_chain_amount) {
							$remainder_amount = $io_nonfee_amount-$send_chain_amount;
							array_push($amounts, $remainder_amount);
							array_push($address_ids, $ios[0]['address_id']);
						}
						
						$transaction_id = $game->blockchain->create_transaction("transaction", $amounts, false, $io_ids, $address_ids, $tx_fee, $message);
						
						if ($transaction_id) {
							$message = ucfirst($game->db_game['coin_name_plural'])." have been debited from this account. ".$sellout_currency['short_name_plural']." will be sent to the address you specified soon.";
						}
						else $error_code = 7;
					}
					else {
						$error_code = 6;
						$message = "Error: you can't afford the TX fee for the sellout transaction.";
					}
				}
				
				if (!empty($content_html)) $output_obj['content_html'] = $content_html;
				//$output_obj['invoices_html'] = $invoices_html;
				
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
