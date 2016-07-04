<?php
include("../includes/connect.php");
include("../includes/get_session.php");

$output = false;

if ($thisuser) {
	$match_id = intval($_REQUEST['match_id']);
	
	if ($match_id > 0) {
		$match = new Match($match_id);
		
		if ($match) {
			$my_membership = $match->user_match_membership($thisuser->db_user['user_id']);
			
			if ($my_membership) {
				$last_move_number = intval($_REQUEST['last_move_number']);
				$last_message_id = intval($_REQUEST['last_message_id']);
				$last_message_id_db = $match->last_match_message();
				$current_round_number = intval($_REQUEST['current_round_number']);
				$round_number_db = $match['current_round_number'];
				
				$output['new_round'] = 0;
				
				if ($match->db_match['last_move_number'] != $last_move_number) {
					$output['new_move'] = 1;
					$output['last_move_number'] = $match->db_match['last_move_number'];
					$output['match_body'] = $match->match_body($my_membership, $thisuser);
					$output['account_value'] = $match->match_mature_balance($my_membership['membership_id']);
					
					if ($current_round_number != $round_number_db) {
						$output['new_round'] = 1;
						$output['current_round_number'] = $round_number_db;
						$output['last_round_result'] = $match->round_result_html($current_round_number, $thisuser);
					}
				}
				else {
					$output['new_move'] = 0;
				}
				
				if ($last_message_id != $last_message_id_db) {
					$output['last_message_id'] = $last_message_id_db;
					$output['new_messages'] = $match->show_match_messages($thisuser->db_user['user_id'], $last_message_id);
				}
				else {
					$output['new_messages'] = 0;
				}
			}
			else {
				$output['error_code'] = 3;
				$output['error_message'] = "Permission denied.";
			}
		}
		else {
			$output['error_code'] = 2;
			$output['error_message'] = "You supplied an invalid match ID.";
		}
	}
	else {
		$output['error_code'] = 2;
		$output['error_message'] = "You supplied an invalid match ID.";
	}
}
else {
	$output['error_code'] = 1;
	$output['error_message'] = "Please log in.";
}

echo json_encode($output);
?>