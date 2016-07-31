<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);
	
	$game = new Game($app, $game_id);
	
	if ($game) {
		$game_info = false;
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		if ($r->rowCount() > 0) {
			$game_info['user_in_game'] = 1;
		}
		else $game_info['user_in_game'] = 0;
		
		if ($game->db_game['creator_id'] == $thisuser->db_user['user_id']) {
			$game_info['url_identifier'] = $game->db_game['url_identifier'];

			if ($game->db_game['game_status'] == "editable") {
				$game_form_vars = explode(",", "giveaway_status,giveaway_amount,maturity,max_voting_fraction,name,payout_weight,round_length,seconds_per_block,pos_reward,pow_reward,inflation,exponential_inflation_rate,exponential_inflation_minershare,final_round,invite_cost,invite_currency,coin_name,coin_name_plural,coin_abbreviation,start_condition,start_condition_players,buyin_policy,per_user_buyin_cap,game_buyin_cap,vote_effectiveness_function");
				
				if ($_REQUEST['inflation'] == "exponential") $_REQUEST['payout_weight'] = "coin_round";
				
				$q = "UPDATE games SET ";

				$start_datetime = date("Y-m-d g:\\0\\0", strtotime($_REQUEST['start_date']." ".$_REQUEST['start_time'].":00"));
				$q .= "start_datetime='".$start_datetime."', ";
				
				for ($i=0; $i<count($game_form_vars); $i++) {
					$game_var = $game_form_vars[$i];
					$game_val = $_REQUEST[$game_form_vars[$i]];
					
					if (in_array($game_var, array('pos_reward','pow_reward','giveaway_amount'))) $game_val = (int) $game_val*pow(10,8);
					else if (in_array($game_var, array("max_voting_fraction", "exponential_inflation_minershare", "exponential_inflation_rate"))) $game_val = intval($game_val)/100;
					else if (in_array($game_var, array('maturity', 'round_length', 'seconds_per_block', 'final_round','invite_currency'))) $game_val = intval($game_val);
					else $game_val = $app->quote_escape($game_val);
					
					$q .= $game_var."=".$game_val.", ";
				}
				
				$q = substr($q, 0, strlen($q)-2)." WHERE game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
				
				$game_name = $app->make_alphanumeric($_REQUEST['name'], "$ -()/!.,:;#");
				
				$url_error = false;

				if ($game_name != $game->db_game['name']) {
					$q = "SELECT * FROM games WHERE name=".$app->quote_escape($_REQUEST['name'])." AND game_id != '".$game->db_game['game_id']."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$url_error = true;
						$error_message = "Game title could not be changed; a game with that name already exists.";
					}
					else {
						$url_identifier = $app->game_url_identifier($game_name);
						$q = "UPDATE games SET name=".$app->quote_escape($game_name).", url_identifier=".$app->quote_escape($url_identifier)." WHERE game_id='".$game->db_game['game_id']."';";
						$r = $app->run_query($q);
						$game_info['url_identifier'] = $url_identifier;
					}
				}
				
				/*$group_id = intval($_REQUEST['group_id']);
				
				if ($group_id != $game->db_game['group_id']) {
					$qq = "SELECT * FROM option_groups WHERE group_id='".$group_id."';";
					$rr = $app->run_query($qq);
					$option_group = $rr->fetch();
					$game->db_game['group_id'] = $option_group['group_id'];
					
					$qq = "UPDATE game_types SET group_id='".$option_group['group_id']."', option_name='".$option_group['option_name']."', option_name_plural='".$option_group['option_name_plural']."' WHERE game_id='".$game->db_game['game_id']."';";
					$rr = $app->run_query($qq);
					
					$game->db_game['group_id'] = $option_group['group_id'];
					$game->db_game['option_name'] = $option_group['option_name'];
					$game->db_game['option_name_plural'] = $option_group['option_name_plural'];
					
					$game->delete_options();
					$game->ensure_options();
				}*/

				if ($url_error) {
					$app->output_message(2, $error_message, false);
				}
				else {
					$action = $_REQUEST['action'];
					
					if ($action == "publish") {
						$q = "UPDATE games SET game_status='published'";
						if ($game->db_game['start_condition'] == "players_joined") $q .= ", initial_coins='".($game->db_game['start_condition_players']*$game->db_game['giveaway_amount'])."'";
						$q .= " WHERE game_id='".$game->db_game['game_id']."';";
						$r = $app->run_query($q);
						
						$app->output_message(1, "Great, your changes have been saved.", $game_info);
					}
					else {
						$app->output_message(1, "Great, your changes have been saved.", $game_info);
					}
				}
			}
			else $app->output_message(2, "This game can't be changed, it's already started.", false);
		}
		else $app->output_message(2, "You don't have permission to modify this game.", false);
	}
	else $app->output_message(2, "Invalid game ID.", false);
}
else $app->output_message(2, "Please log in.", false);
?>