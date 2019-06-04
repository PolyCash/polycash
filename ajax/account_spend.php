<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	if (!empty($_REQUEST['game_id'])) $game_id = (int) $_REQUEST['game_id'];
	
	if ($action == "withdraw_from_account") {
		if ($thisuser) {
			$db_account = $app->fetch_account_by_id($_REQUEST['account_id']);
			
			if ($db_account) {
				if (!empty($db_account['blockchain_id'])) {
					$blockchain = new Blockchain($app, $db_account['blockchain_id']);
					
					if ($thisuser->db_user['user_id'] == $db_account['user_id'] || ($db_account['user_id'] == "" && $app->user_is_admin($thisuser))) {
						$amount = round(pow(10,$blockchain->db_blockchain['decimal_places'])*floatval($_REQUEST['amount']));
						$fee = round(pow(10,$blockchain->db_blockchain['decimal_places'])*floatval($_REQUEST['fee']));
						
						$address = $_REQUEST['address'];
						
						$account_balance = $blockchain->account_balance($db_account['account_id']);
						
						if ($amount+$fee <= $account_balance) {
							$amount_sum = 0;
							
							$db_address = $blockchain->create_or_fetch_address($address, true, false, false, false, false);
							
							$spendable_ios = $app->run_query("SELECT io.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$db_account['account_id']."';");
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
		$db_game = $app->fetch_db_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$buyin_amount = (int) ($_REQUEST['buyin_amount']*pow(10,$blockchain->db_blockchain['decimal_places']));
			$fee_amount = (int) ($_REQUEST['fee_amount']*pow(10,$blockchain->db_blockchain['decimal_places']));
			
			$db_currency = $app->fetch_currency_by_id($game->blockchain->currency_id());
			
			$db_io = $app->fetch_io_by_id($io_id);
			
			if ($db_io) {
				$q = "SELECT * FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id='".$db_io['address_id']."' AND c.user_id='".$thisuser->db_user['user_id']."';";
				$r = $app->run_query($q);
				
				if ($r->rowCount() > 0) {
					$key_account = $r->fetch();
					
					$color_amount = (int) ($db_io['amount'] - $buyin_amount - $fee_amount);
					
					if ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0) {
						$address_text = $_REQUEST['address'];
						
						$user_game = $thisuser->ensure_user_in_game($game, false);
						$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
						
						if ($address_text == "new") {
							$game_currency_account = $app->fetch_account_by_id($user_game['account_id']);
							$color_address = $app->new_address_key($game->blockchain->currency_id(), $game_currency_account);
						}
						else {
							$game->blockchain->load_coin_rpc();
							$color_address = $game->blockchain->create_or_fetch_address($address_text, true, false, false, false, false);
						}
						
						$error_message = false;
						$transaction_id = $game->create_transaction(false, array($buyin_amount, $color_amount), $user_game, false, 'transaction', array($io_id), array($escrow_address['address_id'], $color_address['address_id']), false, $fee_amount, $error_message);
						
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
		$io_id = (int) $_REQUEST['io_id'];
		
		$q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id='".$io_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_io = $r->fetch();
			
			$q = "SELECT *, c.currency_id AS currency_id FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id='".$db_io['address_id']."' AND c.user_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$key_account = $r->fetch();
				
				if (in_array($db_io['spend_status'], array("unconfirmed", "unspent"))) {
					if ($key_account['game_id'] > 0) {
						$db_game = $app->fetch_db_game_by_id($key_account['game_id']);
						$blockchain = new Blockchain($app, $db_game['blockchain_id']);
					}
					else $blockchain = new Blockchain($app, $db_io['blockchain_id']);
					
					if ($action == "start_join_tx") {
						if ($key_account['game_id'] > 0) $q = "SELECT *, SUM(gio.colored_amount) AS colored_amount_sum FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$key_account['account_id']."' AND gio.game_id='".$key_account['game_id']."' AND (io.spend_status='unspent' OR io.spend_status='unconfirmed') AND io.io_id != ".$db_io['io_id']." GROUP BY io.io_id ORDER BY colored_amount_sum DESC;";
						else $q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$key_account['account_id']."' AND (io.spend_status='unspent' OR io.spend_status='unconfirmed') AND io.io_id != ".$db_io['io_id']." GROUP BY io.io_id ORDER BY amount DESC;";
						$r = $app->run_query($q);
						
						$html = '<form action="/accounts/" method="get" onsubmit="finish_join_tx(); return false;">';
						$html .= '<select id="join_tx_io_id" name="join_tx_io_id" class="form-control">'."\n";
						$html .= '<option value="">-- Please Select --</option>'."\n";
						while ($db_io = $r->fetch()) {
							$html .= '<option value="'.$db_io['io_id'].'">';
							if ($key_account['game_id'] > 0) $html .= $app->format_bignum($db_io['colored_amount_sum']/pow(10,$db_game['decimal_places'])).' '.$db_game['coin_abbreviation'].' (';
							$html .= $app->format_bignum($db_io['amount']/pow(10,$blockchain->db_blockchain['decimal_places'])).' '.$blockchain->db_blockchain['coin_name_plural'];
							if ($key_account['game_id'] > 0) $html .= ')';
							$html .= ' '.$db_io['address'].'</option>'."\n";
						}
						$html .= "</select>\n";
						$html .= '<button class="btn btn-primary">Join UTXOs</button>'."\n";
						$html .= "</form>\n";
						
						$output_obj['html'] = $html;
						
						$app->output_message(10, "", $output_obj);
					}
					else if ($action == "finish_join_tx") {
						$join_io_id = (int) $_REQUEST['join_io_id'];
						
						$q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id='".$join_io_id."';";
						$r = $app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$join_db_io = $r->fetch();
							
							$q = "SELECT *, c.currency_id AS currency_id FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id='".$join_db_io['address_id']."' AND c.user_id='".$thisuser->db_user['user_id']."';";
							$r = $app->run_query($q);
							
							if ($r->rowCount() > 0) {
								$join_key_account = $r->fetch();
								
								$fee_amount = (int)(0.0001*pow(10,$blockchain->db_blockchain['decimal_places']));
								$amount = $db_io['amount']+$join_db_io['amount']-$fee_amount;
								
								$error_message = false;
								$transaction_id = $blockchain->create_transaction('transaction', array($amount), false, array($db_io['io_id'], $join_db_io['io_id']), array($join_db_io['address_id']), $fee_amount, $error_message);
								
								if ($transaction_id) {
									$app->output_message(13, "Your transaction has been successfully created!", false);
								}
								else $app->output_message(12, "TX Error: ".$error_message, false);
							}
							else $app->output_message(11, "Error, invalid join UTXO ID.", false);
						}
						else $app->output_message(11, "Error, invalid join UTXO ID.", false);
					}
				}
				else $app->output_message(9, "Error, this UTXO is unconfirmed or already spent.", false);
			}
			else $app->output_message(8, "Error, invalid UTXO ID.", false);
		}
		else $app->output_message(8, "Error, invalid UTXO ID.", false);
	}
	else if ($action == "withdraw") {
		$io_id = (int) $_REQUEST['io_id'];
		$withdraw_type = $_REQUEST['withdraw_type'];
		$address = strip_tags($_REQUEST['address']);
		
		$q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id='".$io_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_io = $r->fetch();
			
			$q = "SELECT * FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id='".$db_io['address_id']."' AND c.user_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$key_account = $r->fetch();
				
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
						
						$db_address = $blockchain->create_or_fetch_address($address, true, false, false, false, false);
						
						$amounts = array($amount);
						$address_ids = array($db_address['address_id']);
						
						if ($remainder_amount > 0) {
							array_push($amounts, $remainder_amount);
							array_push($address_ids, $db_io['address_id']);
						}
						
						$error_message = false;
						$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, array($db_io['io_id']), $address_ids, $fee_amount, $error_message);
						
						if ($transaction_id) $app->output_message(1, "Transaction created successfully.", false);
						else $app->output_message(7, "Error: ", false);
					}
					else $app->output_message(6, "Error: not enough coins.", false);
				}
				else {
					$io_game_q = "SELECT g.game_id, g.blockchain_id, SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN games g ON gio.game_id=g.game_id WHERE gio.is_resolved=1 AND gio.io_id='".$db_io['io_id']."' AND g.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' GROUP BY g.game_id;";
					$io_game_r = $app->run_query($io_game_q);
					
					if ($io_game_r->rowCount() == 1) {
						$io_game = $io_game_r->fetch();
						
						$game = new Game($blockchain, $io_game['game_id']);
						
						$amount = $amount*pow(10, $game->db_game['decimal_places']);
						$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
						
						$db_address = $blockchain->create_or_fetch_address($address, true, false, false, false, false);
						
						$coloredcoins_per_coin = $io_game['SUM(gio.colored_amount)']/($db_io['amount']-$fee_amount);
						$io_amount = ceil($amount/$coloredcoins_per_coin);
						$remainder_amount = $db_io['amount']-$fee_amount-$io_amount;
						
						if ($remainder_amount >= 0) {
							$amounts = array($io_amount);
							$address_ids = array($db_address['address_id']);
							
							if ($remainder_amount > 0) {
								array_push($amounts, $remainder_amount);
								array_push($address_ids, $db_io['address_id']);
							}
							
							$error_message = false;
							$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, array($db_io['io_id']), $address_ids, $fee_amount, $error_message);
							
							if ($transaction_id) $app->output_message(1, "Transaction created successfully.", false);
							else $app->output_message(7, "TX Error: ".$error_message, false);
						}
						else $app->output_message(8, "Error: not enough coins.", false);
					}
					else if ($io_game_r->rowCount() == 0) {
						$app->output_message(7, "Error: no game found for this UTXO.", false);
					}
					else $app->output_message(6, "Error: found multiple games associated with this UTXO.", false);
				}
			}
			else $app->output_message(5, "Error, you don't have permission to spend these coins.", false);
		}
		else $app->output_message(4, "Error, invalid UTXO ID.", false);
	}
	else if ($action == "withdraw_from_card") {
		$card_id = (int) $_REQUEST['card_id'];
		$peer_id = (int) $_REQUEST['peer_id'];
		$claim_type = $_REQUEST['claim_type'];
		
		$q = "SELECT c.* FROM cards c LEFT JOIN card_designs d ON c.design_id=d.design_id WHERE c.peer_id='".$peer_id."' AND c.peer_card_id='".$card_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$card = $r->fetch();
			
			if ($card['user_id'] == $thisuser->db_user['user_id']) {
				if ($claim_type == "to_address") {
					$fee = (float) $_REQUEST['fee'];
					$address = $_REQUEST['address'];
					
					if ($fee > 0 && $fee < $card['amount']) {
						$this_peer = $app->get_peer_by_server_name($GLOBALS['base_url'], true);
						
						if ($card['peer_id'] != $this_peer['peer_id']) {
							$remote_peer = $app->run_query("SELECT * FROM peers WHERE peer_id='".$card['peer_id']."';")->fetch();
							
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
							$db_blockchain = $app->run_query("SELECT * FROM blockchains WHERE blockchain_id='".$transaction['blockchain_id']."';")->fetch();
							
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
		$io_id = (int) $_REQUEST['io_id'];
		$amount_each = (float) $_REQUEST['amount_each'];
		$quantity = (int) $_REQUEST['quantity'];
		$game_id = (int) $_REQUEST['game_id'];
		
		$db_game = $app->fetch_db_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$fee = (float) $_REQUEST['fee'];
			$satoshis_each = round(pow(10,$game->db_game['decimal_places'])*$amount_each);
			$fee_amount = (int) ($fee*pow(10,$game->blockchain->db_blockchain['decimal_places']));
			
			if ($quantity > 0 && $satoshis_each > 0) {
				$total_cost_satoshis = $quantity*$satoshis_each;
				
				$db_io = $app->fetch_io_by_id($io_id);
				
				if ($db_io) {
					$q = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id WHERE io.io_id='".$io_id."' AND gio.game_id='".$game->db_game['game_id']."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$game_ios = array();
						$colored_coin_sum = 0;
						
						while ($game_io = $r->fetch()) {
							array_push($game_ios, $game_io);
							$colored_coin_sum += $game_io['colored_amount'];
						}
						
						$coin_sum = $game_ios[0]['amount'];
						$coins_per_chain_coin = (float) $colored_coin_sum/($coin_sum-$fee_amount);
						$chain_coins_each = ceil($satoshis_each/$coins_per_chain_coin);
						
						if ($chain_coins_each > 0 && in_array($game_ios[0]['spend_status'], array("unspent", "unconfirmed"))) {
							$account_q = "SELECT ca.* FROM currency_accounts ca JOIN games g ON g.game_id=ca.game_id JOIN address_keys k ON k.account_id=ca.account_id WHERE ca.user_id='".$thisuser->db_user['user_id']."' AND k.address_id='".$game_ios[0]['address_id']."';";
							$account_r = $app->run_query($account_q);
							
							if ($account_r->rowCount() > 0) {
								$account = $account_r->fetch();
								
								if ($total_cost_satoshis < $colored_coin_sum && $coin_sum > ($chain_coins_each*$quantity*$utxos_each) - $fee_amount) {
									$remainder_satoshis = $coin_sum - ($chain_coins_each*$quantity) - $fee_amount;
									
									$amounts = array();
									$address_ids = array();
									
									for ($i=0; $i<$quantity; $i++) {
										$address_key = $app->new_address_key($account['currency_id'], $account);
										array_push($address_ids, $address_key['address_id']);
										array_push($amounts, $chain_coins_each);
									}
									if ($remainder_satoshis > 0) {
										array_push($amounts, $remainder_satoshis);
										array_push($address_ids, $db_io['address_id']);
									}
									
									$error_message = false;
									$transaction_id = $game->blockchain->create_transaction('transaction', $amounts, false, array($db_io['io_id']), $address_ids, $fee_amount, $error_message);
									
									if ($transaction_id) {
										$app->output_message(1, "/explorer/games/".$db_game['url_identifier']."/transactions/".$transaction_id."/");
									}
									else $app->output_message(11, "TX Error: ".$error_message, false);
								}
								else {
									$app->output_message(10, "UTXO is only ".$app->format_bignum($colored_coin_sum/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." but you tried to spend ".$app->format_bignum($total_cost_satoshis/pow(10,$game->db_game['decimal_places'])), false);
								}
							}
							else $app->output_message(9, "You don't own this UTXO.", false);
						}
						else $app->output_message(8, "Invalid UTXO.", false);
					}
					else $app->output_message(7, "Invalid UTXO ID.", false);
				}
				else $app->output_message(6, "Invalid UTXO ID.", false);
			}
			else $app->output_message(5, "Invalid quantity.", false);
		}
		else $app->output_message(4, "Invalid game ID.", false);
	}
	else $app->output_message(3, "This action is not yet implemented.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>