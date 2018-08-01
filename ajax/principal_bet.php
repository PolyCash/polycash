<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	$amount = (float) $_REQUEST['amount'];
	$option_id = (int) $_REQUEST['option_id'];
	$fee = (float) $_REQUEST['fee'];
	$fee_total = round($fee*pow(10, $game->blockchain->db_blockchain['decimal_places']));
	$amount_total = round($amount*pow(10, $game->blockchain->db_blockchain['decimal_places']));
	
	$option_q = "SELECT * FROM options WHERE option_id='".$option_id."';";
	$option_r = $app->run_query($option_q);
	
	if ($option_r->rowCount() > 0) {
		$option = $option_r->fetch();
		
		$address_q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$user_game['account_id']."' AND a.option_index='".$option['option_index']."';";
		$address_r = $app->run_query($address_q);
		
		if ($address_r->rowCount() > 0) {
			$address = $address_r->fetch();
			
			$destroy_address_q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$user_game['account_id']."' AND a.option_index='0';";
			$destroy_address_r = $app->run_query($destroy_address_q);
			
			if ($destroy_address_r->rowCount() > 0) {
				$destroy_address = $destroy_address_r->fetch();
				
				$last_block_id = $game->blockchain->last_block_id();
				$mining_block_id = $last_block_id+1;
				
				$q = "SELECT *, SUM(gio.colored_amount) AS coins, SUM(gio.colored_amount)*(".$mining_block_id."-io.create_block_id) AS coin_blocks FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.is_resolved=1 AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$user_game['account_id']."' GROUP BY gio.io_id ORDER BY coin_blocks ASC;";
				$r = $app->run_query($q);
				
				$io_amount_sum = 0;
				$game_amount_sum = 0;
				$io_ids = array();
				$keep_looping = true;
				
				while ($keep_looping && $io = $r->fetch()) {
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
						
						$transaction_id = $game->blockchain->create_transaction("transaction", array($destroy_io_amount, $nondestroy_io_amount), false, $io_ids, array($destroy_address['address_id'], $address['address_id']), $fee_total);
						
						if ($transaction_id) {
							$app->output_message(1, "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$game->blockchain->db_blockchain['url_identifier']."/transactions/".$transaction_id."/\">View Transaction</a>", false);
						}
						else {
							$app->output_message(8, "Error: failed to create the transaction.", false);
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