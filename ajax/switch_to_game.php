<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$game = $_REQUEST['game'];
	
	if ($game == "reset" || $game == "delete") {
		$action = $game;
		$game_id = $thisuser['game_id'];
		
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
					
					echo "1";
				}
				else echo "Error, the game couldn't be reset.";
			}
			else echo "You can't modify this game.";
		}
		else echo "You can't modify this game.";
	}
	else if ($game == "new") {
		$q = "SELECT MAX(creator_game_index) FROM games WHERE creator_id='".$thisuser['user_id']."';";
		$r = run_query($q);
		if (mysql_numrows($r) > 0) {
			$game_index = mysql_fetch_row($r);
			$game_index = $game_index[0]+1;
		}
		else $game_index = 1;
		
		$q = "INSERT INTO games SET creator_id='".$thisuser['user_id']."', seconds_per_block='8', block_timing='realistic', creator_game_index='".$game_index."', game_type='simulation', name='Practice Game #".$game_index."';";
		$r = run_query($q);
		$game_id = mysql_insert_id();
		
		ensure_game_nations($game_id);
		
		ensure_user_in_game($thisuser['user_id'], $game_id);
		for ($i=0; $i<5; $i++) {
			new_webwallet_multi_transaction($game_id, false, array(20000000000), false, $thisuser['user_id'], last_block_id($game_id), 'giveaway', false, false, false);
		}
		
		$q = "SELECT * FROM users WHERE user_id != '".$thisuser['user_id']."' ORDER BY RAND() LIMIT 10;";
		$r = run_query($q);
		while ($player = mysql_fetch_array($r)) {
			ensure_user_in_game($player['user_id'], $game_id);
			for ($i=0; $i<5; $i++) {
				new_webwallet_multi_transaction($game_id, false, array(20000000000), false, $player['user_id'], last_block_id($game_id), 'giveaway', false, false, false);
			}
		}
		
		$q = "UPDATE users SET game_id='".$game_id."' WHERE user_id='".$thisuser['user_id']."';";
		$r = run_query($q);
		
		$q = "UPDATE user_games ug, user_strategies s SET s.voting_strategy='manual' WHERE ug.strategy_id=s.strategy_id AND ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game_id."';";
		$r = run_query($q);
		
		echo "1";
	}
	else {
		$game_id = intval($game);
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$q = "UPDATE users SET game_id='".$game_id."' WHERE user_id='".$thisuser['user_id']."';";
			$r = run_query($q);
			
			echo "1";
		}
		else echo "That game doesn't exist or you don't have permission to join it.";
	}
}
else echo "Please log in.";
?>