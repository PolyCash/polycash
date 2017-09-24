<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	$game_id = (int) $_REQUEST['game_id'];
	$io_id = (int) $_REQUEST['io_id'];
	
	if ($action == "buyin") {
		$buyin_amount = (int) ($_REQUEST['buyin_amount']*pow(10,8));
		$fee_amount = (int) ($_REQUEST['fee_amount']*pow(10,8));
		
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$db_currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$game->blockchain->currency_id()."';")->fetch();
			
			$db_io = $app->run_query("SELECT * FROM transaction_ios WHERE io_id='".$io_id."';")->fetch();
			
			if ($db_io) {
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
						$coin_rpc = new jsonRPCClient('http://'.$game->blockchain->db_blockchain['rpc_username'].':'.$game->blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$game->blockchain->db_blockchain['rpc_port'].'/');
						$color_address = $game->blockchain->create_or_fetch_address($address_text, true, $coin_rpc, false, false, false);
					}
					
					$error_message = false;
					$transaction_id = $game->create_transaction(false, array($buyin_amount, $color_amount), $user_game, false, 'transaction', array($io_id), array($escrow_address['address_id'], $color_address['address_id']), false, $fee_amount, $error_message);
					
					if ($transaction_id) {
						$app->output_message(1, "Great, your transaction has been submitted (#".$transaction_id.")", false);
					}
					else {
						$app->output_message(7, $error_message, false);
					}
				}
				else $app->output_message(6, "Error, one of the amounts you entered is invalid ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0).", false);
			}
			else $app->output_message(5, "Error, incorrect io_id.", false);
		}
		else $app->output_message(4, "Error, incorrect game_id", false);
	}
	else if ($action == "start_join_tx" || $action = "finish_join_tx") {
		$io_id = (int) $_REQUEST['io_id'];
		
		$q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id='".$io_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_io = $r->fetch();
			
			$q = "SELECT *, c.currency_id AS currency_id FROM address_keys k JOIN currency_accounts c ON k.account_id=c.account_id WHERE k.address_id='".$db_io['address_id']."' AND c.user_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$key_account = $r->fetch();
				
				if ($db_io['spend_status'] == "unspent") {
					if ($key_account['game_id'] > 0) {
						$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$key_account['game_id']."';")->fetch();
						$blockchain = new Blockchain($app, $db_game['blockchain_id']);
						
						if ($action == "start_join_tx") {
							$q = "SELECT *, SUM(gio.colored_amount) AS colored_amount_sum FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$key_account['account_id']."' AND gio.game_id='".$key_account['game_id']."' AND io.spend_status='unspent' AND io.io_id != ".$db_io['io_id']." GROUP BY io.io_id ORDER BY colored_amount_sum DESC;";
							$r = $app->run_query($q);
							
							$html = '<form action="/accounts/" method="get" onsubmit="finish_join_tx(); return false;">';
							$html .= '<select id="join_tx_io_id" name="join_tx_io_id" class="form-control">'."\n";
							$html .= '<option value="">-- Please Select --</option>'."\n";
							while ($db_io = $r->fetch()) {
								$html .= '<option value="'.$db_io['io_id'].'">'.$app->format_bignum($db_io['colored_amount_sum']/pow(10,8)).' '.$db_game['coin_abbreviation'].' ('.$app->format_bignum($db_io['amount']/pow(10,8)).' '.$blockchain->db_blockchain['coin_name_plural'].') '.$db_io['address'].'</option>'."\n";
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
									
									$fee_amount = 0.001*pow(10,8);
									$amount = $db_io['amount']+$join_db_io['amount']-$fee_amount;
									
									$transaction_id = $blockchain->create_transaction('transaction', array($amount), false, array($db_io['io_id'], $join_db_io['io_id']), array($join_db_io['address_id']), $fee_amount);
									
									if ($transaction_id) {
										$app->output_message(13, "Your transaction has been successfully created!", false);
									}
									else $app->output_message(12, "Error, failed to create transaction.", false);
								}
								else $app->output_message(11, "Error, invalid join UTXO ID.", false);
							}
							else $app->output_message(11, "Error, invalid join UTXO ID.", false);
						}
					}
				}
				else $app->output_message(9, "Error, this UTXO is unconfirmed or already spent.", false);
			}
			else $app->output_message(8, "Error, invalid UTXO ID.", false);
		}
		else $app->output_message(8, "Error, invalid UTXO ID.", false);
	}
	else $app->output_message(3, "This action is not yet implemented.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>