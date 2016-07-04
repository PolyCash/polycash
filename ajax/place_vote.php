<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$api_output = false;

$noinfo_fail_obj = (object) [
	'status_code' => 1,
	'message' => "Invalid URL"
];

if ($thisuser && $game) {
	$user_strategy = false;
	$success = get_user_strategy($thisuser['user_id'], $game['game_id'], $user_strategy);
	if (!$success) {
		$api_output = (object)[
			'status_code' => 2,
			'message' => "Error, the transaction fee amount could not be determined."
		];
		echo json_encode($api_output);
		die();
	}
	
	$account_value = account_coin_value($game, $thisuser);
	$immature_balance = immature_balance($game, $thisuser);
	$mature_balance = mature_balance($game, $thisuser);
	
	$io_ids_csv = $_REQUEST['io_ids'];
	$io_ids = explode(",", $io_ids_csv);
	
	$option_ids_csv = $_REQUEST['option_ids'];
	$option_ids = explode(",", $option_ids_csv);
	$int_option_ids = [];
	for ($i=0; $i<count($option_ids); $i++) {
		$int_option_ids[$i] = intval($option_ids[$i]);
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
		$int_option_ids[$i] = intval($option_ids[$i]);
	}
	
	if (count($amounts) != count($option_ids)) {
		$api_output = (object)[
			'status_code' => 2,
			'message' => "Option IDs and amounts do not match"
		];
		echo json_encode($api_output);
		die();
	}
	
	for ($i=0; $i<count($io_ids); $i++) {
		$io_id = intval($io_ids[$i]);
		if ($io_id > 0) {
			$qq = "SELECT * FROM transaction_IOs WHERE io_id='".$io_id."';";
			$rr = run_query($qq);
			if (mysql_numrows($rr) == 1) {
				$io = mysql_fetch_array($rr);
				
				if ($io['user_id'] != $thisuser['user_id'] || $io['spend_status'] != "unspent" || $io['game_id'] != $game['game_id']) {
					$api_output = $noinfo_fail_obj;
					echo json_encode($api_output);
					die();
				}
				else {
					if ($io['create_block_id'] <= last_block_id($game['game_id'])-$game['maturity'] || $io['instantly_mature'] == 1) {
						$io_ids[$i] = $io_id;
					}
					else {
						$api_output = (object)[
							'status_code' => 3,
							'message' => "One of the coin inputs you selected is not yet mature."
						];
						echo json_encode($api_output);
						die();
					}
				}
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
		$option_id = intval($option_ids[$i]);
		if ($option_id > 0 && $option_id <= 16) {
			$option_ids[$i] = $option_id;
		}
		else {
			$api_output = (object)[
				'status_code' => 4,
				'message' => "Invalid option ID"
			];
			echo json_encode($api_output);
			die();
		}
		
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
	
	$last_block_id = last_block_id($game['game_id']);
	
	if (($last_block_id+1)%$game['round_length'] == 0) {
		$api_output = (object)[
			'status_code' => 6,
			'message' => "The final block of the round is being mined, so you can't vote right now."
		];
	}
	else {
		if ($amount_sum+$user_strategy['transaction_fee'] <= $mature_balance && $amount_sum > 0) {
			$transaction_id = new_transaction($game, $option_ids, $amounts, $thisuser['user_id'], $thisuser['user_id'], false, 'transaction', $io_ids, false, false, intval($user_strategy['transaction_fee']));
			
			if ($transaction_id) {
				update_option_scores($game);
				
				$q = "SELECT * FROM transactions WHERE transaction_id='".$transaction_id."';";
				$r = run_query($q);
				$transaction = mysql_fetch_array($r);
				
				$api_output = (object)[
					'status_code' => 0,
					'message' => "Your voting transaction has been submitted! <a href=\"/explorer/".$game['url_identifier']."/transactions/".$transaction['tx_hash']."\">Details</a>"
				];
			}
			else {
				$api_output = (object)[
					'status_code' => 7,
					'message' => "Error, the transaction was canceled."
				];
			}
		}
		else {
			$api_output = (object)[
				'status_code' => 8,
				'message' => "You don't have that many coins available to vote right now."
			];
		}
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