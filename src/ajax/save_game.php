<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $game_id);
	
	if ($game) {
		$game_info = false;
		
		$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
		
		if ($user_game) {
			$game_info['redirect_user'] = 1;
		}
		else $game_info['redirect_user'] = 0;
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$game_info['url_identifier'] = $game->db_game['url_identifier'];

			if ($game->db_game['game_status'] == "editable") {
				$game_form_vars = ['event_rule','option_group_id','events_per_round','event_type_name','name','payout_weight','round_length','inflation','exponential_inflation_rate','coin_name','coin_name_plural','coin_abbreviation','start_condition','buyin_policy','game_buyin_cap','default_vote_effectiveness_function','default_effectiveness_param1','default_max_voting_fraction','game_starting_block','escrow_address','genesis_tx_hash','genesis_amount','pow_reward_type', 'pow_fixed_reward'];
				
				if ($_REQUEST['inflation'] == "exponential") $_REQUEST['payout_weight'] = "coin_round";
				if ($_REQUEST['event_rule'] == "single_event_series") $_REQUEST['events_per_round'] = 1;
				
				$blockchain_id = (int) $_REQUEST['blockchain_id'];
				$db_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
				
				$change_game_q = "UPDATE games SET blockchain_id=:blockchain_id, ";
				$change_game_params = [
					'blockchain_id' => $db_blockchain['blockchain_id'],
					'game_id' => $game->db_game['game_id']
				];
				
				for ($i=0; $i<count($game_form_vars); $i++) {
					$game_var = $game_form_vars[$i];
					$game_val = empty($_REQUEST[$game_form_vars[$i]]) ? "" : $_REQUEST[$game_form_vars[$i]];
					
					if (in_array($game_var, ["genesis_amount","exponential_inflation_rate", "default_max_voting_fraction"])) $game_val = (float) $game_val;
					else if (in_array($game_var, ['round_length', 'blockchain_id'])) $game_val = intval($game_val);
					else $game_val = $app->strong_strip_tags($game_val);
					
					$change_game_q .= $game_var."=:".$game_var.", ";
					$change_game_params[$game_var] = $game_val;
					$game->db_game[$game_var] = $game_val;
				}
				
				$change_game_q = substr($change_game_q, 0, strlen($change_game_q)-2)." WHERE game_id=:game_id;";
				$app->run_query($change_game_q, $change_game_params);
				
				$game_name = $app->strong_strip_tags($app->make_alphanumeric($_REQUEST['name'], "$ -()/!.,:;#"));
				
				$url_error = false;

				if ($game_name != $game->db_game['name']) {
					$conflicting_game = $app->run_query("SELECT * FROM games WHERE name=:name AND game_id != :game_id;", [
						'name' => $game_name,
						'game_id' => $game->db_game['game_id']
					])->fetch();
					
					if ($conflicting_game) {
						$url_error = true;
						$error_message = "Game title could not be changed; a game with that name already exists.";
					}
					else {
						$url_identifier = $app->game_url_identifier($game_name);
						$app->run_query("UPDATE games SET name=:name, url_identifier=:url_identifier WHERE game_id=:game_id;", [
							'name' => $game_name,
							'url_identifier' => $url_identifier,
							'game_id' => $game->db_game['game_id']
						]);
						$game_info['url_identifier'] = $url_identifier;
					}
				}
				
				if ($url_error) {
					$app->output_message(2, $error_message, false);
				}
				else {
					$action = $_REQUEST['action'];
					
					if ($action == "publish") {
						$game->set_game_status('published');
						
						$app->output_message(1, "The game has been published.", $game_info);
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