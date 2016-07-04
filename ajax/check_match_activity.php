<?php
include("../includes/connect.php");
include("../includes/get_session.php");

$output = false;

if ($thisuser) {
	$match_id = intval($_REQUEST['match_id']);
	
	if ($match_id > 0) {
		$q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.match_id='".$match_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$match = mysql_fetch_array($r);
			
			$my_membership = user_match_membership($thisuser['user_id'], $match['match_id']);
			
			if ($my_membership) {
				$last_move_number = intval($_REQUEST['last_move_number']);
				$last_message_id = intval($_REQUEST['last_message_id']);
				$last_message_id_db = last_match_message($match['match_id']);
				$current_round_number = intval($_REQUEST['current_round_number']);
				$round_number_db = $match['current_round_number'];
				
				$output['new_round'] = 0;
				
				if ($match['last_move_number'] != $last_move_number) {
					$output['new_move'] = 1;
					$output['last_move_number'] = $match['last_move_number'];
					$output['match_body'] = match_body($match, $my_membership, $thisuser);
					$output['account_value'] = match_mature_balance($my_membership['membership_id']);
					
					if ($current_round_number != $round_number_db) {
						$output['new_round'] = 1;
						$output['current_round_number'] = $round_number_db;
						$output['last_round_result'] = round_result_html($match, $current_round_number, $thisuser);
					}
				}
				else {
					$output['new_move'] = 0;
				}
				
				if ($last_message_id != $last_message_id_db) {
					$output['last_message_id'] = $last_message_id_db;
					$output['new_messages'] = show_match_messages($match, $thisuser['user_id'], $last_message_id);
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