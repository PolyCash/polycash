<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	$target_amount = floatval($_REQUEST['amount']);
	$fee = floatval($_REQUEST['fee']);
	$address_text = $_REQUEST['address'];
	
	if ($target_amount > 0 && $fee >= 0) {
		$target_amount = $target_amount*pow(10,$game->db_game['decimal_places']);
		$fee = (int)($fee*pow(10,$game->blockchain->db_blockchain['decimal_places']));
		$last_block_id = $game->blockchain->last_block_id();
		$mining_block_id = $last_block_id+1;
		$round_id = $game->block_to_round($mining_block_id);
		
		$blockchain_mature_balance = $game->blockchain->user_mature_balance($user_game);
		$game_mature_balance = $thisuser->mature_balance($game, $user_game);
		
		$remainder_address = $app->any_normal_address_in_account($user_game['account_id']);
		
		$user_strategy = false;
		$success = $game->get_user_strategy($user_game, $user_strategy);
		
		if ($success) {
			if ($fee < $blockchain_mature_balance) {
				$spendable_ios_in_account = $app->spendable_ios_in_account($user_game['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
				
				$gio_sum = 0;
				$io_sum = 0;
				$spend_io_ids = array();
				$keep_looping = true;
				
				while ($keep_looping && $io = $r->fetch()) {
					array_push($spend_io_ids, $io['io_id']);
					
					$gio_sum += $io['coins'];
					$io_sum += $io['amount'];
					
					if ($gio_sum >= $target_amount) $keep_looping = false;
				}
				
				if ($gio_sum >= $target_amount) {
					$coloredcoins_per_coin = $gio_sum/($io_sum-$fee);
					
					$io_amount = ceil($target_amount/$coloredcoins_per_coin);
					$remainder_amount = ($io_sum-$fee)-$io_amount;
					
					$address_ok = false;
					
					$game->blockchain->load_coin_rpc();
					
					if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
						$validate_address = $game->blockchain->coin_rpc->validateaddress($address_text);
						$address_ok = $validate_address['isvalid'];
						if ($address_ok) {
							$db_address = $game->blockchain->create_or_fetch_address($address_text, true, false, false, false, false);
						}
					}
					else {
						$db_address = $game->blockchain->create_or_fetch_address($address_text, true, false, false, false, false);
						$address_ok = true;
					}
					
					if ($address_ok) {
						$error_message = false;
						$transaction_id = $game->create_transaction(false, array($io_amount, $remainder_amount), $user_game, false, 'transaction', $spend_io_ids, array($db_address['address_id'], $remainder_address['address_id']), false, $fee, $error_message);
						
						if ($transaction_id) {
							$transaction = $app->fetch_transaction_by_id($transaction_id);
							$app->output_message(1, 'Great, your '.$game->db_game['coin_name_plural'].' have been sent! <a target="_blank" href="/explorer/games/'.$game->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'">View Transaction</a>', false);
						}
						else $app->output_message(8, $error_message, false);
					}
					else $app->output_message(7, "It looks like you entered an invalid address.", false);
				}
				else $app->output_message(6, "You don't have any single UTXO with that many ".$game->db_game['coin_name_plural'].". Try making several smaller transactions.", false);
			}
			else $app->output_message(5, "You don't have that many coins to spend, your transaction has been canceled.", false);
		}
		else $app->output_message(4, "It looks like you entered an invalid address.", false);
	}
	else $app->output_message(3, "Please enter a valid amount.", false);
}
else $app->output_message(2, "Please log in to withdraw coins.", false);
?>
