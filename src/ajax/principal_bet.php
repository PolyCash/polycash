<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$user_game_account = $app->fetch_account_by_id($user_game['account_id']);
	
	$amount = (float) $_REQUEST['amount'];
	$option_id = (int) $_REQUEST['option_id'];
	$fee = (float) $_REQUEST['fee'];
	$fee_total = round($fee*pow(10, $game->blockchain->db_blockchain['decimal_places']));
	$amount_total = round($amount*pow(10, $game->blockchain->db_blockchain['decimal_places']));
	
	$option = $app->fetch_option_by_id($option_id);
	
	if ($option) {
		$address = $app->fetch_addresses_in_account($user_game_account, $option['option_index'], 1)[0];
		
		if ($address) {
			$destroy_address = $app->fetch_addresses_in_account($user_game_account, 0, 1)[0];
			
			if ($destroy_address) {
				$last_block_id = $game->blockchain->last_block_id();
				$mining_block_id = $last_block_id+1;
				$round_id = $game->block_to_round($mining_block_id);
				
				$spendable_ios_in_account = $app->spendable_ios_in_account($user_game_account['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
				
				$io_amount_sum = 0;
				$game_amount_sum = 0;
				$io_ids = array();
				$keep_looping = true;
				
				while ($keep_looping && $io = $spendable_ios_in_account->fetch()) {
					$game_amount_sum += $io['coins'];
					$io_amount_sum += $io['amount'];
					array_push($io_ids, $io['io_id']);
					
					if ($game_amount_sum >= $amount_total && $io_amount_sum >= $fee_total) $keep_looping = false;
				}
				
				if ($game_amount_sum >= $amount_total) {
					$io_nonfee_amount = $io_amount_sum-$fee_total;
					
					if ($io_nonfee_amount > 0) {
						$destroy_frac = $amount_total/$game_amount_sum;
						$destroy_io_amount = ceil($destroy_frac*$io_nonfee_amount);
						$nondestroy_io_amount = $io_nonfee_amount-$destroy_io_amount;
						$error_message = false;
						
						$transaction_id = $game->blockchain->create_transaction("transaction", array($destroy_io_amount, $nondestroy_io_amount), false, $io_ids, array($destroy_address['address_id'], $address['address_id']), $fee_total, $error_message);
						
						if ($transaction_id) {
							$app->output_message(1, "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$game->blockchain->db_blockchain['url_identifier']."/transactions/".$transaction_id."/\">View Transaction</a>", false);
						}
						else {
							$app->output_message(8, "TX Error: ".$error_message, false);
						}
					}
					else $app->output_message(7, "Not enough ".$game->blockchain->db_blockchain['coin_name_plural'].": ".$app->format_bignum($io_amount_sum/pow(10, $game->blockchain->db_blockchain['decimal_places']))." available.", false);
				}
				else $app->output_message(6, "You don't have enough coins to place this bet.", false);
			}
			else $app->output_message(5, "Transaction failed: no destroy address found in this account.", false);
		}
		else {
			$thisuser->generate_user_addresses($game, $user_game);
			$app->output_message(4, "Error, no address found for this option. Please try again.", false);
		}
	}
	else $app->output_message(3, "Error, invalid option ID.", false);
}
else $app->output_message(2, "Invalid user or game.", false);
?>