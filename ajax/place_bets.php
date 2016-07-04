<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser && $game) {
	$user_strategy = false;
	$success = $game->get_user_strategy($thisuser->db_user['user_id'], $user_strategy);
	if (!$success) {
		$output_obj['result_code'] = 2;
		$output_obj['message'] = "Error, the fee amount could not be determined.";
	}
	
	$amounts = explode(",", $_REQUEST['amounts']);
	$options = explode(",", $_REQUEST['options']);
	
	if (count($amounts) != count($options)) {
		$output_obj['result_code'] = 2;
		$output_obj['message'] = "You've reached an invalid URL.";
	}
	else {
		if ($game->db_game['losable_bets_enabled'] == 1) {
			$amount_sum = 0;
			$address_ids = array();
			$max_option_id = false;
			$max_amount = -1;
			$round_id = intval($_REQUEST['round']);
			$bet_round_range = $game->bet_round_range();
			
			if ($round_id >= $bet_round_range[0] && $round_id  <= $bet_round_range[1]) {
				for ($i=0; $i<count($amounts); $i++) {
					if ($options[$i] != intval($options[$i]) || $amounts[$i] != intval($amounts[$i])) {
						$output_obj['result_code'] = 5;
						$output_obj['message'] = "You submitted an invalid number.";
						echo json_encode($output_obj);
						die();
					}
					if ($options[$i] == 0) $options[$i] = false;
					else if ($options[$i] > 0 && $options[$i] <= 16) {}
					else {
						$output_obj['result_code'] = 6;
						$output_obj['message'] = "Please select a valid betting outcome.";
						echo json_encode($output_obj);
						die();
					}
					
					if ($amounts[$i] > $max_amount) {
						$max_option_id = $options[$i];
						$max_amount = $amounts[$i];
					}
					
					$burn_address = $game->get_bet_burn_address($round_id, $options[$i]);
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
				
				$remainder_address_id = $thisuser->user_address_id($game->db_game['game_id'], $max_option_id);
				
				if ($amount_sum > 0) {
					$last_block_id = $game->last_block_id();
					$mining_block_id = $last_block_id+1;
					$account_value = $thisuser->account_coin_value($game);
					$immature_balance = $thisuser->immature_balance($game);
					$mature_balance = $thisuser->mature_balance($game);
					
					if ($amount_sum <= $mature_balance) {
						$transaction_id = $game->new_transaction(false, $amounts, $thisuser->db_user['user_id'], false, false, 'bet', false, $address_ids, $remainder_address_id, $user_strategy['transaction_fee']);
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