<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$api_output = false;

$noinfo_fail_obj = (object) [
	'status_code' => 1,
	'message' => "Invalid URL"
];

if ($thisuser && $game) {
	$user_strategy = false;
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$success = $game->get_user_strategy($user_game, $user_strategy);
	
	if (!$success) {
		$api_output = (object)[
			'status_code' => 2,
			'message' => "Error, the transaction fee amount could not be determined."
		];
		echo json_encode($api_output);
		die();
	}
	
	$account_value = $game->account_balance($user_game['account_id']);
	$immature_balance = $thisuser->immature_balance($game, $user_game);
	$mature_balance = $thisuser->mature_balance($game, $user_game);
	
	$io_ids_csv = $_REQUEST['io_ids'];
	$io_ids = explode(",", $io_ids_csv);
	
	$option_ids_csv = $_REQUEST['option_ids'];
	$option_ids = explode(",", $option_ids_csv);
	$int_option_ids = [];
	for ($i=0; $i<count($option_ids); $i++) {
		$int_option_ids[$i] = (int) $option_ids[$i];
	}
	$option_ids = $int_option_ids;
	
	$amounts_csv = $_REQUEST['amounts'];
	$amounts = explode(",", $amounts_csv);
	
	if (count($io_ids) > 0 && count($amounts) > 0) {}
	else {
		$api_output = $noinfo_fail_obj;
		echo json_encode($api_output);
		die();
	}
	
	for ($i=0; $i<count($option_ids); $i++) {
		$int_option_ids[$i] = (int) $option_ids[$i];
	}
	
	if (count($amounts) != count($option_ids)) {
		$api_output = (object)[
			'status_code' => 2,
			'message' => "Option IDs and amounts do not match"
		];
		echo json_encode($api_output);
		die();
	}
	
	$io_mature_balance = 0;
	
	for ($i=0; $i<count($io_ids); $i++) {
		$io_id = (int) $io_ids[$i];
		
		if ($io_id > 0) {
			$qq = "SELECT * FROM transaction_ios WHERE io_id='".$io_id."';";
			$rr = $app->run_query($qq);
			
			if ($rr->rowCount() == 1) {
				$io = $rr->fetch();
				
				$io_mature_balance += $io['amount'];
			}
			else {
				$api_output = $noinfo_fail_obj;
				echo json_encode($api_output);
				die();
			}
		}
		else {
			$api_output = $noinfo_fail_obj;
			echo json_encode($api_output);
			die();
		}
	}
	
	$amount_sum = 0;
	
	for ($i=0; $i<count($option_ids); $i++) {
		$amount = $amounts[$i];
		if ($amount > 0) {
			$amounts[$i] = $amount;
			$amount_sum += $amount;
		}
		else {
			$api_output = (object)[
				'status_code' => 5,
				'message' => "An invalid amount was included."
			];
			echo json_encode($api_output);
			die();
		}
	}
	
	$fee_amount = $user_strategy['transaction_fee']*pow(10, $game->blockchain->db_blockchain['decimal_places']);
	
	$real_amounts = [];
	$real_amount_sum = 0;
	for ($i=0; $i<count($option_ids)-1; $i++) {
		$real_amount = floor(($io_mature_balance-$fee_amount)*$amounts[$i]/$amount_sum);
		$real_amounts[$i] = $real_amount;
		$real_amount_sum += $real_amount;
	}
	$real_amounts[count($option_ids)-1] = $io_mature_balance - $fee_amount - $real_amount_sum;
	
	$last_block_id = $game->blockchain->last_block_id();
	
	$error_message = false;
	$transaction_id = $game->create_transaction($option_ids, $real_amounts, $user_game, false, 'transaction', $io_ids, false, false, $fee_amount, $error_message);
	
	if ($transaction_id) {
		$game->update_option_votes();
		
		$q = "SELECT * FROM transactions WHERE transaction_id='".$transaction_id."';";
		$r = $app->run_query($q);
		$transaction = $r->fetch();
		
		$api_output = (object)[
			'status_code' => 0,
			'message' => "Your voting transaction has been submitted! <a href=\"/explorer/games/".$game->db_game['url_identifier']."/transactions/".$transaction['tx_hash']."\">Details</a>"
		];
	}
	else {
		$api_output = (object)[
			'status_code' => 7,
			'message' => $error_message
		];
	}
}
else {
	$api_output = (object)[
		'status_code' => 9,
		'message' => 'Please <a href=\"/wallet/\">log in</a>.'
	];
}

echo json_encode($api_output);
?>
