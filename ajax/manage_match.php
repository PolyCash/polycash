<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$do = $_REQUEST['do'];
	
	if ($do == "new") {
		$match_type_id = intval($_REQUEST['match_type_id']);
		
		$q = "SELECT * FROM match_types WHERE match_type_id='".$match_type_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$match_type = $r->fetch();
			
			$q = "INSERT INTO matches SET creator_id='".$thisuser->db_user['user_id']."', status='pending', match_type_id='".$match_type['match_type_id']."';";
			$r = $app->run_query($q);
			$match_id = $app->last_insert_id();
			
			$match = new Match($match_id);
			
			$match->add_user_to_match($thisuser->db_user['user_id'], false, false);
			
			for ($round_id=1; $round_id<=$match_type['num_rounds']; $round_id++) {
				$q = "INSERT INTO match_rounds SET status='incomplete', match_id='".$match->db_match['match_id']."', round_number='".$round_id."';";
				$r = $app->run_query($q);
			}
			
			$result_code = 1;
			$output['match_id'] = $match_id;
		}
		else {
			$result_code = 3;
			$error_message = "Please supply a valid match type";
		}
	}
	else if ($do == "join" || $do == "start" || $do == "move") {
		$match_id = intval($_REQUEST['match_id']);
		
		$match = new Match($match_id);
		
		if ($match) {
			$my_membership = $match->user_match_membership($thisuser->db_user['user_id']);
			
			if ($do == "join") {
				if ($my_membership) {
					$result_code = 4;
					$error_message = "You've already joined this game.";
				}
				else {
					if ($match->db_match['num_players'] > $match->db_match['num_joined']) {
						$match->add_user_to_match($thisuser->db_user['user_id'], false, false);
						$result_code = 1;
					}
					else {
						$result_code = 5;
						$error_message = "This game is already full.";
					}
				}
			}
			else if ($do== "start") {
				if ($my_membership) {
					$match->set_match_status("running");
					$result_code = 1;
				}
				else {
					$result_code = 4;
					$error_message = "First, please join this game.";
				}
			}
			else if ($do == "move") {
				if ($my_membership) {
					$q = "SELECT * FROM match_moves WHERE membership_id='".$my_membership['membership_id']."' AND round_number='".$match->db_match['current_round_number']."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$my_move = $r->fetch();
						$result_code = 6;
						$error_message = "You've already made your move for this round.";
					}
					else {
						$amount = floatval($_REQUEST['amount']);
						$account_value = $match->match_mature_balance($my_membership['membership_id']);
						
						if ($amount == round($amount, 2)) {
							$amount = $amount*pow(10,8);
							
							if ($amount <= $account_value) {
								$move_id = $match->start_match_move($my_membership['membership_id'], 'burn', $amount);
								$result_code = 1;
							}
							else {
								$result_code = 8;
								$error_message = "You don't have that many coins right now.";
							}
						}
						else {
							$result_code = 7;
							$error_message = "Amounts should be rounded to 2 decimal places.";
						}
					}
				}
				else {
					$result_code = 5;
					$error_message = "This game is already full.";
				}
			}
		}
		else {
			$result_code = 3;
			$error_message = "Please supply a valid match ID.";
		}
	}
}
else {
	$result_code = 2;
	$error_message = "Please log in";
}

$output['result_code'] = $result_code;
$output['error_message'] = $error_message;

echo $app->json_encode($output);
?>