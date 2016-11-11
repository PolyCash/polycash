<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	$game_id = intval($_REQUEST['game_id']);
	
	if ($action == "switch") {
		$game = new Game($app, $game_id);
		
		if ($game) {
			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$user_game = $r->fetch();
				
				$app->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
			}
			else {
				if ($game->db_game['creator_id'] > 0) {
					$app->output_message(2, "That game doesn't exist or you don't have permission to join it.");
				}
				else {
					$user_game = $thisuser->ensure_user_in_game($game);
					
					$q = "UPDATE users SET game_id='".$game->db_game['game_id']."' WHERE user_id='".$thisuser->db_user['user_id']."';";
					$r = $app->run_query($q);
					
					$app->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
				}
			}
		}
		else $app->output_message(2, "That game doesn't exist or you don't have permission to join it.");
	}
	else if ($action == "fetch" || $action == "new") {
		if ($action == "new") {
			$new_game_perm = $thisuser->new_game_permission();
			
			if ($new_game_perm) {
				$q = "SELECT MAX(creator_game_index) FROM games WHERE creator_id='".$thisuser->db_user['user_id']."';";
				$r = $app->run_query($q);
				if ($r->rowCount() > 0) {
					$game_index = $r->fetch(PDO::FETCH_NUM);
					$game_index = $game_index[0]+1;
				}
				else $game_index = 1;
				
				$blockchain_id = 2;
				$blockchain = new Blockchain($app, $blockchain_id);
				
				$q = "INSERT INTO games SET blockchain_id='".$blockchain->db_blockchain['blockchain_id']."', creator_id='".$thisuser->db_user['user_id']."', maturity=0, round_length=10, seconds_per_block='".$blockchain->db_blockchain['seconds_per_block']."', buyin_policy='unlimited', block_timing='realistic', creator_game_index='".$game_index."', logo_image_id=34, inflation='exponential', pos_reward='0', pow_reward='0', start_datetime='".date("Y-m-d g:\\0\\0a", time()+(2*60*60))."';";
				$r = $app->run_query($q);
				$game_id = $app->last_insert_id();
				
				$game = new Game($blockchain, $game_id);
				$game_name = "Private Game #".$game_id;
				$url_identifier = $app->game_url_identifier($game_name);
				
				$q = "UPDATE games SET name='".$game_name."', url_identifier='".$url_identifier."' WHERE game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
				$game->db_game['name'] = $game_name;
				$game->db_game['url_identifier'] = $url_identifier;
				
				if ($game->db_game['giveaway_status'] == "public_free") {
					$user_game = $thisuser->ensure_user_in_game($game);
				}
				
				$q = "UPDATE user_games ug JOIN user_strategies s ON ug.strategy_id=s.strategy_id SET s.voting_strategy='manual' WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND ug.game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
			}
			else {
				$app->output_message(2, "You don't have permission to create a new game.");
				die();
			}
		}
		else {
			$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $game_id);
		}
		
		$q = "SELECT game_id, blockchain_id, creator_id, event_rule, option_group_id, event_entity_type_id, events_per_round, event_type_name, game_status, block_timing, giveaway_status, giveaway_amount, maturity, name, payout_weight, round_length, seconds_per_block, pos_reward, pow_reward, inflation, exponential_inflation_rate, exponential_inflation_minershare, final_round, invite_cost, invite_currency, coin_name, coin_name_plural, coin_abbreviation, start_condition, start_datetime, buyin_policy, game_buyin_cap, default_vote_effectiveness_function, default_max_voting_fraction, game_starting_block, escrow_address, genesis_tx_hash, genesis_amount FROM games WHERE game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$switch_game = $r->fetch(PDO::FETCH_ASSOC);

			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$switch_game['game_id']."';";
			$r = $app->run_query($q);
			$user_game = $r->fetch();
			
			if ($switch_game['creator_id'] == $thisuser->db_user['user_id'] || $user_game) {
				if ($switch_game['creator_id'] == $thisuser->db_user['user_id']) $switch_game['my_game'] = true;
				else $switch_game['my_game'] = false;

				$switch_game['creator_id'] = false;

				$switch_game['name_disp'] = '<a target="_blank" href="/'.$game->db_game['url_identifier'].'">'.$switch_game['name'].'</a>';

				$switch_game['start_date'] = date("n/j/Y", strtotime($switch_game['start_datetime']));
				$switch_game['start_time'] = date("G", strtotime($switch_game['start_datetime']));
				
				$app->output_message(1, "", $switch_game);
			}
			else $app->output_message(2, "Access denied", false);
		}
		else $app->output_message(2, "Access denied", false);
	}
	else $app->output_message(3, "Bad URL", false);
	/*else if ($action == "reset" || $action == "delete") {
		$q = "SELECT * FROM game_types WHERE game_id='".$game_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$this_game = $r->fetch();
			if ($this_game['p2p_mode'] == "none" && $this_game['creator_id'] == $thisuser->db_user['user_id']) {
				$success = delete_reset_game($action, $game_id);
				
				if ($success) {
					$output_obj['redirect_url'] = '/wallet/'.$game->db_game['url_identifier'];
					
					if ($action == "delete") {
						$q = "SELECT * FROM game_types WHERE game_id='".$app->get_site_constant('primary_game_id')."';";
						$r = $app->run_query($q);
						$primary_game = $r->fetch();
						
						$q = "UPDATE users SET game_id='".$primary_game['game_id']."' WHERE user_id='".$thisuser->db_user['user_id']."';";
						$r = $app->run_query($q);
						
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
else $app->output_message(2, "Please log in.", false);
?>