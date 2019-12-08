<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$user_strategy = false;
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$account = $app->fetch_account_by_id($user_game['account_id']);
	$success = $game->get_user_strategy($user_game, $user_strategy);
	
	if (!$success) {
		$app->output_message(2, "Error, the transaction fee amount could not be determined.", false);
		die();
	}
	
	$fee = (int)($user_strategy['transaction_fee']*pow(10, $game->blockchain->db_blockchain['decimal_places']));
	
	$burn_amount = (int) $_REQUEST['burn_amount'];
	$address_ids = [];
	$io_ids = explode(",", $_REQUEST['io_ids']);
	$amounts = array_map('intval', explode(",", $_REQUEST['amounts']));
	$option_ids = explode(",", $_REQUEST['option_ids']);
	
	// This step pulls in IOs in this account which have no game IO
	// (usually coins sent to a delete address in a betting tx)
	// without this, the user will run out of chain coins after several bets
	$recommended_recycle_ios = 2;
	$recycle_ios = $app->fetch_recycle_ios_in_account($account['account_id'], $recommended_recycle_ios);
	
	if (count($recycle_ios) > 0) {
		$recycle_amount = 0;
		
		foreach ($recycle_ios as $recycle_io) {
			$recycle_amount += $recycle_io['amount'];
			array_push($io_ids, $recycle_io['io_id']);
		}
		
		$amount_sum = array_sum($amounts);
		$initial_amount_sum = $amount_sum+$burn_amount;
		$new_amount_sum = $initial_amount_sum+$recycle_amount;
		
		$new_burn_amount = ceil($burn_amount*($new_amount_sum/$initial_amount_sum));
		$new_remainder_amount = $new_amount_sum-$new_burn_amount;
		
		for ($amount_i=0; $amount_i<count($amounts); $amount_i++) {
			$amounts[$amount_i] = round($amounts[$amount_i]*$new_remainder_amount/$amount_sum);
		}
		
		$overshoot_amount = array_sum($amounts)-$new_remainder_amount;
		$amounts[count($amounts)-1] -= $overshoot_amount;
		$burn_amount = $new_burn_amount;
	}
	
	// Now run some sanity checks
	if ($burn_amount == 0 && $game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] == 0) {
		$app->output_message(3, "How many ".$game->db_game['coin_name_plural']." do you want to spend?", false);
		die();
	}
	
	if (count($amounts) != count($option_ids)) {
		$app->output_message(4, "Option IDs and amounts do not match", false);
		die();
	}
	
	$amount_sum = 0;
	for ($i=0; $i<count($amounts); $i++) {
		if ($amounts[$i] < 0) {
			$app->output_message(5, "Invalid amount specified.", false);
			die();
		}
		$amount_sum += $amounts[$i];
	}
	
	$gio_info = $app->run_query("SELECT COUNT(*), SUM(gio.colored_amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.io_id IN (".implode(",", array_map("intval", $io_ids)).") AND k.account_id=:account_id;", [
		'account_id' => $user_game['account_id']
	])->fetch();
	
	$io_info = $app->run_query("SELECT COUNT(*), SUM(io.amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.io_id IN (".implode(",", array_map("intval", $io_ids)).") AND k.account_id=:account_id;", [
		'account_id' => $user_game['account_id']
	])->fetch();
	
	if (count($io_ids) == 0 || count($amounts) == 0 || $io_info['COUNT(*)'] != count($io_ids)) {
		$app->output_message(6, "Error: invalid amounts or IO IDs.", false);
		die();
	}
	
	$max_burn_frac = 0.9;
	$max_burn_amount = floor($io_info['SUM(io.amount)']*$max_burn_frac);
	$gio_max_burn_amount = floor($gio_info['SUM(gio.colored_amount)']*$max_burn_frac);
	
	if ($burn_amount > $max_burn_amount) {
		$app->output_message(7, "Please spend a maximum of ".$app->format_bignum($gio_max_burn_amount/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural'].", or add more ".$game->db_game['coin_name_plural']." to this transaction.", false);
		die();
	}
	else if ($io_info['SUM(io.amount)'] != $fee+$burn_amount+$amount_sum) {
		$app->output_message(8, "Error: amounts don't add up correctly: ".$io_info['SUM(io.amount)']." vs ($fee+$burn_amount+$amount_sum)", false);
		die();
	}
	
	// Now create the transaction
	$separator_addresses = $app->fetch_addresses_in_account($account, 1, count($option_ids));
	$separator_frac = 0.25;
	$new_amounts = [];
	$new_address_ids = [];
	
	if ($burn_amount > 0) {
		$burn_address = $app->fetch_addresses_in_account($account, 0, 1)[0];
		
		array_push($new_amounts, $burn_amount);
		array_push($new_address_ids, $burn_address['address_id']);
	}
	
	for ($i=0; $i<count($option_ids); $i++) {
		$db_option = $app->fetch_option_by_id($option_ids[$i]);
		
		if ($db_option && $db_option['game_id'] == $game->db_game['game_id']) {
			$db_address = $app->fetch_addresses_in_account($account, $db_option['option_index'], 1)[0];
			
			if ($db_address) {
				array_push($address_ids, $db_address['address_id']);
				
				if (count($separator_addresses) > 0) {
					$separator_amount = floor($separator_frac*$amounts[$i]);
					$new_amount = $amounts[$i]-$separator_amount;
					
					array_push($new_amounts, $new_amount);
					array_push($new_address_ids, $address_ids[$i]);
					
					array_push($new_amounts, $separator_amount);
					array_push($new_address_ids, $separator_addresses[$i%count($separator_addresses)]['address_id']);
				}
				else {
					array_push($new_amounts, $amounts[$i]);
					array_push($new_address_ids, $address_ids[$i]);
				}
			}
			else {
				$app->output_message(9, "Error: no address for option #".$db_option['option_index'], false);
				die();
			}
		}
		else {
			$app->output_message(10, "Error: you supplied an invalid option ID.", false);
			die();
		}
	}
	
	$error_message = false;
	$transaction_id = $game->blockchain->create_transaction("transaction", $new_amounts, false, $io_ids, $new_address_ids, $fee, $error_message);
	
	if ($transaction_id) {
		$game->update_option_votes();
		
		$transaction = $app->fetch_transaction_by_id($transaction_id);
		
		$app->output_message(1, "Your transaction has been submitted! <a href=\"/explorer/games/".$game->db_game['url_identifier']."/transactions/".$transaction['tx_hash']."\">Details</a>", false);
	}
	else $app->output_message(11, "There was an error creating the transaction: ".$error_message, false);
}
else $app->output_message(12, "Please log in.", false);
?>
