<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	$game_id = intval($_REQUEST['game_id']);
	
	if ($action == "switch") {
		$game = new Game($game_id);
		
		if ($game) {
			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$r = $GLOBALS['app']->run_query($q);
			
			if (mysql_numrows($r) == 1) {
				$user_game = mysql_fetch_array($r);
				
				$GLOBALS['app']->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
			}
			else {
				if ($game->db_game['creator_id'] > 0) {
					$GLOBALS['app']->output_message(2, "That game doesn't exist or you don't have permission to join it.");
				}
				else {
					$game->ensure_user_in_game($thisuser);
					
					$q = "UPDATE users SET game_id='".$game->db_game['game_id']."' WHERE user_id='".$thisuser->db_user['user_id']."';";
					$r = $GLOBALS['app']->run_query($q);
					
					$GLOBALS['app']->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
				}
			}
		}
		else $GLOBALS['app']->output_message(2, "That game doesn't exist or you don't have permission to join it.");
	}
	else if ($action == "fetch" || $action == "new") {
		if ($action == "new") {
			$new_game_perm = $thisuser->new_game_permission();
			
			if ($new_game_perm) {
				$q = "SELECT MAX(creator_game_index) FROM games WHERE creator_id='".$thisuser->db_user['user_id']."';";
				$r = $GLOBALS['app']->run_query($q);
				if (mysql_numrows($r) > 0) {
					$game_index = mysql_fetch_row($r);
					$game_index = $game_index[0]+1;
				}
				else $game_index = 1;
				
				$q = "INSERT INTO games SET creator_id='".$thisuser->db_user['user_id']."', maturity=0, round_length=60, seconds_per_block='6', block_timing='realistic', creator_game_index='".$game_index."', game_type='simulation', pos_reward='".(6000*pow(10,8))."', pow_reward='".(200*pow(10,8))."', start_datetime='".date("Y-m-d g:\\0\\0a", time()+(2*60*60))."';";
				$r = $GLOBALS['app']->run_query($q);
				$game_id = mysql_insert_id();
				
				$game = new Game($game_id);
				$game_name = "Private Game #".$game_id;
				$url_identifier = $GLOBALS['app']->game_url_identifier($game_name);
				
				$q = "UPDATE games SET name='".$game_name."', url_identifier='".$url_identifier."' WHERE game_id='".$game->db_game['game_id']."';";
				$r = $GLOBALS['app']->run_query($q);
				$game->db_game['name'] = $game_name;
				$game->db_game['url_identifier'] = $url_identifier;
				
				$game->ensure_game_options();
				
				if ($game->db_game['giveaway_status'] == "public_free") {
					$game->ensure_user_in_game($thisuser);
				}
				
				$q = "UPDATE users SET game_id='".$game->db_game['game_id']."' WHERE user_id='".$thisuser->db_user['user_id']."';";
				$r = $GLOBALS['app']->run_query($q);
				
				$q = "UPDATE user_games ug, user_strategies s SET s.voting_strategy='manual' WHERE ug.strategy_id=s.strategy_id AND ug.user_id='".$thisuser->db_user['user_id']."' AND ug.game_id='".$game->db_game['game_id']."';";
				$r = $GLOBALS['app']->run_query($q);
			}
			else {
				$GLOBALS['app']->output_message(2, "You don't have permission to create a new game.");
				die();
			}
		}
		else {
			$game = new Game($game_id);
		}
		
		$q = "SELECT creator_id, game_id, game_status, block_timing, giveaway_status, giveaway_amount, maturity, max_voting_fraction, name, payout_weight, round_length, seconds_per_block, pos_reward, pow_reward, inflation, exponential_inflation_rate, exponential_inflation_minershare, final_round, invite_cost, invite_currency, coin_name, coin_name_plural, coin_abbreviation, start_condition, start_datetime, start_condition_players, buyin_policy, per_user_buyin_cap, game_buyin_cap, option_group_id, payout_taper_function FROM games WHERE game_id='".$game->db_game['game_id']."';";
		$r = $GLOBALS['app']->run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$switch_game = mysql_fetch_array($r);

			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$switch_game['game_id']."';";
			$r = $GLOBALS['app']->run_query($q);
			$user_game = mysql_fetch_array($r);
			
			if ($switch_game['creator_id'] == $thisuser->db_user['user_id'] || $user_game) {
				if ($switch_game['creator_id'] == $thisuser->db_user['user_id']) $switch_game['my_game'] = true;
				else $switch_game['my_game'] = false;

				$switch_game['creator_id'] = false;

				$switch_game['name_disp'] = '<a target="_blank" href="/'.$game->db_game['url_identifier'].'">'.$switch_game['name'].'</a>';

				$switch_game['start_date'] = date("n/j/Y", strtotime($switch_game['start_datetime']));
				$switch_game['start_time'] = date("G", strtotime($switch_game['start_datetime']));
				
				$GLOBALS['app']->output_message(1, "", $switch_game);
			}
			else $GLOBALS['app']->output_message(2, "Access denied", false);
		}
		else $GLOBALS['app']->output_message(2, "Access denied", false);
	}
	else $GLOBALS['app']->output_message(3, "Bad URL", false);
	/*else if ($action == "reset" || $action == "delete") {
		$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$r = $GLOBALS['app']->run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$this_game = mysql_fetch_array($r);
			if ($this_game['game_type'] == "simulation" && $this_game['creator_id'] == $thisuser->db_user['user_id']) {
				$success = delete_reset_game($action, $game_id);
				
				if ($success) {
					$output_obj['redirect_url'] = '/wallet/'.$game->db_game['url_identifier'];
					
					if ($action == "delete") {
						$q = "SELECT * FROM games WHERE game_id='".$GLOBALS['app']->get_site_constant('primary_game_id')."';";
						$r = $GLOBALS['app']->run_query($q);
						$primary_game = mysql_fetch_array($r);
						
						$q = "UPDATE users SET game_id='".$primary_game['game_id']."' WHERE user_id='".$thisuser->db_user['user_id']."';";
						$r = $GLOBALS['app']->run_query($q);
						
						$output_obj['redirect_url'] = '/wallet/'.$primary_game['url_identifier'];
					}
					
					output_message(1, "", $output_obj);
				}
				else output_message(2, "Error, the game couldn't be reset.", false);
			}
			else output_message(2, "You can't modify this game.", false);
		}
		else output_message(2, "You can't modify this game.", false);
	}*/
}
else $GLOBALS['app']->output_message(2, "Please log in.", false);
?>