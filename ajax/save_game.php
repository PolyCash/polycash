<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser && $game) {
	if ($game['creator_id'] == $thisuser['user_id']) {
		$from_game_status = $game['game_status'];
		$game_status = mysql_real_escape_string($_REQUEST['game_status']);
		$status_changed = false;
		if ($game_status != $from_game_status) {
			if (in_array($game_status, array('paused','running')) && ($from_game_status == "unstarted" || ($from_game_status == "running" && $game_status == "paused") || ($from_game_status == "paused" && $game_status == "running"))) {
				$q = "UPDATE games SET game_status='".$game_status."' WHERE game_id='".$game['game_id']."';";
				$r = run_query($q);
				$status_changed = true;
			}
			else {
				output_message(2, "You can't switch the game to that status right now.", false);
				die();
			}
		}
		
		if ($game['game_status'] == "unstarted") {
			$game_form_vars = explode(",", "giveaway_status,giveaway_amount,maturity,max_voting_fraction,name,payout_weight,round_length,seconds_per_block,pos_reward,pow_reward,game_status");
			
			$q = "UPDATE games SET ";
			
			for ($i=0; $i<count($game_form_vars); $i++) {
				$game_var = $game_form_vars[$i];
				$game_val = mysql_real_escape_string($_REQUEST[$game_form_vars[$i]]);
				
				if (in_array($game_var, array('pos_reward','pow_reward','giveaway_amount'))) $game_val = intval(floatval($game_val)*pow(10,8));
				else if ($game_var == "max_voting_fraction") $game_val = intval($game_val)/100;
				else if (in_array($game_var, array('maturity', 'round_length', 'seconds_per_block'))) $game_val = intval($game_val);
				
				$q .= $game_var."='".$game_val."', ";
			}
			
			$q = substr($q, 0, strlen($q)-2)." WHERE game_id='".$game['game_id']."';";
			$r = run_query($q);
			
			$game_name = make_alphanumeric($_REQUEST['name'], " -()/!.,:;#");
			
			if ($game_name != $game['name']) {
				$q = "SELECT * FROM games WHERE name='".mysql_real_escape_string($_REQUEST['name'])."' AND game_id != '".$game['game_id']."';";
				$r = run_query($q);
				if (mysql_numrows($r) > 0) {
					output_message(2, "Game title could not be changed; a game with that name already exists.");
				}
				else {
					$url_identifier = game_url_identifier($game_name);
					$q = "UPDATE games SET name='".mysql_real_escape_string($game_name)."', url_identifier='".$url_identifier."' WHERE game_id='".$game['game_id']."';";
					$r = run_query($q);
					output_message(1, "Great, your changes have been saved.", false);
				}
			}
			else output_message(1, "Great, your changes have been saved.", false);
		}
		else {
			if ($status_changed) output_message(1, "Great, your changes have been saved.", false);
			else output_message(2, "This game can't be changed, it's already started.", false);
		}
		
		if ($status_changed) {
			if ($from_game_status == "unstarted" && $game_status == "running" && $game['giveaway_status'] == "on" && $game['giveaway_amount'] > 0) {
				$giveaway_utxo_count = 5;
				
				for ($i=0; $i<$giveaway_utxo_count; $i++) {
					new_transaction($game, false, array(intval($game['giveaway_amount']/$giveaway_utxo_count)), false, $thisuser['user_id'], last_block_id($game_id), 'giveaway', false, false, false);
				}
				
				$q = "SELECT u.* FROM users u JOIN user_strategies s ON u.user_id=s.user_id WHERE u.user_id != '".$thisuser['user_id']."' AND s.voting_strategy != 'manual' ORDER BY RAND() LIMIT 10;";
				$r = run_query($q);
				while ($player = mysql_fetch_array($r)) {
					ensure_user_in_game($player['user_id'], $game_id);
					for ($i=0; $i<$giveaway_utxo_count; $i++) {
						new_transaction($game, false, array(intval($game['giveaway_amount']/$giveaway_utxo_count)), false, $player['user_id'], last_block_id($game_id), 'giveaway', false, false, false);
					}
				}
			}
		}
	}
	else output_message(2, "You don't have permission to modify this game.", false);
}
else output_message(2, "Please log in.", false);
?>