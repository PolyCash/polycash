<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$action = $_REQUEST['action'];
	if (!empty($_REQUEST['game_id'])) $game_id = (int) $_REQUEST['game_id'];
	
	if ($action == "withdraw_from_account") {
		if ($thisuser) {
			$db_account = $app->fetch_account_by_id($_REQUEST['account_id']);
			
			if ($db_account) {
				$currency = $app->fetch_currency_by_id($db_account['currency_id']);
				
				if (!empty($currency['blockchain_id'])) {
					$blockchain = new Blockchain($app, $currency['blockchain_id']);
					
					if ($thisuser->db_user['user_id'] == $db_account['user_id'] || ($db_account['user_id'] == "" && $app->user_is_admin($thisuser))) {
						$amount = round(pow(10,$blockchain->db_blockchain['decimal_places'])*floatval($_REQUEST['amount']));
						$fee = round(pow(10,$blockchain->db_blockchain['decimal_places'])*floatval($_REQUEST['fee']));
						
						$address = $_REQUEST['address'];
						
						$unconfirmed_balance = $blockchain->account_balance($db_account['account_id'], true);
						$immature_amount = $blockchain->account_balance($db_account['account_id'], false, true);
						$spendable_balance = $unconfirmed_balance - $immature_amount;
						
						if ($amount+$fee <= $spendable_balance) {
							$amount_sum = 0;
							
							$db_address = $blockchain->create_or_fetch_address($address, false, null);
							
							$spendable_ios = $app->run_query("SELECT io.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND io.spend_status IN ('unspent','unconfirmed') AND io.is_mature=1 AND k.account_id=:account_id;", [
								'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
								'account_id' => $db_account['account_id']
							]);
							$keep_looping = true;
							
							$io_ids = [];
							
							while ($keep_looping && $io = $spendable_ios->fetch()) {
								array_push($io_ids, $io['io_id']);
								
								$amount_sum += $io['amount'];
								if ($amount_sum >= $amount+$fee) $keep_looping = false;
							}
							
							$amounts = [];
							$address_ids = [];
							
							array_push($amounts, $amount);
							array_push($address_ids, $db_address['address_id']);
							
							$refund_error = false;
							if ($amount+$fee < $amount_sum) {
								$refund_address = $app->new_normal_address_key($db_account['currency_id'], $db_account);
								if ($refund_address) {
									array_push($amounts, $amount_sum-$amount-$fee);
									array_push($address_ids, $refund_address['address_id']);
								}
							}
							
							if (!$refund_error) {
								$error_message = false;
								$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, $io_ids, $address_ids, $fee);
								
								if ($transaction_id) {
									$app->output_message(1, 'Great, your coins have been sent! <a target="_blank" href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/transactions/'.$transaction_id.'">View Transaction</a>', false);
								}
								else $app->output_message(9, "TX Error: ".$error_message, false);
							}
							else $app->output_message(9, "Failed to generate a refund address.", false);
						}
						else $app->output_message(8, "Error, you don't have enough coins.", false);
					}
					else $app->output_message(7, "Error, permission denied.", false);
				}
				else $app->output_message(6, "Invalid blockchain ID.", false);
			}
			else $app->output_message(5, "Invalid account ID.", false);
		}
		else $app->output_message(4, "You must be logged in.", false);
	}
	else if ($action == "buyin") {
		$io_id = (int) $_REQUEST['io_id'];
		$db_game = $app->fetch_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$buyin_amount = (int) ($_REQUEST['buyin_amount']*pow(10,$blockchain->db_blockchain['decimal_places']));
			$fee_amount = (int) ($_REQUEST['fee_amount']*pow(10,$blockchain->db_blockchain['decimal_places']));
			
			$db_currency = $app->fetch_currency_by_id($game->blockchain->currency_id());
			
			$db_io = $app->fetch_io_by_id($io_id);
			
			if ($db_io) {
				$key_account = $app->run_query("SELECT * FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id=:address_id AND c.user_id=:user_id;", [
					'address_id' => $db_io['address_id'],
					'user_id' => $thisuser->db_user['user_id']
				])->fetch();
				
				if ($key_account) {
					$color_amount = (int) ($db_io['amount'] - $buyin_amount - $fee_amount);
					
					if ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0) {
						$address_text = $_REQUEST['address'];
						
						$user_game = $thisuser->ensure_user_in_game($game, false);
						$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], false, null);
						
						if ($address_text == "new") {
							$game_currency_account = $app->fetch_account_by_id($user_game['account_id']);
							$color_address = $app->new_normal_address_key($game->blockchain->currency_id(), $game_currency_account);
						}
						else {
							$game->blockchain->load_coin_rpc();
							$color_address = $game->blockchain->create_or_fetch_address($address_text, false, null);
						}
						
						$error_message = false;
						$transaction_id = $game->blockchain->create_transaction("transaction", [$buyin_amount, $color_amount], null, [$io_id], [$escrow_address['address_id'], $color_address['address_id']], $fee_amount, $error_message);
						
						if ($transaction_id) {
							$app->output_message(1, "Great, your transaction has been submitted (#".$transaction_id.")", false);
						}
						else {
							$app->output_message(7, "TX Error: ".$error_message, false);
						}
					}
					else $app->output_message(6, "Error, one of the amounts you entered is invalid ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0).", false);
				}
				else $app->output_message(5, "Error, incorrect io_id.", false);
			}
			else $app->output_message(5, "Error, you don't have permission to spend these coins.", false);
		}
		else $app->output_message(4, "Error, incorrect game_id", false);
	}
	else if ($action == "start_join_tx" || $action == "finish_join_tx") {
		if (!empty($_REQUEST['account_id']) && $account = $app->fetch_account_by_id($_REQUEST['account_id'])) {
			$db_io = $app->fetch_io_by_id((int) $_REQUEST['io_id']);
			
			if ($db_io && $address_key = $app->fetch_address_key_by_address_in_account($db_io['address_id'], $account['account_id'])) {
				if ($account['user_id'] == $thisuser->db_user['user_id']) {
					if (in_array($db_io['spend_status'], array("unconfirmed", "unspent"))) {
						if ($account['game_id'] > 0) {
							$db_game = $app->fetch_game_by_id($account['game_id']);
							$blockchain = new Blockchain($app, $db_game['blockchain_id']);
						}
						else $blockchain = new Blockchain($app, $db_io['blockchain_id']);
						
						if ($action == "start_join_tx") {
							$joinable_ios_params = [
								'account_id' => $account['account_id'],
								'io_id' => $db_io['io_id']
							];
							if ($account['game_id'] > 0) {
								$joinable_ios_q = "SELECT *, SUM(gio.colored_amount) AS colored_amount_sum FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id AND gio.game_id=:game_id AND (io.spend_status='unspent' OR io.spend_status='unconfirmed') AND io.io_id != :io_id GROUP BY io.io_id ORDER BY colored_amount_sum DESC;";
								$joinable_ios_params['game_id'] = $account['game_id'];
							}
							else $joinable_ios_q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id AND (io.spend_status='unspent' OR io.spend_status='unconfirmed') AND io.io_id != :io_id GROUP BY io.io_id ORDER BY amount DESC;";
							$joinable_ios = $app->run_query($joinable_ios_q, $joinable_ios_params);
							
							$html = '<form action="/accounts/" method="get" onsubmit="thisPageManager.finish_join_tx(); return false;">';
							
							$html .= '<div class="form-group"><select id="join_tx_io_id" name="join_tx_io_id" class="form-control">'."\n";
							$html .= '<option value="">-- Please Select --</option>'."\n";
							while ($db_io = $joinable_ios->fetch()) {
								$html .= '<option value="'.$db_io['io_id'].'">';
								if ($account['game_id'] > 0) $html .= $app->format_bignum($db_io['colored_amount_sum']/pow(10,$db_game['decimal_places'])).' '.$db_game['coin_abbreviation'].' (';
								$io_amount_disp = $app->format_bignum($db_io['amount']/pow(10,$blockchain->db_blockchain['decimal_places']));
								$html .= $io_amount_disp." ".($io_amount_disp=="1" ? $blockchain->db_blockchain['coin_name'] : $blockchain->db_blockchain['coin_name_plural']);
								if ($account['game_id'] > 0) $html .= ')';
								$html .= ' '.$db_io['address'].'</option>'."\n";
							}
							$html .= "</select></div>\n";
							
							$html .= '<div class="form-group">';
							$html .= '<label for="join_tx_fee">Transaction fee:</label>';
							$html .= '<input id="join_tx_fee" type="text" class="form-control" value="'.($db_game ? $db_game['default_transaction_fee'] : "").'" />';
							$html .= "</div>\n";
							
							$html .= '<button class="btn btn-primary">Join UTXOs</button>'."\n";
							$html .= "</form>\n";
							
							$output_obj['html'] = $html;
							
							$app->output_message(10, "", $output_obj);
						}
						else if ($action == "finish_join_tx") {
							$join_io_id = (int) $_REQUEST['join_io_id'];
							$join_db_io = $app->fetch_io_by_id($join_io_id);
							
							if ($join_db_io) {
								$join_key_account = $app->run_query("SELECT *, c.currency_id AS currency_id FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id=:address_id AND c.user_id=:user_id;", [
									'address_id' => $join_db_io['address_id'],
									'user_id' => $thisuser->db_user['user_id']
								])->fetch();
								
								if ($join_key_account) {
									$tx_fee = (float) $_REQUEST['tx_fee'];
									$fee_amount = (int)($tx_fee*pow(10,$blockchain->db_blockchain['decimal_places']));
									$amount = $db_io['amount']+$join_db_io['amount']-$fee_amount;
									
									$new_normal_address = $app->new_normal_address_key($join_key_account['currency_id'], $join_key_account);
									
									if ($new_normal_address) {
										$error_message = false;
										$transaction_id = $blockchain->create_transaction('transaction', [$amount], false, [$db_io['io_id'], $join_db_io['io_id']], [$new_normal_address['address_id']], $fee_amount, $error_message);
										
										if ($transaction_id) {
											$transaction = $app->fetch_transaction_by_id($transaction_id);
											if ($account['game_id'] > 0) {
												$db_game = $app->fetch_game_by_id($account['game_id']);
												$app->output_message(1, "/explorer/games/".$db_game['url_identifier']."/transactions/".$transaction['tx_hash']."/", false);
											}
											else $app->output_message(1, "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction['tx_hash']."/", false);
										}
										else $app->output_message(10, "TX Error: ".$error_message, false);
									}
									else $app->output_message(9, "There was an error generating a new address.", false);
								}
								else $app->output_message(8, "Error, invalid join UTXO ID.", false);
							}
							else $app->output_message(7, "Error, invalid join UTXO ID.", false);
						}
					}
					else $app->output_message(6, "Sorry, that UTXO is not spendable.", false);
				}
				else $app->output_message(5, "Sorry, you don't have permissions for that account.", false);
			}
			else $app->output_message(4, "Error, invalid UTXO ID.", false);
		}
		else $app->output_message(3, "Please specify a valid account ID.", false);
	}
	else if ($action == "withdraw") {
		if (!empty($_REQUEST['account_id']) && $account = $app->fetch_account_by_id($_REQUEST['account_id'])) {
			$db_io = $app->fetch_io_by_id((int) $_REQUEST['io_id']);
			
			if ($db_io && $address_key = $app->fetch_address_key_by_address_in_account($db_io['address_id'], $account['account_id'])) {
				if ($account['user_id'] == $thisuser->db_user['user_id']) {
					$address = strip_tags($_REQUEST['address']);
					$withdraw_type = $_REQUEST['withdraw_type'];
					
					$blockchain = new Blockchain($app, $db_io['blockchain_id']);
					$blockchain->load_coin_rpc();
					
					if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
						try {
							$info = $blockchain->coin_rpc->getwalletinfo();
						}
						catch (Exception $e) {
							$app->output_message(8, "Error: RPC connection failed.", false);
							die();
						}
					}
					
					$amount = (float) $_REQUEST['amount'];
					$fee = (float) $_REQUEST['fee'];
					
					if ($withdraw_type == "blockchain") {
						$amount = $amount*pow(10, $blockchain->db_blockchain['decimal_places']);
						$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
						
						if ($db_io['amount'] >= $amount+$fee_amount) {
							$remainder_amount = $db_io['amount']-$amount-$fee_amount;
							
							$db_address = $blockchain->create_or_fetch_address($address, false, null);
							
							$amounts = array($amount);
							$address_ids = array($db_address['address_id']);
							
							if ($remainder_amount > 0) {
								array_push($amounts, $remainder_amount);
								array_push($address_ids, $db_io['address_id']);
							}
							
							$error_message = false;
							$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, array($db_io['io_id']), $address_ids, $fee_amount, $error_message);
							
							if ($transaction_id) {
								$transaction = $app->fetch_transaction_by_id($transaction_id);
								$app->output_message(1, "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction['tx_hash']."/", false);
							}
							else $app->output_message(7, "Error: ", false);
						}
						else $app->output_message(6, "Error: not enough coins.", false);
					}
					else {
						$game = new Game($blockchain, $account['game_id']);
						
						$amount = $amount*pow(10, $game->db_game['decimal_places']);
						$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
						
						$db_address = $blockchain->create_or_fetch_address($address, false, null);
						
						$game_ios = $game->fetch_game_ios_by_io($db_io['io_id'])->fetchAll();
						$any_unresolved = false;
						$gio_sum = 0;
						
						foreach ($game_ios as $game_io) {
							if (!$game_io['is_resolved']) $any_unresolved = true;
							$gio_sum += $game_io['colored_amount'];
						}
						
						if (!$any_unresolved) {
							if ($gio_sum >= $amount) {
								// If withdraw amount is within 0.01% of total balance, round up rather than leaving a tiny amount behind
								$initial_remainder_amount = $gio_sum-$amount;
								$remainder_frac = $initial_remainder_amount/$amount;
								$withdrawing_all = false;
								if ($remainder_frac < 1/10000 && $initial_remainder_amount < 1*pow(10, $game->db_game['decimal_places'])) {
									$withdrawing_all = true;
									$amount += $initial_remainder_amount;
								}
								
								$coloredcoins_per_coin = $gio_sum/($db_io['amount']-$fee_amount);
								$io_amount = ceil($amount/$coloredcoins_per_coin);
								$remainder_amount = $db_io['amount']-$fee_amount-$io_amount;
								
								if ($remainder_amount >= 0) {
									$amounts = [$io_amount];
									$address_ids = [$db_address['address_id']];
									
									if ($remainder_amount > 0) {
										array_push($amounts, $remainder_amount);
										array_push($address_ids, $db_io['address_id']);
									}
									
									$error_message = false;
									$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, [$db_io['io_id']], $address_ids, $fee_amount, $error_message);
									
									if ($transaction_id) {
										$transaction = $app->fetch_transaction_by_id($transaction_id);
										$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/transactions/".$transaction['tx_hash']."/", false);
									}
									else $app->output_message(10, "TX Error: ".$error_message, false);
								}
								else $app->output_message(9, "Error: not enough coins.", false);
							}
							else $app->output_message(8, "Error: your balance is not high enough to spend that amount.", false);
						}
						else $app->output_message(7, "Error: attempting to spend unresolved coins.", false);
					}
				}
				else $app->output_message(6, "Error, you don't have permission to spend these coins.", false);
			}
			else $app->output_message(5, "Error, invalid UTXO ID.", false);
		}
		else $app->output_message(4, "Error, invalid account ID.", false);
	}
	else if ($action == "withdraw_from_card") {
		$card_id = (int) $_REQUEST['card_id'];
		$peer_id = (int) $_REQUEST['peer_id'];
		$claim_type = $_REQUEST['claim_type'];
		
		$card = $app->fetch_card_by_peer_and_id($peer_id, $card_id);
		
		if ($card) {
			if ($card['user_id'] == $thisuser->db_user['user_id']) {
				if ($claim_type == "to_address") {
					$fee = (float) $_REQUEST['fee'];
					$address = $_REQUEST['address'];
					
					if ($fee > 0 && $fee < $card['amount']) {
						$this_peer = $app->get_peer_by_server_name(AppSettings::getParam('base_url'), true);
						
						if ($card['peer_id'] != $this_peer['peer_id']) {
							$remote_peer = $app->fetch_peer_by_id($card['peer_id']);
							
							$remote_url = $remote_peer['base_url']."/api/card/".$card['peer_card_id']."/withdraw/?secret=".$card['secret_hash']."&fee=".$fee."&address=".$address;
							$remote_response_raw = file_get_contents($remote_url);
							$remote_response = get_object_vars(json_decode($remote_response_raw));
							
							if ($remote_response['status_code'] == 1) {
								$app->change_card_status($card, 'redeemed');
								$app->output_message(1, $remote_response['message'], false);
							}
							else $app->output_message(7, $remote_response['message'], false);
						}
						else {
							$transaction = $app->pay_out_card($card, $address, $fee);
							$db_blockchain = $app->fetch_blockchain_by_id($transaction['blockchain_id']);
							
							if ($transaction) $app->output_message(1, "Great! Coins have been sent to <a href=\"/explorer/blockchains/".$db_blockchain['url_identifier']."/transactions/".$transaction['tx_hash']."\">your address</a>.", false);
							else $app->output_message(7, "Error: failed to create the transaction.", false);
						}
					}
					else $app->output_message(6, "Error: invalid fee amount.", false);
				}
				else if ($claim_type == "to_account" || $claim_type == "to_game") {
					list($status_code, $message) = $app->redeem_card_to_account($thisuser, $card, $claim_type);
					
					$app->output_message($status_code, $message, false);
				}
				else $app->output_message(6, "Error: invalid action.", false);
			}
			else $app->output_message(5, "Error: you don't own this card.", false);
		}
		else $app->output_message(4, "Error: invalid card or peer ID.", false);
	}
	else if ($action == "split") {
		if (!empty($_REQUEST['account_id']) && $account = $app->fetch_account_by_id($_REQUEST['account_id'])) {
			$db_io = $app->fetch_io_by_id((int) $_REQUEST['io_id']);
			
			if ($db_io && $address_key = $app->fetch_address_key_by_address_in_account($db_io['address_id'], $account['account_id'])) {
				if ($account['user_id'] == $thisuser->db_user['user_id']) {
					$amount_each = (float) $_REQUEST['amount_each'];
					$quantity = (int) $_REQUEST['quantity'];
					$game_id = (int) $_REQUEST['game_id'];
					
					$db_game = $app->fetch_game_by_id($game_id);
					
					if ($db_game && $db_game['game_id'] == $account['game_id']) {
						$blockchain = new Blockchain($app, $db_game['blockchain_id']);
						$game = new Game($blockchain, $db_game['game_id']);
						
						$fee = (float) $_REQUEST['fee'];
						$satoshis_each = round(pow(10,$game->db_game['decimal_places'])*$amount_each);
						$fee_amount = (int) ($fee*pow(10,$game->blockchain->db_blockchain['decimal_places']));
						
						if ($quantity > 0 && $satoshis_each > 0) {
							$total_cost_satoshis = $quantity*$satoshis_each;
							
							$db_game_ios = $game->fetch_game_ios_by_io($db_io['io_id'])->fetchAll();
							
							if (count($db_game_ios) > 0) {
								$game_ios = [];
								$colored_coin_sum = 0;
								
								foreach ($db_game_ios as $game_io) {
									array_push($game_ios, $game_io);
									$colored_coin_sum += $game_io['colored_amount'];
								}
								
								$coin_sum = $game_ios[0]['amount'];
								$coins_per_chain_coin = (float) $colored_coin_sum/($coin_sum-$fee_amount);
								$chain_coins_each = ceil($satoshis_each/$coins_per_chain_coin);
								
								if ($chain_coins_each > 0 && in_array($db_io['spend_status'], ["unspent", "unconfirmed"])) {
									if ($total_cost_satoshis < $colored_coin_sum && $coin_sum > ($chain_coins_each*$quantity) - $fee_amount) {
										$remainder_satoshis = $coin_sum - ($chain_coins_each*$quantity) - $fee_amount;
										
										$amounts = [];
										$address_ids = [];
										
										for ($i=0; $i<$quantity; $i++) {
											$address_key = $app->new_normal_address_key($account['currency_id'], $account);
											array_push($address_ids, $address_key['address_id']);
											array_push($amounts, $chain_coins_each);
										}
										if ($remainder_satoshis > 0) {
											array_push($amounts, $remainder_satoshis);
											array_push($address_ids, $db_io['address_id']);
										}
										
										$error_message = false;
										$transaction_id = $game->blockchain->create_transaction('transaction', $amounts, false, [$db_io['io_id']], $address_ids, $fee_amount, $error_message);
										
										if ($transaction_id) {
											$transaction = $app->fetch_transaction_by_id($transaction_id);
											$app->output_message(1, "/explorer/games/".$db_game['url_identifier']."/transactions/".$transaction['tx_hash']."/", false);
										}
										else $app->output_message(12, "TX Error: ".$error_message, false);
									}
									else {
										$app->output_message(11, "UTXO is only ".$game->display_coins($colored_coin_sum)." but you tried to spend ".$app->format_bignum($total_cost_satoshis/pow(10,$game->db_game['decimal_places'])), false);
									}
								}
								else $app->output_message(10, "UTXO is not spendable.", false);
							}
							else $app->output_message(9, "That UTXO isn't associated with any coins.", false);
						}
						else $app->output_message(8, "Invalid quantity.", false);
					}
					else $app->output_message(7, "Invalid game ID.", false);
				}
				else $app->output_message(6, "You don't have permissions for that account.", false);
			}
			else $app->output_message(5, "Please specify a valid UTXO ID.", false);
		}
		else $app->output_message(4, "Please specify a valid account ID.", false);
	}
	else if ($action == "spend_unresolved") {
		if ($_REQUEST['whole_or_part'] == "whole") {
			$address_text = $_REQUEST['address'];
			
			if (!empty($address_text)) {
				$fee_float = (float) $_REQUEST['fee'];
				$game_io_id = (int) $_REQUEST['game_io_id'];
				$account_id = (int) $_REQUEST['account_id'];
				
				$account = $app->fetch_account_by_id($account_id);
				
				if ($account && $account['user_id'] == $thisuser->db_user['user_id']) {
					$game_io = $app->fetch_game_io_by_id($game_io_id);
					
					if ($game_io) {
						$address_key = $app->fetch_address_key_by_address_in_account($game_io['address_id'], $account['account_id']);
						
						if ($address_key) {
							$db_game = $app->fetch_game_by_id($game_io['game_id']);
							$blockchain = new Blockchain($app, $db_game['blockchain_id']);
							$game = new Game($blockchain, $db_game['game_id']);
							$db_event = $app->fetch_event_by_id($game_io['event_id']);
							
							if ($db_event && $game_io['is_game_coinbase'] == 1) {
								if ($game_io['is_resolved'] == 0 && $db_event['event_payout_block'] > $blockchain->last_block_id() && $game_io['spend_status'] != "spent") {
									$fee_int = (int)($fee_float*pow(10, $blockchain->db_blockchain['decimal_places']));
									
									$io_nonfee_amount = $game_io['amount'] - $fee_int;
									
									if ($io_nonfee_amount > 0) {
										$all_gios = $game->fetch_game_ios_by_io($game_io['io_id'])->fetchAll();
										
										// If more than one gio is attached to this IO, it's probably a bet placed with no separator
										// So include a normal output to avoid deleting resolved coins
										if (count($all_gios) > 1) {
											$new_normal_address = $app->new_normal_address_key($account['currency_id'], $account);
											$num_outputs = 3;
										}
										else {
											$new_normal_address = false;
											$num_outputs = 2;
										}
										
										$passthrough_address = $app->fetch_addresses_in_account($account, 2, 1);
										
										if (count($passthrough_address) > 0) {
											$passthrough_address = $passthrough_address[0];
											$spend_to_address = $blockchain->create_or_fetch_address($address_text, false, null);
											
											if ($spend_to_address['is_destroy_address'] == 0 && $spend_to_address['is_separator_address'] == 0 && $spend_to_address['is_passthrough_address'] == 0) {
												$output_amounts = [];
												$output_address_ids = [];
												
												if ($new_normal_address) {
													array_push($output_amounts, floor($io_nonfee_amount/$num_outputs));
													array_push($output_address_ids, $new_normal_address['address_id']);
												}
												array_push($output_amounts, floor($io_nonfee_amount/$num_outputs));
												array_push($output_address_ids, $passthrough_address['address_id']);
												
												array_push($output_amounts, $io_nonfee_amount-array_sum($output_amounts));
												array_push($output_address_ids, $spend_to_address['address_id']);
												
												$error_message = "";
												
												$transaction_id = $blockchain->create_transaction("transaction", $output_amounts, false, [$game_io['io_id']], $output_address_ids, $fee_int, $error_message);
												
												if ($transaction_id) {
													$transaction = $app->fetch_transaction_by_id($transaction_id);
													$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/transactions/".$transaction['tx_hash']."/", false);
												}
												else $app->output_message(14, "TX Error: ".$error_message, false);
											}
											else $app->output_message(13, "Please use a normal address.", false);
										}
										else $app->output_message(12, "Error fetching your addresses.", false);
									}
									else $app->output_message(11, "UTXO not big enough to afford your fee.", false);
								}
								else $app->output_message(10, "This UTXO is already spent or resolved.", false);
							}
							else $app->output_message(9, "This is not a betting UTXO.", false);
						}
						else $app->output_message(8, "The game IO ID you specified does not match this account ID.", false);
					}
					else $app->output_message(7, "You supplied an invalid game IO ID.", false);
				}
				else $app->output_message(6, "The account ID you specified does not match your user account.", false);
			}
			else $app->output_message(5, "Please specify an address.", false);
		}
		else $app->output_message(4, "Please specify whole_or_part", false);
	}
	else $app->output_message(3, "This action is not yet implemented.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>