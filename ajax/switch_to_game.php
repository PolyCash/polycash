<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$game = $_REQUEST['game'];
	
	if ($game == "reset" || $game == "delete") {
		$game_id = $thisuser['game_id'];
		
		$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$this_game = mysql_fetch_array($r);
			
			if ($this_game['game_type'] == "instant" && $this_game['creator_id'] == $thisuser['user_id']) {
				$success = delete_reset_game($game, $game_id);
				
				if ($success) {
					if ($game == "delete") {
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
		
		$q = "INSERT INTO games SET creator_id='".$thisuser['user_id']."', seconds_per_block='8', block_timing='realistic', creator_game_index='".$game_index."', game_type='instant', name='Practice Game #".$game_index."';";
		$r = run_query($q);
		$game_id = mysql_insert_id();
		
		ensure_game_nations($game_id);
		
		$q = "SELECT * FROM users ORDER BY RAND() LIMIT 10;";
		$r = run_query($q);
		while ($player = mysql_fetch_array($r)) {
			ensure_user_in_game($player['user_id'], $game_id);
			new_webwallet_transaction($game_id, false, 100000000000, $player['user_id'], last_block_id($game_id), 'giveaway');
		}
		
		$q = "UPDATE users SET game_id='".$game_id."' WHERE user_id='".$thisuser['user_id']."';";
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