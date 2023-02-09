<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$account = $app->fetch_account_by_id($user_game['account_id']);
	
	$amount = (float) $_REQUEST['amount'];
	$option_id = (int) $_REQUEST['option_id'];
	$fee = (float) $_REQUEST['fee'];
	$fee_int = round($fee*pow(10, $game->blockchain->db_blockchain['decimal_places']));
	$amount_int = round($amount*pow(10, $game->db_game['decimal_places']));
	
	$coins_per_vote = $app->coins_per_vote($game->db_game);
	
	$option = $app->fetch_option_by_id($option_id);
	
	if ($option) {
		$address = $app->fetch_addresses_in_account($account, $option['option_index'], 1)[0];
		
		if ($address) {
			$destroy_address = $app->fetch_addresses_in_account($account, 0, 1)[0];
			$separator_address = $app->fetch_addresses_in_account($account, 1, 1)[0];
			
			if ($destroy_address && $separator_address) {
				$last_block_id = $game->blockchain->last_block_id();
				$mining_block_id = $last_block_id+1;
				$round_id = $game->block_to_round($mining_block_id);
				
				$spendable_ios_in_account = $app->spendable_ios_in_account($account['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
				
				$mandatory_bets = 0;
				$io_amount_in = 0;
				$game_amount_in = 0;
				$io_ids = [];
				$burn_game_amount = 0;
				$keep_looping = true;
				
				while ($keep_looping && $io = $spendable_ios_in_account->fetch()) {
					$game_amount_in += $io['coins'];
					$io_amount_in += $io['amount'];
					array_push($io_ids, $io['io_id']);
					
					if ($game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] > 0) {
						if ($game->db_game['payout_weight'] == "coin_block") $votes = $io['coin_blocks'];
						else if ($game->db_game['payout_weight'] == "coin_round") $votes = $io['coin_rounds'];
						$this_mandatory_bets = floor($votes*$coins_per_vote);
					}
					else $this_mandatory_bets = 0;
					
					$mandatory_bets += $this_mandatory_bets;
					
					$burn_game_amount = $amount_int-$mandatory_bets;
					if ($game_amount_in >= $burn_game_amount*1.2) $keep_looping = false;
				}
				
				$recycle_ios = $app->fetch_recycle_ios_in_account($user_game['account_id'], false);
				
				foreach ($recycle_ios as $recycle_io) {
					array_push($io_ids, $recycle_io['io_id']);
					$io_amount_in += $recycle_io['amount'];
				}
				
				$io_nonfee_amount = $io_amount_in-$fee_int;
				$game_coins_per_coin = $game_amount_in/$io_nonfee_amount;
				
				$burn_amount = ceil($burn_game_amount/$game_coins_per_coin);
				
				$io_nonfee_amount = $io_amount_in-$fee_int;
				$io_nondestroy_amount = $io_nonfee_amount - $burn_amount;
				
				if ($io_nondestroy_amount > 0) {
					$io_separator_frac = 0.25;
					$error_message = false;
					
					$io_amounts = [$burn_amount];
					$address_ids = [$destroy_address['address_id']];
					
					$bet_addr_amount = round($io_nondestroy_amount*(1-$io_separator_frac));
					$separator_amount = $io_nondestroy_amount-$bet_addr_amount;
					
					array_push($io_amounts, $bet_addr_amount);
					array_push($address_ids, $address['address_id']);
					
					array_push($io_amounts, $separator_amount);
					array_push($address_ids, $separator_address['address_id']);
					
					$transaction_id = $game->blockchain->create_transaction("transaction", $io_amounts, false, $io_ids, $address_ids, $fee_int, $error_message);
					
					if ($transaction_id) {
						$transaction = $app->fetch_transaction_by_id($transaction_id);
						$app->output_message(1, "Great, your transaction was submitted. <a href=\"/explorer/games/".$game->db_game['url_identifier']."/transactions/".$transaction['tx_hash']."\">View Transaction</a>", false);
					}
					else {
						$app->output_message(8, "TX Error: ".$error_message, false);
					}
				}
				else $app->output_message(7, "Transaction failed: you don't have enough ".$game->db_game['coin_name_plural'].".", false);
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