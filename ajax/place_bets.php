<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser) {
	$user_strategy = false;
	$success = get_user_strategy($thisuser['user_id'], $game['game_id'], $user_strategy);
	if (!$success) {
		$output_obj['result_code'] = 2;
		$output_obj['message'] = "Error, the fee amount could not be determined.";
	}
	
	$amounts = explode(",", $_REQUEST['amounts']);
	$nations = explode(",", $_REQUEST['nations']);
	
	if (count($amounts) != count($nations)) {
		$output_obj['result_code'] = 2;
		$output_obj['message'] = "You've reached an invalid URL.";
	}
	else {
		if ($game['losable_bets_enabled'] == 1) {
			$amount_sum = 0;
			$address_ids = array();
			$max_nation_id = false;
			$max_amount = -1;
			$round_id = intval($_REQUEST['round']);
			$bet_round_range = bet_round_range($game);
			
			if ($round_id >= $bet_round_range[0] && $round_id  <= $bet_round_range[1]) {
				for ($i=0; $i<count($amounts); $i++) {
					if ($nations[$i] != intval($nations[$i]) || $amounts[$i] != intval($amounts[$i])) {
						$output_obj['result_code'] = 5;
						$output_obj['message'] = "You submitted an invalid number.";
						echo json_encode($output_obj);
						die();
					}
					if ($nations[$i] == 0) $nations[$i] = false;
					else if ($nations[$i] > 0 && $nations[$i] <= 16) {}
					else {
						$output_obj['result_code'] = 6;
						$output_obj['message'] = "Please select a valid betting outcome.";
						echo json_encode($output_obj);
						die();
					}
					
					if ($amounts[$i] > $max_amount) {
						$max_nation_id = $nations[$i];
						$max_amount = $amounts[$i];
					}
					
					$burn_address = get_bet_burn_address($game, $round_id, $nations[$i]);
					if ($burn_address) {
						$address_ids[$i] = $burn_address['address_id'];
					}
					else {
						$output_obj['result_code'] = 7;
						$output_obj['message'] = "There was an error finding the betting address, your bet has been canceled.";
						echo json_encode($output_obj);
						die();
					}
					$amount_sum += $amounts[$i];
				}
				
				$remainder_address_id = user_address_id($game['game_id'], $thisuser['user_id'], $max_nation_id);
				
				if ($amount_sum > 0) {
					$last_block_id = last_block_id($game['game_id']);
					$mining_block_id = $last_block_id+1;
					$account_value = account_coin_value($game, $thisuser);
					$immature_balance = immature_balance($game, $thisuser);
					$mature_balance = mature_balance($game, $thisuser);
					
					if ($amount_sum <= $mature_balance) {
						$transaction_id = new_transaction($game, false, $amounts, $thisuser['user_id'], false, false, 'bet', false, $address_ids, $remainder_address_id, $user_strategy['transaction_fee']);
						if ($transaction_id > 0) {
							$output_obj['result_code'] = 11;
							$output_obj['message'] = "Great, your bet has been placed!";
						}
						else {
							$output_obj['result_code'] = 10;
							$output_obj['message'] = "There was an error, your transaction has been canceled.";
						}
					}
					else {
						$output_obj['result_code'] = 9;
						$output_obj['message'] = "You don't have that many coins available right now.";
					}
				}
				else {
					$output_obj['result_code'] = 8;
					$output_obj['message'] = "Please enter a valid amount.";
				}
			}
			else {
				$output_obj['result_code'] = 4;
				$output_obj['message'] = "Sorry, you can't vote in that round right now.";
			}
		}
		else {
			$output_obj['result_code'] = 3;
			$output_obj['message'] = "Betting is disabled.";
		}
	}
}
else {
	$output_obj['result_code'] = 1;
	$output_obj['message'] = "Please log in.";
}

echo json_encode($output_obj);
?>