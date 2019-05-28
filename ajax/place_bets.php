<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
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
	if ($burn_amount == 0 && $game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] == 0) {
		$app->output_message(3, "How many ".$game->db_game['coin_name_plural']." do you want to spend?", false);
		die();
	}
	
	$address_ids = array();
	$io_ids = explode(",", $_REQUEST['io_ids']);
	$amounts = explode(",", $_REQUEST['amounts']);
	$option_ids = explode(",", $_REQUEST['option_ids']);
	
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
		$amount_sum += (int) $amounts[$i];
	}
	
	$gio_q = "SELECT COUNT(*), SUM(gio.colored_amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.io_id IN (".implode(",", $io_ids).") AND k.account_id='".$user_game['account_id']."';";
	$gio_r = $app->run_query($gio_q);
	$gio_info = $gio_r->fetch();
	
	$io_q = "SELECT COUNT(*), SUM(io.amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.io_id IN (".implode(",", $io_ids).") AND k.account_id='".$user_game['account_id']."';";
	$io_r = $app->run_query($io_q);
	$io_info = $io_r->fetch();
	
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
		$app->output_message(8, "Error: amounts don't add up correctly: ".$io_info['SUM(io.amount)']." vs ".($fee+$burn_amount+$amount_sum), false);
		die();
	}
	
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
		$option_q = "SELECT * FROM options op JOIN events ev ON op.event_id=ev.event_id WHERE op.option_id='".$option_ids[$i]."' AND ev.game_id='".$game->db_game['game_id']."';";
		$option_r = $app->run_query($option_q);
		
		if ($option_r->rowCount() > 0) {
			$db_option = $option_r->fetch();
			$db_address = $app->fetch_addresses_in_account($account, $db_option['option_index'], 1)[0];
			
			if ($db_address) {
				array_push($address_ids, $db_address['address_id']);
				
				$separator_amount = floor($separator_frac*$amounts[$i]);
				$new_amount = $amounts[$i]-$separator_amount;
				
				array_push($new_amounts, $new_amount);
				array_push($new_address_ids, $address_ids[$i]);
				
				array_push($new_amounts, $separator_amount);
				array_push($new_address_ids, $separator_addresses[$i%count($separator_addresses)]['address_id']);
			}
			else {
				$app->output_message(9, "Error: no address for option #".$option_ids[$i], false);
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
	else {
		$app->output_message(11, "There was an error creating the transaction: ".$error_message, false);
	}
}
else {
	$app->output_message(12, "Please log in.", false);
}
?>
