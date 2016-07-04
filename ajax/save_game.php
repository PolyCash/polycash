<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);
	
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	if (mysql_numrows($r) == 1) {
		$game = mysql_fetch_array($r);
		$game_info = false;
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game['game_id']."';";
		$r = run_query($q);
		if (mysql_numrows($r) > 0) {
			$game_info['user_in_game'] = 1;
		}
		else $game_info['user_in_game'] = 0;
		
		if ($game['creator_id'] == $thisuser['user_id']) {
			$game_info['url_identifier'] = $game['url_identifier'];

			if ($game['game_status'] == "editable") {
				$game_form_vars = explode(",", "giveaway_status,giveaway_amount,maturity,max_voting_fraction,name,payout_weight,round_length,seconds_per_block,pos_reward,pow_reward,inflation,exponential_inflation_rate,exponential_inflation_minershare,final_round,invite_cost,invite_currency,coin_name,coin_name_plural,coin_abbreviation,start_condition,start_condition_players");
				
				$q = "UPDATE games SET ";

				$start_datetime = date("Y-m-d g:\0\0", strtotime($_REQUEST['start_date']." ".$_REQUEST['start_time'].":00"));
				$q .= "start_datetime='".$start_datetime."', ";

				for ($i=0; $i<count($game_form_vars); $i++) {
					$game_var = $game_form_vars[$i];
					$game_val = mysql_real_escape_string($_REQUEST[$game_form_vars[$i]]);
					
					if (in_array($game_var, array('pos_reward','pow_reward','giveaway_amount'))) $game_val = intval(floatval($game_val)*pow(10,8));
					else if (in_array($game_var, array("max_voting_fraction", "exponential_inflation_minershare", "exponential_inflation_rate"))) $game_val = intval($game_val)/100;
					else if (in_array($game_var, array('maturity', 'round_length', 'seconds_per_block', 'final_round','invite_currency'))) $game_val = intval($game_val);
					
					$q .= $game_var."='".$game_val."', ";
				}
				
				$q = substr($q, 0, strlen($q)-2)." WHERE game_id='".$game['game_id']."';";
				$r = run_query($q);
				
				$game_name = make_alphanumeric($_REQUEST['name'], " -()/!.,:;#");
				
				$url_error = false;

				if ($game_name != $game['name']) {
					$q = "SELECT * FROM games WHERE name='".mysql_real_escape_string($_REQUEST['name'])."' AND game_id != '".$game['game_id']."';";
					$r = run_query($q);
					if (mysql_numrows($r) > 0) {
						$url_error = true;
						$error_message = "Game title could not be changed; a game with that name already exists.";
					}
					else {
						$url_identifier = game_url_identifier($game_name);
						$q = "UPDATE games SET name='".mysql_real_escape_string($game_name)."', url_identifier='".$url_identifier."' WHERE game_id='".$game['game_id']."';";
						$r = run_query($q);
						$game_info['url_identifier'] = $url_identifier;
					}
				}

				if ($url_error) {
					output_message(2, $error_message, false);
				}
				else {
					$action = $_REQUEST['action'];

					if ($action == "publish") {
						$q = "UPDATE games SET game_status='published' WHERE game_id='".$game['game_id']."';";
						$r = run_query($q);

						output_message(1, "Great, your changes have been saved.", $game_info);
					}
					else {
						output_message(1, "Great, your changes have been saved.", $game_info);
					}
				}
			}
			else output_message(2, "This game can't be changed, it's already started.", false);
		}
		else output_message(2, "You don't have permission to modify this game.", false);
	}
	else output_message(2, "Invalid game ID.", false);
}
else output_message(2, "Please log in.", false);
?>