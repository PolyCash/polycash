<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$game_id = (int) $_REQUEST['game_id'];
	
	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $game_id);
	
	if ($game) {
		$game_info = false;
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		if ($r->rowCount() > 0) {
			$game_info['redirect_user'] = 1;
		}
		else $game_info['redirect_user'] = 0;
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$game_info['url_identifier'] = $game->db_game['url_identifier'];

			if ($game->db_game['game_status'] == "editable") {
				if ($_REQUEST['giveaway_status'] == "public_free" || $_REQUEST['giveaway_status'] == "public_pay") $game_info['redirect_user'] = 1;
				
				$_REQUEST['invite_currency'] = $_REQUEST['base_currency_id'];
				
				$game_form_vars = explode(",", "event_rule,option_group_id,event_entity_type_id,events_per_round,event_type_name,maturity,name,payout_weight,round_length,pos_reward,inflation,exponential_inflation_rate,final_round,coin_name,coin_name_plural,coin_abbreviation,start_condition,buyin_policy,game_buyin_cap,default_vote_effectiveness_function,default_effectiveness_param1,default_max_voting_fraction,game_starting_block,escrow_address,genesis_tx_hash,genesis_amount");
				
				if ($_REQUEST['inflation'] == "exponential") $_REQUEST['payout_weight'] = "coin_round";
				if ($_REQUEST['event_rule'] == "single_event_series") $_REQUEST['events_per_round'] = 1;
				
				$blockchain_id = (int) $_REQUEST['blockchain_id'];
				$db_blockchain = $app->run_query("SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';")->fetch();
				
				$q = "UPDATE games SET blockchain_id='".$db_blockchain['blockchain_id']."', ";
				
				for ($i=0; $i<count($game_form_vars); $i++) {
					$game_var = $game_form_vars[$i];
					$game_val = $_REQUEST[$game_form_vars[$i]];
					
					if (in_array($game_var, array('pos_reward', 'genesis_amount'))) $game_val = (int) $game_val*pow(10,$game->db_game['decimal_places']);
					else if (in_array($game_var, array("exponential_inflation_rate", "default_max_voting_fraction"))) $game_val = intval($game_val)/100;
					else if (in_array($game_var, array('maturity', 'round_length', 'final_round','blockchain_id'))) $game_val = intval($game_val);
					else $game_val = $app->quote_escape($app->strong_strip_tags($game_val));
					
					$q .= $game_var."=".$game_val.", ";
				}
				
				$q = substr($q, 0, strlen($q)-2)." WHERE game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
				
				$game_name = $app->strong_strip_tags($app->make_alphanumeric($_REQUEST['name'], "$ -()/!.,:;#"));
				
				$url_error = false;

				if ($game_name != $game->db_game['name']) {
					$q = "SELECT * FROM games WHERE name=".$app->quote_escape($game_name)." AND game_id != '".$game->db_game['game_id']."';";
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
				
				if ($url_error) {
					$app->output_message(2, $error_message, false);
				}
				else {
					$action = $_REQUEST['action'];
					
					if ($action == "publish") {
						$q = "UPDATE games SET game_status='published' WHERE game_id='".$game->db_game['game_id']."';";
						$r = $app->run_query($q);
						
						$game->ensure_events_until_block($game->blockchain->last_block_id()+1);
						
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