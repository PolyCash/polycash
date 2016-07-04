<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	$game_id = intval($_REQUEST['game_id']);
	
	if ($action == "switch") {
		$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$game = mysql_fetch_array($r);
			
			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game_id."';";
			$r = run_query($q);
			
			if (mysql_numrows($r) == 1) {
				$user_game = mysql_fetch_array($r);
				
				$q = "UPDATE users SET game_id='".$user_game['game_id']."' WHERE user_id='".$thisuser['user_id']."';";
				$r = run_query($q);
				
				output_message(1, "", false);
			}
			else {
				if ($game['creator_id'] > 0) {
					output_message(2, "That game doesn't exist or you don't have permission to join it.");
				}
				else {
					ensure_user_in_game($thisuser['user_id'], $game['game_id']);
					
					$q = "UPDATE users SET game_id='".$game['game_id']."' WHERE user_id='".$thisuser['user_id']."';";
					$r = run_query($q);
					
					output_message(1, "", false);
				}
			}
		}
		else output_message(2, "That game doesn't exist or you don't have permission to join it.");
	}
	else if ($action == "fetch" || $action == "new") {
		if ($action == "new") {
			$q = "SELECT MAX(creator_game_index) FROM games WHERE creator_id='".$thisuser['user_id']."';";
			$r = run_query($q);
			if (mysql_numrows($r) > 0) {
				$game_index = mysql_fetch_row($r);
				$game_index = $game_index[0]+1;
			}
			else $game_index = 1;
			
			$q = "INSERT INTO games SET creator_id='".$thisuser['user_id']."', maturity=0, round_length=20, seconds_per_block='10', block_timing='realistic', creator_game_index='".$game_index."', game_type='simulation', pos_reward='".(6000*pow(10,8))."', pow_reward='".(200*pow(10,8))."';";
			$r = run_query($q);
			$game_id = mysql_insert_id();
			
			$game_name = "Practice Game #".$game_id;
			$url_identifier = game_url_identifier($game_name);
			
			$q = "UPDATE games SET name='".$game_name."', url_identifier='".$url_identifier."' WHERE game_id='".$game_id."';";
			$r = run_query($q);
			
			$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
			$r = run_query($q);
			$game = mysql_fetch_array($r);
			
			ensure_game_nations($game_id);
			
			// Add this user and 10 random users to the game
			ensure_user_in_game($thisuser['user_id'], $game_id);
			
			$q = "UPDATE users SET game_id='".$game_id."' WHERE user_id='".$thisuser['user_id']."';";
			$r = run_query($q);
			
			$q = "UPDATE user_games ug, user_strategies s SET s.voting_strategy='manual' WHERE ug.strategy_id=s.strategy_id AND ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game_id."';";
			$r = run_query($q);
			
			$invitation = false;
			generate_invitation($game_id, $thisuser['user_id'], $invitation, $thisuser['user_id']);
			$invitation = false;
			$success = try_apply_giveaway($game, $user, $invitation);
		}
		
		$q = "SELECT g.creator_id, g.game_id, g.game_status, g.block_timing, g.giveaway_status, g.giveaway_amount, g.maturity, g.max_voting_fraction, g.name, g.payout_weight, g.round_length, g.seconds_per_block, g.pos_reward, g.pow_reward FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$switch_game = mysql_fetch_array($r);
			if ($switch_game['creator_id'] == $thisuser['user_id']) $switch_game['my_game'] = true;
			else $switch_game['my_game'] = false;
			$switch_game['creator_id'] = false;
			output_message(1, "", $switch_game);
		}
		else output_message(2, "Access denied", false);
	}
	else if ($action == "reset" || $action == "delete") {
		$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$this_game = mysql_fetch_array($r);
			if ($this_game['game_type'] == "simulation" && $this_game['creator_id'] == $thisuser['user_id']) {
				$success = delete_reset_game($action, $game_id);
				
				if ($success) {
					if ($action == "delete") {
						$q = "UPDATE users SET game_id='".get_site_constant('primary_game_id')."' WHERE user_id='".$thisuser['user_id']."';";
						$r = run_query($q);
					}
					
					output_message(1, "", false);
				}
				else output_message(2, "Error, the game couldn't be reset.", false);
			}
			else output_message(2, "You can't modify this game.", false);
		}
		else output_message(2, "You can't modify this game.", false);
	}
	else output_message(3, "Bad URL", false);
}
else output_message(2, "Please log in.", false);
?>