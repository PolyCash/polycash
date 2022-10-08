<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($game && $thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
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
		
		$blockchain_spendable_balance = $game->blockchain->account_balance($user_game['account_id'], true);
		
		if ($fee < $blockchain_spendable_balance) {
			$spendable_ios_in_account = $app->spendable_ios_in_account($user_game['account_id'], $game->db_game['game_id'], $round_id, $last_block_id)->fetchAll();
			
			$account_balance = 0;
			foreach ($spendable_ios_in_account as $io) {
				$account_balance += $io['coins'];
			}
			
			if ($account_balance >= $target_amount) {
				// If withdraw amount is within 0.01% of total balance, round up rather than leaving a tiny amount behind
				$initial_remainder_amount = $account_balance-$target_amount;
				$remainder_frac = $initial_remainder_amount/$target_amount;
				$withdrawing_all = false;
				if ($remainder_frac < 1/10000 && $initial_remainder_amount < 1*pow(10, $game->db_game['decimal_places'])) {
					$withdrawing_all = true;
					$target_amount += $initial_remainder_amount;
				}
				
				$gio_sum = 0;
				$io_sum = 0;
				$spend_io_ids = [];
				$keep_looping = true;
				$io_pos = 0;
				
				while ($keep_looping && $io_pos < count($spendable_ios_in_account)) {
					$io = $spendable_ios_in_account[$io_pos];
					
					array_push($spend_io_ids, $io['io_id']);
					
					$gio_sum += $io['coins'];
					$io_sum += $io['amount'];
					
					if ($gio_sum >= $target_amount) $keep_looping = false;
					
					$io_pos++;
				}
				
				if ($withdrawing_all) {
					$recycle_ios = $app->fetch_recycle_ios_in_account($user_game['account_id'], null);
					
					foreach ($recycle_ios as $recycle_io) {
						array_push($spend_io_ids, $recycle_io['io_id']);
						$io_sum += $recycle_io['amount'];
					}
				}
				
				$coloredcoins_per_coin = $gio_sum/($io_sum-$fee);
				
				$io_amount = ceil($target_amount/$coloredcoins_per_coin);
				$remainder_amount = ($io_sum-$fee)-$io_amount;
				
				$address_ok = false;
				
				$game->blockchain->load_coin_rpc();
				
				if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
					$validate_address = $game->blockchain->coin_rpc->validateaddress($address_text);
					$address_ok = $validate_address['isvalid'];
					if ($address_ok) {
						$db_address = $game->blockchain->create_or_fetch_address($address_text, false, null);
					}
				}
				else {
					$db_address = $game->blockchain->create_or_fetch_address($address_text, false, null);
					$address_ok = true;
				}
				
				if ($address_ok) {
					$amounts = [$io_amount];
					$address_ids = [$db_address['address_id']];
					
					if ($remainder_amount > 0) {
						$remainder_address = $app->any_normal_address_in_account($user_game['account_id']);
						array_push($amounts, $remainder_amount);
						array_push($address_ids, $remainder_address['address_id']);
					}
					
					$error_message = null;
					$transaction_id = $game->blockchain->create_transaction("transaction", $amounts, null, $spend_io_ids, $address_ids, $fee, $error_message);
					
					if ($transaction_id) {
						$transaction = $app->fetch_transaction_by_id($transaction_id);
						$app->output_message(1, 'Great, your '.$game->db_game['coin_name_plural'].' have been sent! <a target="_blank" href="/explorer/games/'.$game->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'">View Transaction</a>', false);
					}
					else $app->output_message(8, $error_message, false);
				}
				else $app->output_message(7, "It looks like you entered an invalid address.", false);
			}
			else $app->output_message(6, "You don't have that many coins to spend, your transaction has been canceled.", false);
		}
		else $app->output_message(5, "You don't have that many coins to spend, your transaction has been canceled.", false);
	}
	else $app->output_message(3, "Please enter a valid amount.", false);
}
else $app->output_message(2, "Please log in to withdraw coins.", false);
?>
